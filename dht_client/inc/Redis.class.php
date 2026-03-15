<?php

class RedisPool
{
    private static $instance;
    private $config = [];
    private $pool;
    private $max_connections = 10;
    private $current_connections = 0;
    private $ping_interval = 30;

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init($config = [])
    {
        $this->config = $config;

        if (!empty($config['max_connections'])) {
            $this->max_connections = min($config['max_connections'], 50);
        }

        // 增加Channel容量，确保能容纳所有连接
        $this->pool = new Swoole\Coroutine\Channel($this->max_connections + 10);

        return $this;
    }

    private function createConnection()
    {
        try {

            $redis = new Redis();

            $timeout = $this->config['timeout'] ?? 3;

            $redis->connect(
                $this->config['host'],
                $this->config['port'],
                $timeout
            );

            if (!empty($this->config['password'])) {
                $redis->auth($this->config['password']);
            }

            if (!empty($this->config['database'])) {
                $redis->select($this->config['database']);
            }

            // 设置连接选项
            if (defined('Redis::OPT_READ_TIMEOUT')) {
                $redis->setOption(Redis::OPT_READ_TIMEOUT, 3);
            }
            if (defined('Redis::OPT_CONNECT_TIMEOUT')) {
                $redis->setOption(Redis::OPT_CONNECT_TIMEOUT, $timeout);
            }

            $this->current_connections++;

            return [
                'redis' => $redis,
                'time' => time()
            ];

        } catch (Exception $e) {
            return null;
        }
    }

    private function isConnectionValid($conn)
    {
        $redis = $conn['redis'];
        $last_used = $conn['time'];

        if (time() - $last_used < $this->ping_interval) {
            return true;
        }

        try {

            $result = $redis->ping();

            return $result === '+PONG' || $result === true;

        } catch (Exception $e) {
            return false;
        }
    }

    public function getConnection()
    {

        if (empty($this->config['host'])) {
            return null;
        }

        // 尝试从池中获取连接
        $attempts = 0;
        $max_attempts = 5;
        
        while ($attempts < $max_attempts) {
            $attempts++;
            
            if (!$this->pool->isEmpty()) {
                $conn = $this->pool->pop(0.1); // 100ms超时
                if ($conn) {
                    if ($this->isConnectionValid($conn)) {
                        return $conn['redis'];
                    }

                    try {
                        $conn['redis']->close();
                    } catch (Exception $e) {}

                    $this->current_connections--;
                }
            }

            // 检查是否可以创建新连接
            if ($this->current_connections < $this->max_connections) {
                $conn = $this->createConnection();
                if ($conn) {
                    return $conn['redis'];
                }
            }

            // 短暂挂起，避免忙等
            Swoole\Coroutine::sleep(0.01);
        }

        return null;
    }

    public function returnConnection($redis)
    {
        if (!$redis instanceof Redis) {
            return;
        }

        $conn = [
            'redis' => $redis,
            'time' => time()
        ];

        // 使用非阻塞的push操作，如果失败则关闭连接
        if (!$this->pool->push($conn, 0.1)) {

            try {
                $redis->close();
            } catch (Exception $e) {}

            $this->current_connections--;
        }
    }

    public function execute(callable $callback)
    {

        $retry = 0;
        $max_retries = 3;

        while ($retry < $max_retries) {

            $redis = $this->getConnection();

            if (!$redis) {
                $retry++;
                Swoole\Coroutine::sleep(0.01);
                continue;
            }

            try {

                return $callback($redis);

            } catch (Exception $e) {

                try {
                    $redis->close();
                } catch (Exception $e) {}

                $this->current_connections--;
                $retry++;
                Swoole\Coroutine::sleep(0.01);
                continue;

            } finally {

                $this->returnConnection($redis);

            }
        }

        return false;
    }

    public function exists($infohash)
    {

        $result = $this->execute(function($redis) use ($infohash) {

            $infohash_bin = $this->normalizeInfohash($infohash);

            $set_key = $this->config['prefix'] . 'infohashes';

            return (bool)$redis->sIsMember($set_key, $infohash_bin);

        });

        return (bool)$result;
    }

    public function set($infohash, $expire = 86400)
    {

        return (bool)$this->execute(function($redis) use ($infohash, $expire) {

            $infohash_bin = $this->normalizeInfohash($infohash);

            $set_key = $this->config['prefix'] . 'infohashes';

            if ($redis->sIsMember($set_key, $infohash_bin)) {
                return false;
            }

            $result = $redis->sAdd($set_key, $infohash_bin);

            if ($result) {
                $redis->expire($set_key, $expire);
            }

            return $result;

        });
    }

    private function normalizeInfohash($infohash)
    {

        if (strlen($infohash) === 40 && ctype_xdigit($infohash)) {
            return hex2bin($infohash);
        }

        if (strlen($infohash) === 20) {
            return $infohash;
        }

        return hex2bin(strtoupper(bin2hex($infohash)));
    }

    public function closeAll()
    {

        while (!$this->pool->isEmpty()) {

            $conn = $this->pool->pop(0.1);
            if ($conn) {
                try {
                    $conn['redis']->close();
                } catch (Exception $e) {}
            }
        }

        $this->current_connections = 0;
    }
}
