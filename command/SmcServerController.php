<?php

namespace app\command;

use app\core\lib\Config;
use app\core\lib\controller\Controller;

/**
 * 服务管理中心
 * Class SmcServerController
 * @package app\command
 */
class SmcServerController extends Controller
{
    /**
     * @var array
     */
    private $globalConfig;
    /**
     * @var array
     */
    private $queueConfig;

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
            $app = new \Pupilcp\App($this->globalConfig);
            $app->run($command, $daemon);
        } catch (\Throwable $e) {
            $error = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
            var_dump('Error: ' . $error);
            \Pupilcp\Smc::$logger->log('Error: ' . $error, \Pupilcp\Log\Logger::LEVEL_ERROR);
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
                throw new \Exception('invalid callbackUrl:' . $callbackUrl);
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
        } catch (\Throwable $e) {
            $error = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
            var_dump('Error: ' . $error);
            \Pupilcp\Smc::$logger->log('Error: ' . $error, \Pupilcp\Log\Logger::LEVEL_ERROR);
        }
    }


}