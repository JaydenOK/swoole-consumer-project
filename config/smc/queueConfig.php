<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

return [
    //消息服务连接配置
    'connection' => [
        'host'            => '127.0.0.1',
        'user'            => 'test',
        'pass'            => 'test',
        'port'            => '5672',
        'vhost'           => '/', //default vhost
        'exchange'        => 'queue_exchange',
        'timeout'         => 3,
    ],
    'queues' => [
        'queue_name' => [
            'queueName'      => 'goods', //队列名称
            'routeKey'       => 'goods_route', //路由key
            'vhost'          => '/', //队列所在的vhost
            'prefetchCount'  => 10, //默认为10，不需要的话去掉该选项或设置为null
            'minConsumerNum' => 2,  //最小消费者数量
            'maxConsumerNum' => 3,  //最大消费者数量，系统限制最大20
            'warningNum'     => 10000, //达到预警的消息数量，请合理设置，建议不少于1000
			'callback'       => ['hello', 't'], //程序执行job，[command,action]
        ],
        'queue_name1' => [
            'queueName'      => 'queue_name1',
            'routeKey'       => 'queue_routekey',
            'vhost'          => '/',
            'prefetchCount'  => 10,
            'minConsumerNum' => 1,
            'maxConsumerNum' => 3,
            'warningNum'     => 10000,
			'callback'       => ['hello', 't'], //程序执行job，[command,action]
        ],
    ],
];
