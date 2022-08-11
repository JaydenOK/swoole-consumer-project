<?php

namespace app\command;

use app\core\lib\controller\Controller;
use app\language\Api;
use app\module\utils\ResponseUtil;
use app\service\SmcService;
use Exception;
use Throwable;

/**
 * 服务管理中心
 * Class SmcServerController
 * @package app\command
 */
class SmcServerController extends Controller
{

    /**
     * @var SmcService
     */
    private $service;

    protected function init()
    {
        $this->service = new SmcService();
    }

    //启动: php index.php "command/SmcServer/manage" "command=start"
    //停止: php index.php "command/SmcServer/manage" "command=stop"
    //重启: php index.php "command/SmcServer/manage" "command=restart"
    //查看状态: php index.php "command/SmcServer/manage" "command=status"
    //帮助: php index.php "command/SmcServer/manage" "command=status"
    public function manage()
    {
        $message = $this->service->manage($this->body);
        return ResponseUtil::getOutputArrayByCodeAndMessage(Api::SUCCESS, $message);
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
            $this->service->run($callbackUrl, $data);
        } catch (Throwable $e) {
            $error = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
            print_r('Error: ' . $error);
        }
    }

    /**
     * 热加载队列配置回调，队列使用direct模式
     * @return array
     * @throws Exception
     */
    public static function loadQueueConfig()
    {
        return (new SmcService())->loadQueueConfig();
    }

    //查看当前存在的队列: php index.php "command/SmcServer/queueList"
    public function queueList()
    {
        $queueConfigArr = $this->service->queueList();
        return ResponseUtil::getOutputArrayByCodeAndData(Api::SUCCESS, $queueConfigArr);
    }

    //修改配置60s生效
    //增加队列(手动后台增加mq队列):  php index.php "command/SmcServer/addQueue" "queueName=send_email&minConsumerNum=3&maxConsumerNum=10&callbackUrl=callback/Message/send"
    //增加队列(手动后台增加mq队列):  php index.php "command/SmcServer/addQueue" "queueName=send_code&minConsumerNum=5&maxConsumerNum=10&callbackUrl=callback/Message/sendCode"
    public function addQueue()
    {
        $message = $this->service->addQueue($this->body);
        return ResponseUtil::getOutputArrayByCodeAndMessage(Api::SUCCESS, $message);
    }

    //删除队列: php index.php "command/SmcServer/deleteQueue" "queueName=send_email"
    public function deleteQueue()
    {
        $message = $this->service->deleteQueue($this->body);
        return ResponseUtil::getOutputArrayByCodeAndMessage(Api::SUCCESS, $message);
    }

}