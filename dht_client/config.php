<?php
/**
 * 主配置文件 - 包含Swoole服务器配置和应用配置
 */
return array(
    // Swoole服务器配置 - 仅包含Swoole::Server->set()支持的参数
    'server' => array(
        'daemonize' => true, //是否后台守护进程
        'worker_num' => 8, // 主进程数, 一般为CPU的1至2倍 降低内存占用
        'task_worker_num' => 300, //task进程的数量 值越大内存占用越高 根据自己的实际情况设置
        'max_conn' => 65535, //最大连接数
        'reload_async' => true, //设置为 true 时，将启用异步安全重启特性，Worker 进程会等待异步事件完成后再退出
        'max_request' => 0, //防止 PHP 内存溢出, 一个工作进程处理 X 次任务后自动重启
        'max_wait_time' => 60, //worker退出之前最大等待时间，从30调整为60秒
        'dispatch_mode' => 2, //收到会轮循分配给每一个 Worker 进程
        'discard_timeout_request' => false, //是否丢弃已关闭链接的数据请求
        'log_level' => 4, //日志级别设置,生产环境可以配置为5 (使用数字代替常量，避免版本兼容问题)
        'task_enable_coroutine' => true, //task协程开启
        'task_max_request' => 0, //防止 PHP 内存溢出, 一个task进程处理 X 次任务后自动重启
        'enable_coroutine' => true, //开启协程
        'coroutine_max_num' => 10000, //限制协程最大数量
        'task_tmpdir' => '/tmp/swoole_task' // 为Task Worker设置临时目录，避免权限问题
    ),
    
    // 应用配置 - 应用程序自定义配置参数
    'application' => array(
        'server_ip' => '127.0.0.1', //服务端ip
        'server_port' => 2345, //服务端端口
        
        // 节点相关限制
        'max_node_size' => 200,                    // 路由表最大节点数
        'table_size_multiplier' => 2,              // Swoole Table 大小乘数
        
        // 定时器相关配置
        'auto_find_time' => 5000,                 // 自动查找节点的时间间隔（毫秒），从10000调整为15000，进一步降低任务生成频率
        'router_table_save_interval' => 60000,     // 路由表保存间隔（毫秒）
        'gc_interval' => 60000,                    // 垃圾回收间隔（毫秒）
        'task_status_check_interval' => 3000,      // Task Worker 状态检查间隔（毫秒），从2000调整为3000，减少检查频率
        
        // Task Worker 相关限制
        'task_threshold' => 0.85,                  // Task Worker 使用率阈值（达到此值时暂停请求），从0.9调整为0.85，更保守的阈值
        
        // 网络相关限制
        'connection_timeout' => 0.8,               // 连接超时时间（秒）
    ),
    
    // Redis配置
    'redis' => array(
        'enable' => true, // Redis开关，true开启，false关闭
        'host' => '',
        'port' => 6379,
        'password' => '',
        'database' => 0,
        'timeout' => 2,
        'persistent' => true, 
        'prefix' => 'dht_',
        'infohash_expire' => 86400, // infohash过期时间，单位：秒
        'max_connections' => 50
    ),
        // MySQL配置
    'mysql' => array(
        'enable' => false, // MySQL开关，true开启，false关闭
        'host' => '',
        'port' => 3306,
        'user' => '',
        'password' => '',
        'database' => 'dht',
        'charset' => 'utf8mb4',
        'timeout' => 2,
        'persistent' => true, 
        'prefix' => '', // 表前缀
        'table_name' => 'history', // infohash存储表名
        'max_connections' => 50, // MySQL连接池最大连接数
    )
);