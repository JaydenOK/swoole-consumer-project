<?php

namespace app\callback;

use app\core\lib\controller\Controller;
use app\language\Api;
use app\module\utils\ResponseUtil;
use app\service\OrderService;

class Order extends Controller
{

    /**
     * @var OrderService
     */
    private $service;

    public function init()
    {
        //todo 替代构造方法
        $this->service = new OrderService();
    }

    /**
     * lists
     * @return array
     */
    public function lists()
    {
        $data = $this->service->getOrderLists($this->body);
        return ResponseUtil::getOutputArrayByCodeAndData(Api::SUCCESS, $data);
    }


}