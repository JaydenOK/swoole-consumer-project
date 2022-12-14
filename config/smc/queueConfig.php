<?php

return [
    //消息服务连接配置：/etc/rabbitmq/rabbitmq.conf
    //listeners.tcp.local    = 127.0.0.1:5672
    'connection' => [
        'host' => '127.0.0.1',
        'user' => 'test',
        'pass' => 'test',
        'port' => '5672',
        'vhost' => '/', //default vhost
        'exchange' => 'multi_consumer_exchange',
        'timeout' => 3,
    ],
    'queues' => [
        //已改为redis存储，启动前需要保证在redis有相关配置存在
        //一个队列对应一个回调地址callback
        //callback可改造成http请求地址
        //需手动创建交换机
//        'default_multi_consumer' => [
//            'queueName' => 'default_multi_consumer', //队列名称
//            'routeKey' => 'default_multi_consumer', //路由key
//            'vhost' => '/', //队列所在的vhost
//            'prefetchCount' => 5, //默认为10，不需要的话去掉该选项或设置为null
//            'minConsumerNum' => 1,  //最小消费者数量
//            'maxConsumerNum' => 10,  //最大消费者数量，系统限制最大20
//            'warningNum' => 1000, //达到预警的消息数量，请合理设置，建议不少于1000
//            //本框架直接写模块路由，如果是外部的请求可以填写完整http地址，系统会以http-post-json方式回调
//            'callbackUrl' => 'testCallbackUrl',
//        ],
    ],
];
