<?php
/**
 * Redis连接池类
 */
class RedisPool
{
    private static $instance;
    private $config = array();
    private $pool = array();
    private $max_connections = 10;
    private $current_connections = 0;

    /**
     * 私有构造方法，防止直接实例化
     */
    private function __construct() {}

    /**
     * 获取单例实例
     * @return RedisPool
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 初始化配置
     * @param array $config
     * @return RedisPool
     */
    public function init($config = array())
    {
        $this->config = $config;
        $this->max_connections = isset($config['max_connections']) ? $config['max_connections'] : 10;
        return $this;
    }

    /**
     * 检查Redis连接是否有效
     * @param Redis $redis
     * @return bool
     */
    private function isConnectionValid($redis)
    {
        try {
            // 设置ping命令的超时时间，避免长时间阻塞
            $redis->setOption(Redis::OPT_READ_TIMEOUT, 1);
            // 使用ping命令检查连接是否有效
            $result = $redis->ping();
            // 恢复默认超时设置
            $redis->setOption(Redis::OPT_READ_TIMEOUT, -1);
            return $result === '+PONG' || $result === true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取Redis连接
     * @return Redis|null
     */
    public function getConnection()
    {
        // 尝试从连接池中获取可用连接
        while (!empty($this->pool)) {
            $redis = array_pop($this->pool);
            // 检查连接是否有效
            if ($this->isConnectionValid($redis)) {
                return $redis;
            } else {
                // 连接无效，减少连接计数
                $this->current_connections--;
                error_log('Removed invalid Redis connection from pool');
            }
        }

        // 检查是否达到最大连接数
        if ($this->current_connections >= $this->max_connections) {
            error_log('Redis connection pool exhausted. Current: ' . $this->current_connections . ', Max: ' . $this->max_connections);
            return null;
        }

        // 连接重试机制
        $retryCount = 0;
        $maxRetries = 3; // 最大重试次数
        $retryDelay = 100000; // 重试间隔，单位微秒（100毫秒）

        while ($retryCount < $maxRetries) {
            try {
                $redis = new Redis();
                // 设置连接超时，避免长时间阻塞
                $timeout = $this->config['timeout'] ?? 2;
                
                // 检查是否使用持久连接
                $persistent = $this->config['persistent'] ?? false;
                $persistent_id = $this->config['persistent_id'] ?? 'dht_spider_redis';
                
                if ($persistent) {
                    // 使用持久连接
                    $redis->pconnect($this->config['host'], $this->config['port'], $timeout, $persistent_id);
                } else {
                    // 使用普通连接
                    $redis->connect($this->config['host'], $this->config['port'], $timeout);
                }
                
                if (!empty($this->config['password'])) {
                    $redis->auth($this->config['password']);
                }
                
                if (!empty($this->config['database'])) {
                    $redis->select($this->config['database']);
                }
                
                $this->current_connections++;
                return $redis;
            } catch (Exception $e) {
                $retryCount++;
                if ($retryCount >= $maxRetries) {
                    error_log('Redis connection error after ' . $maxRetries . ' retries: ' . $e->getMessage());
                    return null;
                }
                // 短暂等待后重试
                error_log('Redis connection attempt ' . $retryCount . ' failed, retrying in ' . ($retryDelay / 1000) . 'ms: ' . $e->getMessage());
                usleep($retryDelay);
            }
        }
        
        return null;
    }

    /**
     * 归还Redis连接到连接池
     * @param Redis $redis
     */
    public function returnConnection($redis)
    {
        if ($redis instanceof Redis) {
            // 只在获取连接时检查有效性，归还时不检查，减少ping命令调用
            $this->pool[] = $redis;
        }
    }

    /**
     * 关闭所有连接
     */
    public function closeAll()
    {
        foreach ($this->pool as $redis) {
            if ($redis instanceof Redis) {
                // 检查是否使用持久连接，持久连接不调用close
                $persistent = $this->config['persistent'] ?? false;
                if (!$persistent) {
                    $redis->close();
                }
            }
        }
        $this->pool = array();
        $this->current_connections = 0;
    }

    /**
     * 检查infohash是否存在
     * @param string|binary $infohash 二进制或十六进制字符串格式的infohash
     * @return bool
     */
    public function exists($infohash)
    {
        $retryCount = 0;
        $maxRetries = 2;
        
        while ($retryCount < $maxRetries) {
            // 获取Redis连接
            $redis = $this->getConnection();
            if (!$redis) {
                error_log('Redis connection failed in exists() method');
                $retryCount++;
                sleep(1);
                continue;
            }

            try {
                // 确定infohash格式并转换为二进制
                $infohash_bin = $this->normalizeInfohash($infohash);
                
                // 使用统一的Set来存储所有infohash
                $set_key = $this->config['prefix'] . 'infohashes';
                
                // 检查infohash是否存在于Set中
                $result = $redis->sIsMember($set_key, $infohash_bin);
                
                // 归还连接
                $this->returnConnection($redis);
                
                // 确保返回bool类型
                return (bool)$result;
            } catch (Exception $e) {
                error_log('Redis exists error: ' . $e->getMessage());
                // 连接出错，不再归还到连接池
                $this->current_connections--;
                $retryCount++;
                // 短暂等待后重试
                if ($retryCount < $maxRetries) {
                    error_log('Retrying Redis exists operation...');
                    sleep(1);
                }
            }
        }
        
        return false;
    }

    /**
     * 设置infohash
     * @param string $infohash 二进制或十六进制字符串格式的infohash
     * @param int $expire
     * @return bool
     */
    public function set($infohash, $expire = 86400)
    {
        $retryCount = 0;
        $maxRetries = 2;
        
        while ($retryCount < $maxRetries) {
            $redis = $this->getConnection();
            if (!$redis) {
                $retryCount++;
                sleep(1);
                continue;
            }

            try {
                // 确定infohash格式并转换为二进制
                $infohash_bin = $this->normalizeInfohash($infohash);
                
                // 使用统一的Set来存储所有infohash
                $set_key = $this->config['prefix'] . 'infohashes';
                
                // 将infohash添加到Set中
                $result = $redis->sAdd($set_key, $infohash_bin);
                
                // 设置Set的过期时间（只在添加成功时设置，避免重复操作）
                if ($result) {
                    $redis->expire($set_key, $expire);
                }
                
                $this->returnConnection($redis);
                return $result;
            } catch (Exception $e) {
                error_log('Redis set error: ' . $e->getMessage());
                // 连接出错，不再归还到连接池
                $this->current_connections--;
                $retryCount++;
                // 短暂等待后重试
                if ($retryCount < $maxRetries) {
                    error_log('Retrying Redis set operation...');
                    sleep(1);
                }
            }
        }
        
        return false;
    }
    
    /**
     * 将infohash转换为标准二进制格式
     * @param string|binary $infohash 二进制或十六进制字符串格式的infohash
     * @return string 二进制格式的infohash
     */
    private function normalizeInfohash($infohash)
    {
        // 如果是十六进制字符串（40个字符），转换为二进制
        if (strlen($infohash) === 40 && ctype_xdigit($infohash)) {
            return hex2bin($infohash);
        }
        // 如果已经是二进制（20字节），直接返回
        if (strlen($infohash) === 20) {
            return $infohash;
        }
        // 其他情况，尝试转换
        return hex2bin(strtoupper(bin2hex($infohash)));
    }
}
