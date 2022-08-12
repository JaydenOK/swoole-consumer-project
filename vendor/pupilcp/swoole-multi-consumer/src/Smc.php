<?php

/*
 * @project swoole-multi-consumer
 * @author pupilcp
 * @site https://github.com/pupilcp
 */

namespace Pupilcp;

use Pupilcp\Driver\AmqpDriver;
use Pupilcp\Driver\RedisDriver;
use Pupilcp\Library\RedisLib;
use Pupilcp\Log\Logger;

class Smc
{
    public static $logger = null;
    public static $redis = null;
    private static $globalConfig = null;

    private static $prefixKey = 'smc_';
    //随队列配置改变
    private static $configHashKey = 'smcConfigSalt';

    public static function getRedis()
    {
        $flag = false;
        $i = 1;
        while ($i <= 3 && !$flag) {
            try {
                $redis = RedisLib::getInstance(self::getGlobalConfig()['redis'], false);
                $flag = true;

                return $redis;
            } catch (\Throwable $e) {
                self::$logger->log($e->getMessage() . $e->getTraceAsString(), Logger::LEVEL_ERROR);
            }
            usleep(100);
            $i++;
        }

        return false;
    }

    public static function getConfig()
    {
        $configJson = self::getConfigHash();
        if ($configJson) {
            return json_decode($configJson, true);
        }

        return false;
    }

    /**
     * set global amqp config
     * @param array $config
     */
    public static function setConfig($config)
    {
        self::setConfigHash(json_encode($config));
    }

    /**
     * get global amqp config
     * @return null|array
     */
    public static function getGlobalConfig()
    {
        return self::$globalConfig;
    }

    /**
     * except amqp
     * @param array $globalConfig
     */
    public static function setGlobalConfig($globalConfig)
    {
        self::$globalConfig = $globalConfig;
    }

    /**
     * 选择消费驱动.
     *
     * @param array $queueConf 队列配置
     * @param int $driverFlag 驱动标识
     *
     * @return object
     * @throws
     *
     */
    public function selectDriver($queueConf, $driverFlag)
    {
        $config = self::getConfig()['connection'];
        switch ($driverFlag) {
            case SMC_AMQP_CONSUME:
                if (isset($queueConf['vhost'])) {
                    $config['vhost'] = $queueConf['vhost'];
                }

                return new AmqpDriver($config);
            case SMC_REDIS_CONSUME:
                return new RedisDriver($config);
            default:
                throw new \Exception('Unknow Driver');
        }
    }

    /**
     * @param mixed $name
     *
     * @return int
     */
    public static function getWorkerCount($name)
    {
        $key = md5(self::$prefixKey . self::getGlobalConfig()['global']['uniqueServiceId'] . $name);

        return self::getRedis()->scard($key);
    }

    /**
     * 获取队列名$name的所有进程id
     * @param mixed $name
     *
     * @return array
     */
    public static function getWorkers($name)
    {
        $key = md5(self::$prefixKey . self::getGlobalConfig()['global']['uniqueServiceId'] . $name);

        return self::getRedis()->smembers($key);
    }

    /**
     * @param mixed $name
     * @param mixed $pid
     *
     * @return bool
     */
    public static function addWorker($name, $pid)
    {
        $key = md5(self::$prefixKey . self::getGlobalConfig()['global']['uniqueServiceId'] . $name);

        return self::getRedis()->sadd($key, $pid);
    }

    /**
     * @param mixed $name
     * @param mixed $pid
     *
     * @return mixed
     */
    public static function deleteWorker($name, $pid = null)
    {
        $redis = self::getRedis();
        $key = md5(self::$prefixKey . self::getGlobalConfig()['global']['uniqueServiceId'] . $name);
        if (null === $pid) {
            return $redis->spop($key);
        } elseif ($redis->sismember($key, $pid)) {
            return $redis->srem($key, $pid);
        }

        return false;
    }

    /**
     * 删除队列pid信息
     * @param mixed $name
     *
     * @return mixed
     */
    public static function cleanWorkers($name)
    {
        $redis = self::getRedis();
        $key = md5(self::$prefixKey . self::getGlobalConfig()['global']['uniqueServiceId'] . $name);

        return $redis->del($key);
    }

    /**
     * get global amqp config
     * @return mixed
     */
    public static function getConfigHash()
    {
        $key = md5(self::$configHashKey . self::getGlobalConfig()['global']['uniqueServiceId']);

        return self::getRedis()->get($key);
    }

    /**
     * uniqueServiceId server generate
     * @param string $val queue-json
     *
     * @return mixed
     */
    public static function setConfigHash($val)
    {
        $key = md5(self::$configHashKey . self::getGlobalConfig()['global']['uniqueServiceId']);

        return self::getRedis()->set($key, $val);
    }

    /**
     * @return mixed
     */
    public static function delConfigHash()
    {
        $key = md5(self::$configHashKey . self::getGlobalConfig()['global']['uniqueServiceId']);

        return self::getRedis()->del($key);
    }

    /**
     * @param mixed $val
     *
     * @return mixed
     */
    public static function cmpConfigHash($val)
    {
        return 0 === strcmp($val, self::getConfigHash()) ? true : false;
    }
}
