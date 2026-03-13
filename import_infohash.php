<?php
/**
 * 大数据量infohash导入脚本
 * 功能：将MySQL history表中的infohash批量导入到Redis
 * 支持：200万+数据量，内存占用低，速度快
 */

// ------------------------- 配置部分 ------------------------- //
// 数据库配置
$db_config = [
    'host' => '127.0.0.1',          // 数据库主机
    'port' => 3306,                 // 数据库端口
    'user' => 'dht',               // 数据库用户名
    'pass' => '',      // 数据库密码
    'dbname' => 'dht',    // 数据库名
    'charset' => 'utf8mb4'          // 字符集
];

// Redis配置（直接定义）
$redis_config = [
    'host' => 'localhost',          // Redis主机
    'port' => 6379,                 // Redis端口
    'password' => '',               // Redis密码（无密码留空）
    'database' => 0,                // Redis数据库
    'timeout' => 2                  // 连接超时时间
];

// 导入参数配置
$import_config = [
    'batch_size' => 10000,          // 每批处理数量（推荐5000-20000）
    'table_name' => 'history',      // 源数据表名
    'infohash_field' => 'infohash', // infohash字段名
    'expire_time' => 86400,         // Redis数据过期时间（秒）
    'prefix' => 'dht_'              // Redis键前缀（与config.php保持一致）
];

// 输出开始信息
echo "=== DHT infohash大数据量导入工具 ===\n";
echo "导入配置：\n";
echo "- 数据库：{$db_config['host']}:{$db_config['port']}/{$db_config['dbname']}\n";
echo "- 源表：{$import_config['table_name']}.{$import_config['infohash_field']}\n";
echo "- Redis：{$redis_config['host']}:{$redis_config['port']}\n";
echo "- 每批处理：{$import_config['batch_size']} 条\n";
echo "- 过期时间：{$import_config['expire_time']} 秒\n";
echo "- 键前缀：{$import_config['prefix']}\n";
echo "==================================\n\n";

// 连接MySQL
try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['dbname']};charset={$db_config['charset']}",
        $db_config['user'],
        $db_config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true // 使用缓冲查询确保数据完整读取
        ]
    );
    echo "✓ MySQL连接成功\n";
} catch (PDOException $e) {
    die("✗ MySQL连接失败：" . $e->getMessage() . "\n");
}

// 连接Redis
try {
    $redis = new Redis();
    $redis->connect($redis_config['host'], $redis_config['port'], $redis_config['timeout'] ?? 2);
    
    if (!empty($redis_config['password'])) {
        $redis->auth($redis_config['password']);
    }
    
    if (!empty($redis_config['database'])) {
        $redis->select($redis_config['database']);
    }
    
    echo "✓ Redis连接成功\n";
} catch (RedisException $e) {
    die("✗ Redis连接失败：" . $e->getMessage() . "\n");
}

// 获取数据总量（由于infohash是主键，不可能为NULL，所以移除WHERE条件）
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM {$import_config['table_name']}");
    $count_stmt->execute();
    $total_count = $count_stmt->fetchColumn();
    echo "✓ 数据总量：{$total_count} 条\n";
    $count_stmt->closeCursor();
} catch (PDOException $e) {
    die("✗ 获取数据总量失败：" . $e->getMessage() . "\n");
}

// 开始导入
echo "\n开始导入数据...\n";
echo "==================================\n";

$processed_count = 0;
$start_time = microtime(true);
$current_time = $start_time;

$limit = $import_config['batch_size'];
$last_infohash = '';
$is_first_batch = true;
$continue_import = true;

