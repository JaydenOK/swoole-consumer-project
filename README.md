#### swoole-rabbitmq 多消费者异步队列服务
可作为独立队列回调服务，向rabbitmq队列发送消息，配置callback后，异步回调到callback地址，用于业务解耦。  
或集成到自己系统模块下，callback配置对应的 模块/控制器/方法，命令行执行回调。  

```shell script
启动: php index.php "command/SmcServer/manage" "command=start"  
通知: php index.php "command/SmcServer/manage" "command=stop"  
重启: php index.php "command/SmcServer/manage" "command=restart"  
查看状态: php index.php "command/SmcServer/manage" "command=status"
```
  
配置回调地址  : config/smc/globalConfig.php
```php
[
    //一个队列对应一个回调地址callback
    //callback可改造成http请求地址
    //需手动创建交换机，队列名，设置路由
    'email-message' => [
        'queueName' => 'email-message', //队列名称
        'routeKey' => 'email-message', //路由key
        'vhost' => '/', //队列所在的vhost
        'prefetchCount' => 5, //默认为10，不需要的话去掉该选项或设置为null
        'minConsumerNum' => 3,  //最小消费者数量
        'maxConsumerNum' => 10,  //最大消费者数量，系统限制最大20
        'warningNum' => 30, //达到预警的消息数量，请合理设置，建议不少于1000
        //本框架直接写模块路由，如果是外部的请求可以填写完整http地址，系统会以http-post-json方式回调
        'callbackUrl' => 'callback/Message/send',
    ],
]
```


