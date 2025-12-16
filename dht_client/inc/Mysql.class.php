<?php
/**
 * MySQL连接池类
 */
class MysqlPool
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
     * @return MysqlPool
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
     * @return MysqlPool
     */
    public function init($config = array())
    {
        $this->config = $config;
        $this->max_connections = isset($config['max_connections']) ? $config['max_connections'] : 10;
        return $this;
    }
    
    /**
     * 获取MySQL连接
     * @return mysqli|null
     */
    public function getConnection()
    {
        // 尝试从连接池中获取可用连接
        if (!empty($this->pool)) {
            $conn = array_pop($this->pool);
            // 检查连接是否有效
            if ($conn->ping()) {
                return $conn;
            } else {
                // 连接无效，关闭并减少计数
                $conn->close();
                $this->current_connections--;
            }
        }
        
        // 检查是否达到最大连接数
        if ($this->current_connections >= $this->max_connections) {
            error_log('MySQL connection pool exhausted. Current: ' . $this->current_connections . ', Max: ' . $this->max_connections);
            return null;
        }
        
        // 连接重试机制
        $retryCount = 0;
        $maxRetries = 3;
        $retryDelay = 100000; // 100毫秒
        
        while ($retryCount < $maxRetries) {
            try {
                $conn = new mysqli();
                $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, $this->config['timeout'] ?? 2);
                $conn->connect(
                    $this->config['host'],
                    $this->config['user'],
                    $this->config['password'],
                    $this->config['database'],
                    $this->config['port'] ?? 3306
                );
                
                if ($conn->connect_error) {
                    throw new Exception('MySQL connection error: ' . $conn->connect_error);
                }
                
                $conn->set_charset($this->config['charset'] ?? 'utf8mb4');
                $this->current_connections++;
                return $conn;
            } catch (Exception $e) {
                $retryCount++;
                if ($retryCount >= $maxRetries) {
                    error_log('MySQL connection error after ' . $maxRetries . ' retries: ' . $e->getMessage());
                    return null;
                }
                error_log('MySQL connection attempt ' . $retryCount . ' failed, retrying in ' . ($retryDelay / 1000) . 'ms: ' . $e->getMessage());
                usleep($retryDelay);
            }
        }
        
        return null;
    }
    
    /**
     * 归还MySQL连接到连接池
     * @param mysqli $conn
     */
    public function returnConnection($conn)
    {
        if ($conn instanceof mysqli) {
            $this->pool[] = $conn;
        }
    }
    
    /**
     * 关闭所有连接
     */
    public function closeAll()
    {
        foreach ($this->pool as $conn) {
            if ($conn instanceof mysqli) {
                $conn->close();
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
        // 获取MySQL连接
        $conn = $this->getConnection();
        if (!$conn) {
            error_log('MySQL connection failed in exists() method');
            return false;
        }
        
        try {
            // 将二进制infohash转换为大写十六进制字符串
            $infohash_hex = strtoupper(bin2hex($infohash));
            
            // 生成完整表名
            $tableName = $this->config['prefix'] . $this->config['table_name'];
            
            // 准备查询语句
            $stmt = $conn->prepare("SELECT 1 FROM `{$tableName}` WHERE `infohash` = ? LIMIT 1");
            if (!$stmt) {
                throw new Exception('MySQL prepare error: ' . $conn->error);
            }
            
            $stmt->bind_param('s', $infohash_hex);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // 检查结果
            $exists = $result->num_rows > 0;
            
            // 释放资源
            $stmt->close();
            $result->close();
            
            // 归还连接
            $this->returnConnection($conn);
            
            return $exists;
        } catch (Exception $e) {
            error_log('MySQL exists error: ' . $e->getMessage());
            // 归还连接
            $this->returnConnection($conn);
            return false;
        }
    }
}