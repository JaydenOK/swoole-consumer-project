<?php

namespace app\callback;

use app\core\lib\controller\Controller;
use app\language\Api;
use app\module\utils\ResponseUtil;

class Message extends Controller
{

    public function init()
    {
        //todo 替代构造方法
    }

    /**
     * 回调处理逻辑，如发送通知消息
     * @return array
     */
    public function send()
    {
        //队列回调数据
        $message = isset($this->body['message']) ? $this->body['message'] : '';
        $messageArr = json_decode($message, true);
        $data = ['time' => date('Y-m-d H:i:s'), 'message' => $messageArr];
        return ResponseUtil::getOutputArrayByCodeAndData(Api::SUCCESS, $data);
    }

    /**
     * 发送验证码
     * @return array
     */
    public function sendCode()
    {
        //队列回调数据
        $message = isset($this->body['message']) ? $this->body['message'] : '';
        $messageArr = json_decode($message, true);
        $data = ['time' => date('Y-m-d H:i:s'), 'message' => $messageArr];
        return ResponseUtil::getOutputArrayByCodeAndData(Api::SUCCESS, $data);
    }

}