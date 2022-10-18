<?php

namespace app\service;

use Pupilcp\App;
use Pupilcp\Log\Logger;
use Pupilcp\Smc;
use Throwable;

class SmcService
{

    const QUEUE_CONFIG = 'queue.config';
    const STATUS_STOP = 0;  //停止
    const STATUS_START = 1;   //启动中

    /**
     * @var array
     */
    private $globalConfig = [];
    /**
     * 包含connection信息
     * @var array
     */
    private $queueConfig = [];
    private $redis;

    public function __construct()
    {
    }

    private function getGlobalConfig()
    {
        if (empty($this->globalConfig)) {
            if (file_exists(APP_ROOT . '/config/smc/globalConfig.php')) {
                $this->globalConfig = include APP_ROOT . '/config/smc/globalConfig.php';
            }
        }
        return $this->globalConfig;
    }

    private function getDefaultQueueConfig()
    {
        if (file_exists(APP_ROOT . '/config/smc/queueConfig.php')) {
            $this->queueConfig = include APP_ROOT . '/config/smc/queueConfig.php';
        }
        return $this->queueConfig;
    }

    //smc管理
    public function manage($requestBody)
    {
        try {
            //命令，默认启动
            $command = isset($requestBody['command']) ? $requestBody['command'] : 'start';
            //是否以守护进程执行，默认是
            $daemon = isset($requestBody['daemon']) ? $requestBody['daemon'] : true;
            $this->getGlobalConfig();
            $app = new App($this->globalConfig);
            $app->run($command, $daemon);
            $message = 'done';
        } catch (Throwable $e) {
            $message = $e->getMessage() . PHP_EOL;
            Smc::$logger->log('Error: ' . $message, Logger::LEVEL_ERROR);
        }
        return $message;
    }

    /**
     * @param $callbackUrl string
     * @param $data string
     */
    public function run($callbackUrl, $data)
    {
        if (strpos($callbackUrl, 'http') !== false) {
            //直接填的http请求方法post
            $header = ['Content-Type: application/json; charset=utf-8'];
            curlPost($callbackUrl, $data, 5, $header);
        } else {
            //配置的是本系统内的回调，回调数据需是一维数组
            //php index.php "command/SmcServer/manage" "command=status"
            $dataArr = @json_decode($data, true);
            //回调数据兼容非json串
            if (json_last_error() !== JSON_ERROR_NONE) {
                $dataArr = [0 => $data];
            }
            $command = '/usr/bin/php index.php ' . $callbackUrl . ' ' . http_build_query($dataArr);
            exec($command);
        }
    }

    /**
     * 启动后的队列配置获取，热加载队列配置回调，队列使用direct模式
     * @param string $type
     * @return array
     */
    public function loadQueueConfig($type = '')
    {
        try {
            $config = [
                'connection' => [],
                'queues' => [],
            ];
            //改用redis存储
            $defaultQueueConfig = $this->getDefaultQueueConfig();
            $config['connection'] = isset($defaultQueueConfig['connection']) ? $defaultQueueConfig['connection'] : [];
            //启动之后，不在使用配文件默认队列信息
            $redis = $this->getRedis();
            $queuesJson = $redis->get(self::QUEUE_CONFIG);
            if (!empty($queuesJson)) {
                $redisQueues = json_decode($queuesJson, true);
                //队列信息没变，不用更新，改变则合并返回
                ksort($redisQueues);
                foreach ($redisQueues as $name => $item) {
                    if ($item['status'] == self::STATUS_STOP) {
                        //过滤未启用的队列
                        unset($redisQueues[$name]);
                    }
                }
//                if (json_encode($redisQueues) !== $queuesJson) {
//                    $redis->set(self::QUEUE_CONFIG, json_encode($redisQueues));
//                }
                $config['queues'] = $redisQueues;
            }
            return $config;
        } catch (\Exception $e) {
            Smc::$logger->log('loadQueueConfig Error: ' . $e->getMessage(), Logger::LEVEL_ERROR);
            return [];
        }
    }

    public function addQueue($requestBody)
    {
        $queueName = isset($requestBody['queueName']) ? $requestBody['queueName'] : '';
        $routeKey = isset($requestBody['routeKey']) ? $requestBody['routeKey'] : $queueName;
        $vHost = isset($requestBody['vhost']) ? $requestBody['vhost'] : '/';
        $prefetchCount = isset($requestBody['prefetchCount']) ? $requestBody['prefetchCount'] : 5;
        $minConsumerNum = $requestBody['minConsumerNum'];
        $maxConsumerNum = $requestBody['maxConsumerNum'];
        $warningNum = isset($requestBody['warningNum']) ? $requestBody['warningNum'] : 1000;
        $callbackUrl = $requestBody['callbackUrl'];
        if (empty($queueName) || empty($routeKey) || empty($vHost) || empty($prefetchCount) || empty($minConsumerNum)
            || empty($maxConsumerNum) || empty($warningNum) || empty($callbackUrl)) {
            return 'miss parameter';
        }
        $queuesJson = $this->getRedis()->get(self::QUEUE_CONFIG);
        $queuesArr = empty($queuesJson) ? [] : json_decode($queuesJson, true);
        if (isset($queuesArr[$queueName])) {
            return 'add fail, queue exist:' . $queueName;
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
            'status' => self::STATUS_STOP,
        ];
        $queuesArr = array_merge($queuesArr, $tpl);
        ksort($queuesArr);
        $this->getRedis()->set(self::QUEUE_CONFIG, json_encode($queuesArr));
        return 'success';
    }

    //查看当前队列所有信息
    public function queueList()
    {
        $queuesJson = $this->getRedis()->get(self::QUEUE_CONFIG);
        $queuesArr = json_decode($queuesJson, true);
        return $queuesArr;
    }

    //删除队列
    public function deleteQueue($requestBody)
    {
        $queueName = isset($requestBody['queueName']) ? $requestBody['queueName'] : '';
        $queuesJson = $this->getRedis()->get(self::QUEUE_CONFIG);
        $queuesArr = json_decode($queuesJson, true);
        if (!isset($queuesArr[$queueName])) {
            return 'queue not exist';
        }
        unset($queuesArr[$queueName]);
        ksort($queuesArr);
        $this->getRedis()->set(self::QUEUE_CONFIG, json_encode($queuesArr));
        return 'success';
    }

    //启动
    public function start($requestBody)
    {
        $queueName = isset($requestBody['queueName']) ? $requestBody['queueName'] : '';
        $queuesJson = $this->getRedis()->get(self::QUEUE_CONFIG);
        $queuesArr = json_decode($queuesJson, true);
        if (!isset($queuesArr[$queueName])) {
            return 'queue not exist';
        }
        $queuesArr[$queueName]['status'] = self::STATUS_START;
        ksort($queuesArr);
        $this->getRedis()->set(self::QUEUE_CONFIG, json_encode($queuesArr));
        return 'success';
    }

    //停止
    public function stop($requestBody)
    {
        $queueName = isset($requestBody['queueName']) ? $requestBody['queueName'] : '';
        $queuesJson = $this->getRedis()->get(self::QUEUE_CONFIG);
        $queuesArr = json_decode($queuesJson, true);
        if (!isset($queuesArr[$queueName])) {
            return 'queue not exist';
        }
        $queuesArr[$queueName]['status'] = self::STATUS_STOP;
        ksort($queuesArr);
        $this->getRedis()->set(self::QUEUE_CONFIG, json_encode($queuesArr));
        return 'success';
    }

    private function getRedis()
    {
        if ($this->redis === null) {
            $this->getGlobalConfig();
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