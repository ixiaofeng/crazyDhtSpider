<?php

class DbPool
{
    private static $instance = null;
    private $pool = []; // 连接池数组
    private $maxConnections = 200; // 最大连接数，与MySQL最大连接数一致
    private $connectionsCount = 0; // 当前连接数
    
    // 私有构造函数，防止外部实例化
    private function __construct() {}
    
    // 获取单例实例
    public static function getInstance(): DbPool
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // 创建新的数据库连接
    private function createConnection(): PDO
    {
        global $config;
        
        $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4";
        $options = [
            PDO::ATTR_PERSISTENT => false, // 关闭持久连接以避免状态共享问题
            PDO::ATTR_TIMEOUT => 10,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true // 启用缓冲查询
        ];
        
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);
        $this->connectionsCount++;
        return $pdo;
    }
    
    // 从连接池获取连接
    private function getConnection(): PDO
    {
        // 优先从连接池中获取可用连接
        foreach ($this->pool as $key => $connection) {
            try {
                // 检查连接是否有效
                $connection->query('SELECT 1');
                unset($this->pool[$key]);
                return $connection;
            } catch (Exception $e) {
                // 连接无效，移除
                unset($this->pool[$key]);
                $this->connectionsCount--;
            }
        }
        
        // 连接池为空且未达到最大连接数，创建新连接
        if ($this->connectionsCount < $this->maxConnections) {
            return $this->createConnection();
        }
        
        // 等待一小段时间后再次尝试获取连接
        usleep(1000);
        return $this->getConnection();
    }
    
    // 将连接返回连接池
    private function releaseConnection(PDO $pdo): void
    {
        $this->pool[] = $pdo;
    }
    
    // 执行数据库查询
    public static function sourceQuery($rs, $bt_data): void
    {
        $instance = self::getInstance();
        $pdo = $instance->getConnection();
        $stmt = null;
        
        try {
            // 开启事务
            $pdo->beginTransaction();
            
            // 检查是否已存在
            $stmt = $pdo->prepare("SELECT infohash FROM history WHERE infohash = ?");
            $stmt->execute([$rs['infohash']]);
            $exists = $stmt->fetchColumn() !== false;
            $stmt->closeCursor();
            $stmt = null;
            
            if ($exists) {
                // 更新已有记录
                $stmt = $pdo->prepare("UPDATE bt SET hot = hot + 1, lasttime = NOW() WHERE infohash = ?");
                $stmt->execute([$rs['infohash']]);
                $stmt->closeCursor();
                $stmt = null;
            } else {
                // 插入新记录到history表
                $stmt = $pdo->prepare("INSERT INTO history (infohash) VALUES (?)");
                $stmt->execute([$rs['infohash']]);
                $stmt->closeCursor();
                $stmt = null;
                
                // 准备bt表的插入语句，将time和lasttime设置为NOW()
                $columns = array_keys($bt_data);
                $placeholders = array_fill(0, count($columns), '?');
                
                // 确保time和lasttime列存在且使用NOW()
                $timeColumns = ['time', 'lasttime'];
                foreach ($timeColumns as $column) {
                    if (in_array($column, $columns)) {
                        // 移除原来的time/lasttime列
                        $index = array_search($column, $columns);
                        unset($columns[$index], $bt_data[$column]);
                        
                        // 重新添加到数组末尾
                        $columns[] = $column;
                        $bt_data[$column] = 'NOW()';
                    } else {
                        // 如果不存在，添加到数组
                        $columns[] = $column;
                        $bt_data[$column] = 'NOW()';
                    }
                }
                
                // 构建最终的SQL语句
                $columns_str = implode(', ', $columns);
                $values_str = '';
                $execute_values = [];
                
                foreach ($bt_data as $value) {
                    if ($value === 'NOW()') {
                        $values_str .= 'NOW(), ';
                    } else {
                        $values_str .= '?, ';
                        $execute_values[] = $value;
                    }
                }
                
                // 移除末尾的逗号和空格
                $values_str = rtrim($values_str, ', ');
                
                $sql = "INSERT INTO bt ({$columns_str}) VALUES ({$values_str})";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($execute_values);
                $stmt->closeCursor();
                $stmt = null;
            }
            
            // 提交事务
            $pdo->commit();
            
            // 查询完成后将连接返回连接池
            $instance->releaseConnection($pdo);
        } catch (Exception $e) {
            // 回滚事务
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // 关闭打开的语句
            if ($stmt !== null) {
                try {
                    $stmt->closeCursor();
                } catch (Exception $e2) {
                    // 忽略关闭游标时的异常
                }
            }
            
            // 异常情况下，损坏的连接不返回连接池，仅减少计数
            // PDO连接会在对象销毁时自动关闭，无需手动调用close()方法
            $instance->connectionsCount--;
            
            // 重新抛出异常
            throw $e;
        }
    }
    
    // 设置最大连接数
    public static function setMaxConnections(int $max): void
    {
        $instance = self::getInstance();
        $instance->maxConnections = $max;
    }
    
    // 手动释放所有连接
    public static function close(): void
    {
        $instance = self::getInstance();
        $instance->pool = [];
        $instance->connectionsCount = 0;
    }
}