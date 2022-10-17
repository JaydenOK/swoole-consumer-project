<?php

namespace app\service;

class OrderService
{

    public function __construct()
    {
    }

    public function getOrderLists(array $body)
    {
        try {
            $where = $this->handleQueryParams($body);
            $business = Db::table('order')->where($where)->find();
            $data = ['status' => 1, 'message' => 'success', 'data' => $business];
        } catch (\Exception $e) {
            $data = ['status' => 0, 'message' => $e->getMessage()];
        }
        return $data;
    }

    private function handleQueryParams(array $body)
    {
        $where = [];
        //todo
        return $where;
    }

}