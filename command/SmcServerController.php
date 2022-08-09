<?php

namespace app\command;

use app\core\lib\Config;
use app\core\lib\controller\Controller;
use app\language\Api;
use app\module\utils\ResponseUtil;
use Exception;
use Pupilcp\App;
use Pupilcp\Log\Logger;
use Pupilcp\Smc;
use Throwable;

/**
 * 服务管理中心
 * Class SmcServerController
 * @package app\command
 */
class SmcServerController extends Controller
{
    const QUEUE_CONFIG = 'queue.config';

    /**
     * @var array
     */
    private $globalConfig;
    /**
     * @var array
     */
    private $queueConfig;
    private $redis;

    protected function init()
    {
        $this->globalConfig = [];
        if (file_exists(APP_ROOT . '/config/smc/globalConfig.php')) {
            $this->globalConfig = require APP_ROOT . '/config/smc/globalConfig.php';
        }
        $this->queueConfig = [];
        if (file_exists(APP_ROOT . '/config/smc/queueConfig.php')) {
            $this->queueConfig = require APP_ROOT . '/config/smc/queueConfig.php';
        }
    }

    //启动: php index.php "command/SmcServer/manage" "command=start"
    //停止: php index.php "command/SmcServer/manage" "command=stop"
    //重启: php index.php "command/SmcServer/manage" "command=restart"
    //查看状态: php index.php "command/SmcServer/manage" "command=status"
    //帮助: php index.php "command/SmcServer/manage" "command=status"
    public function manage()
    {
        try {
            //命令，默认启动
            $command = isset($this->body['command']) ? $this->body['command'] : 'start';
            //是否以守护进程执行，默认是
            $daemon = isset($this->body['daemon']) ? $this->body['daemon'] : true;
            $app = new App($this->globalConfig);
            $app->run($command, $daemon);
        } catch (Throwable $e) {
            $error = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
            var_dump('Error: ' . $error);
            Smc::$logger->log('Error: ' . $error, Logger::LEVEL_ERROR);
        }
    }

    //队列回调，应用程序统一命令行执行方法
    public function run($params)
    {
        try {
            $callbackLogPath = APP_ROOT . '/logs/callback';
            if (!is_dir($callbackLogPath)) {
                mkdir($callbackLogPath, 0777);
            }
            file_put_contents($callbackLogPath . '/' . date('Ymd') . '.log', date('[Y-m-d H:i:s]:') . json_encode($params) . PHP_EOL, FILE_APPEND);
            $callbackUrl = isset($params['callbackUrl']) ? trim($params['callbackUrl']) : '';
            $data = isset($params['data']) ? $params['data'] : '';
            if (empty($callbackUrl)) {
                throw new Exception('invalid callbackUrl:' . $callbackUrl);
            }
            if ($callbackUrl === 'testCallbackUrl') {
                //测试队列回调
                return $callbackUrl;
            }
            if (strpos($callbackUrl, 'http') !== false) {
                //直接填的http请求方法post
                $header = ['Content-Type: application/x-www-form-urlencoded; charset=utf-8'];
                curlPost($callbackUrl, $data, 5, $header);
            } else {
                //配置的是本系统内的回调，回调数据需是一维数组
                //php index.php "command/SmcServer/manage" "command=status"
                $command = '/usr/bin/php index.php ' . $callbackUrl . ' ' . http_build_query(json_decode($data, true));
                exec($command);
            }
        } catch (Throwable $e) {
            $error = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
            var_dump('Error: ' . $error);
            Smc::$logger->log('Error: ' . $error, Logger::LEVEL_ERROR);
        }
    }

    //热加载队列配置回调，队列使用direct模式
    public function loadQueueConfig()
    {
        //改用redis存储
        $redis = $this->getRedis();
        $defaultQueueConfig = $this->queueConfig;
        $queueConfig = $redis->get(self::QUEUE_CONFIG);
        if (!empty($queueConfig)) {
            $defaultQueueConfig['queues'] = array_merge($defaultQueueConfig['queues'], json_decode($queueConfig, true));
        }
        return $defaultQueueConfig;
    }