// 使用基于主键（infohash）的分页方式处理，避免OFFSET导致的性能问题
try {
    while ($continue_import) {
        // 重置Redis连接
        if (!$redis->isConnected()) {
            $redis->connect($redis_config['host'], $redis_config['port'], $redis_config['timeout'] ?? 2);
        }
        
        // 基于主键的分页查询，避免OFFSET带来的性能问题
        $is_current_batch_first = $is_first_batch;
        
        if ($is_current_batch_first) {
            // 第一批数据，直接查询
            $query = "SELECT {$import_config['infohash_field']} FROM {$import_config['table_name']} " .
                     "ORDER BY {$import_config['infohash_field']} " .
                     "LIMIT {$limit}";
        } else {
            // 后续批次，使用WHERE条件基于上一批的最后一个infohash进行查询
            $query = "SELECT {$import_config['infohash_field']} FROM {$import_config['table_name']} " .
                     "WHERE {$import_config['infohash_field']} > ? " .
                     "ORDER BY {$import_config['infohash_field']} " .
                     "LIMIT {$limit}";
        }
        
        $stmt = $pdo->prepare($query);
        if (!$is_current_batch_first) {
            $stmt->bindValue(1, $last_infohash, PDO::PARAM_STR);
        }
        
        // 更新标志，仅在当前批次处理完成后
        if ($is_first_batch) {
            $is_first_batch = false;
        }
        $stmt->execute();
        $batch_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        
        // 如果当前批次没有数据，说明已经导入完成
        if (empty($batch_data)) {
            break;
        }
        
        // 开启Redis管道
        $redis->multi(Redis::PIPELINE);
        
        // 批量添加到管道（由于infohash是主键，不可能为空，所以移除空值检查）
        // 使用统一的Set存储所有infohash，直接存储二进制格式
        $set_key = $import_config['prefix'] . 'infohashes';
        foreach ($batch_data as $row) {
            $infohash_hex = $row[$import_config['infohash_field']];
            $infohash_bin = hex2bin($infohash_hex);
            $redis->sAdd($set_key, $infohash_bin);
        }
        // 为Set设置过期时间
        $redis->expire($set_key, $import_config['expire_time']);
        
        // 执行管道
        $redis->exec();
        
        // 更新统计
        $processed_count += count($batch_data);
        
        // 记录当前批次的最后一个infohash，作为下一批的查询起点
        $last_infohash = end($batch_data)[$import_config['infohash_field']];
        
        // 计算进度
        $progress = (int)min(100, round(($processed_count / $total_count) * 100, 2));
        
        // 输出进度（每10%或每10秒输出一次）
        $now = microtime(true);
        if ($progress % 10 == 0 || ($now - $current_time) >= 10) {
            $current_time = $now;
            $elapsed = round($now - $start_time, 2);
            $speed = round($processed_count / $elapsed, 0);
            $remaining = $speed > 0 ? round(($total_count - $processed_count) / $speed, 2) : 0;
            
            echo "进度：{$progress}% | 已处理：{$processed_count}/{$total_count} | 速度：{$speed}条/秒 | 用时：{$elapsed}秒 | 剩余：{$remaining}秒\n";
            
            // 释放内存
            unset($batch_data);
            gc_collect_cycles();
        }
        
        // 检查是否已经处理完所有数据
        if ($processed_count >= $total_count) {
            $continue_import = false;
        }
    }
} catch (PDOException $e) {
    die("\n✗ 数据导入失败（MySQL错误）：" . $e->getMessage() . "\n");
} catch (RedisException $e) {
    die("\n✗ 数据导入失败（Redis错误）：" . $e->getMessage() . "\n");
} catch (Throwable $e) {
    die("\n✗ 数据导入失败（系统错误）：" . $e->getMessage() . "\n");
}

// 完成导入
$end_time = microtime(true);
$total_time = round($end_time - $start_time, 2);
$avg_speed = round($processed_count / $total_time, 0);

// 关闭连接
$pdo = null;
$redis->close();

// 输出完成信息
echo "==================================\n";
echo "\n✅ 导入完成！\n";
echo "- 总数据量：{$total_count} 条\n";
echo "- 成功导入：{$processed_count} 条\n";
echo "- 总耗时：{$total_time} 秒\n";
echo "- 平均速度：{$avg_speed} 条/秒\n";
echo "- Redis键前缀：{$import_config['prefix']}\n";
echo "- 数据过期时间：{$import_config['expire_time']} 秒\n";
echo "\n📝 提示：可使用以下命令验证Redis数据：\n";
echo "   redis-cli keys '{$import_config['prefix']}*' | wc -l\n";
echo "   redis-cli TTL '{$import_config['prefix']}样本infohash'\n";
