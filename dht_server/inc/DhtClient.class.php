<?php

use Medoo\Medoo;

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
    private function createConnection(): Medoo
    {
        global $database_config;
        $medoo = new Medoo([
            'database_type' => 'mysql',
            'database_name' => $database_config['db']['name'],
            'server' => $database_config['db']['host'],
            'username' => $database_config['db']['user'],
            'password' => $database_config['db']['pass'],
            'charset' => 'utf8mb4',
            'option' => [
                PDO::ATTR_PERSISTENT => false, // 关闭持久连接以避免状态共享问题
                PDO::ATTR_TIMEOUT => 10,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true // 启用缓冲查询
            ]
        ]);
        $this->connectionsCount++;
        return $medoo;
    }
    
    // 从连接池获取连接
    private function getConnection(): Medoo
    {
        // 优先从连接池中获取可用连接
        foreach ($this->pool as $key => $connection) {
            try {
                // 检查连接是否有效
                $connection->pdo->query('SELECT 1');
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
    private function releaseConnection(Medoo $medoo): void
    {
        $this->pool[] = $medoo;
    }
    
    // 执行数据库查询
    public static function sourceQuery($rs, $bt_data): void
    {
        $instance = self::getInstance();
        $medoo = $instance->getConnection();
        
        try {
            // 直接使用PDO执行查询，确保结果被完全获取
            $pdo = $medoo->pdo;
            
            // 检查是否已存在
            $stmt = $pdo->prepare("SELECT infohash FROM history WHERE infohash = ?");
            $stmt->execute([$rs['infohash']]);
            // 确保获取所有结果
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // 显式关闭语句
            $stmt->closeCursor();
            
            if (!empty($data)) {
                $stmt = $pdo->prepare("UPDATE bt SET hot = hot + 1, lasttime = NOW() WHERE infohash = ?");
                $stmt->execute([$rs['infohash']]);
                $stmt->closeCursor();
            } else {
                $stmt = $pdo->prepare("INSERT INTO history (infohash) VALUES (?)");
                $stmt->execute([$rs['infohash']]);
                $stmt->closeCursor();
                
                // 构建insert语句，将time和lasttime替换为NOW()
                $bt_data['time'] = 'NOW()';
                $bt_data['lasttime'] = 'NOW()';
                
                // 分离需要占位符的列和直接使用函数的列
                $placeholder_columns = [];
                $placeholder_values = [];
                $function_columns = [];
                
                foreach ($bt_data as $column => $value) {
                    if ($value === 'NOW()') {
                        $function_columns[] = "{$column} = NOW()";
                    } else {
                        $placeholder_columns[] = $column;
                        $placeholder_values[] = $value;
                    }
                }
                
                // 构建SQL语句
                $sql = "INSERT INTO bt ";
                
                if (!empty($placeholder_columns)) {
                    $columns_part = '(' . implode(', ', $placeholder_columns) . ')' . 
                                   (empty($function_columns) ? '' : ', ');
                    $values_part = 'VALUES (' . implode(', ', array_fill(0, count($placeholder_columns), '?')) . ')' . 
                                  (empty($function_columns) ? '' : ', ');
                } else {
                    $columns_part = '';
                    $values_part = '';
                }
                
                if (!empty($function_columns)) {
                    $function_part = implode(', ', $function_columns);
                } else {
                    $function_part = '';
                }
                
                // 组合完整的SQL语句
                if (!empty($placeholder_columns) && !empty($function_columns)) {
                    $sql .= "{$columns_part} SET {$function_part}";
                } elseif (!empty($placeholder_columns)) {
                    $sql .= "{$columns_part} {$values_part}";
                } elseif (!empty($function_columns)) {
                    $sql .= "SET {$function_part}";
                }
                
                $stmt = $pdo->prepare($sql);
                if (!empty($placeholder_values)) {
                    $stmt->execute($placeholder_values);
                } else {
                    $stmt->execute();
                }
                $stmt->closeCursor();
            }
            
            // 查询完成后将连接返回连接池
            $instance->releaseConnection($medoo);
        } catch (Exception $e) {
            // 发生异常时，不将连接返回连接池
            $instance->connectionsCount--;
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