    public function addQueue()
    {
        $queueName = isset($this->body['queueName']) ? $this->body['queueName'] : '';
        $routeKey = isset($this->body['routeKey']) ? $this->body['routeKey'] : $queueName;
        $vHost = isset($this->body['vhost']) ? $this->body['vhost'] : '/';
        $prefetchCount = isset($this->body['prefetchCount']) ? $this->body['prefetchCount'] : 5;
        $minConsumerNum = $this->body['minConsumerNum'];
        $maxConsumerNum = $this->body['maxConsumerNum'];
        $warningNum = isset($this->body['warningNum']) ? $this->body['warningNum'] : 1000;
        $callbackUrl = $this->body['callbackUrl'];
        if (empty($queueName) || empty($routeKey) || empty($vHost) || empty($prefetchCount) || empty($minConsumerNum)
            || empty($maxConsumerNum) || empty($warningNum) || empty($callbackUrl)) {
            return ResponseUtil::getOutputArrayByCodeAndMessage(Api::SUCCESS, 'miss parameter.');
        }
        $queueConfig = $this->getRedis()->get(self::QUEUE_CONFIG);
        $queueConfigArr = empty($queueConfig) ? [] : json_decode($queueConfig, true);
        if (isset($queueConfigArr[$queueName])) {
            return ResponseUtil::getOutputArrayByCodeAndMessage(Api::PARAM_ERROR, 'add fail, queue exist:' . $queueName);
        }
        $tpl[$queueName] = [
            'queueName' => $queueName, //队列名称
            'routeKey' => $routeKey, //路由key
            'vhost' => $vHost, //队列所在的vhost
            'prefetchCount' => $prefetchCount, //默认为10，不需要的话去掉该选项或设置为null
            'minConsumerNum' => $minConsumerNum,  //最小消费者数量
            'maxConsumerNum' => $maxConsumerNum,  //最大消费者数量，系统限制最大20
            'warningNum' => $warningNum, //达到预警的消息数量，请合理设置，建议不少于1000
            //本框架直接写模块路由，如果是外部的请求可以填写完整http地址，系统会以http-post-json方式回调
            'callbackUrl' => $callbackUrl,
        ];
        $queueConfigArr = array_merge($queueConfigArr, $tpl);
        $this->getRedis()->set(self::QUEUE_CONFIG, json_encode($queueConfigArr));
        return ResponseUtil::getOutputArrayByCode(Api::SUCCESS);
    }

    //查看当前队列所有信息
    public function queueList()
    {
        $queueConfig = $this->getRedis()->get(self::QUEUE_CONFIG);
        $queueConfigArr = json_decode($queueConfig, true);
        return ResponseUtil::getOutputArrayByCodeAndData(Api::SUCCESS, $queueConfigArr);
    }

    //删除队列
    public function deleteQueue()
    {
        $queueName = isset($this->body['queueName']) ? $this->body['queueName'] : '';
        $queueConfig = $this->getRedis()->get(self::QUEUE_CONFIG);
        $queueConfigArr = json_decode($queueConfig, true);
        if (!isset($queueConfigArr[$queueName])) {
            return ResponseUtil::getOutputArrayByCodeAndMessage(Api::RECORD_NOT_EXISTS, 'queue not exist');
        }
        unset($queueConfigArr[$queueName]);
        $this->getRedis()->set(self::QUEUE_CONFIG, json_encode($queueConfigArr));
        return ResponseUtil::getOutputArrayByCode(Api::SUCCESS);
    }

    private function getRedis()
    {
        if ($this->redis === null) {
            $config = $this->globalConfig['redis'];
            $this->redis = new \Redis();
            if (empty($config['host'])) {
                throw new \Exception('Redis host can not empty');
            }
            $this->redis->connect($config['host'], $config['port'], $config['timeout']);
            if (isset($config['password']) && $config['password']) {
                $this->redis->auth($config['password']);
            }
            $this->redis->select($config['database']);
        }
        return $this->redis;
    }

}