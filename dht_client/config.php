<?php

/**
 * 主配置文件 - 包含Swoole服务器配置和应用配置
 */
return array(
    // Swoole服务器配置 - 仅包含Swoole::Server->set()支持的参数
    'server' => array(
        'daemonize' => true,                    // 是否后台守护进程
        'worker_num' => 2,                      // 主进程数, 一般为CPU的1至2倍 降低内存占用
        'task_worker_num' => 200,               // task进程的数量 值越大内存占用越高 根据自己的实际情况设置
        'max_conn' => 65535,                    // 最大连接数
        'reload_async' => true,                 // 设置为 true 时，将启用异步安全重启特性，Worker 进程会等待异步事件完成后再退出
        'max_request' => 0,                     // 防止 PHP 内存溢出, 一个工作进程处理 X 次任务后自动重启
        'max_wait_time' => 60,                  // worker退出之前最大等待时间，从30调整为60秒
        'dispatch_mode' => 2,                   // 收到会轮循分配给每一个 Worker 进程
        'discard_timeout_request' => false,     // 是否丢弃已关闭链接的数据请求
        'log_level' => 4,                       // 日志级别设置,生产环境可以配置为5 (使用数字代替常量，避免版本兼容问题)
        'task_enable_coroutine' => true,        // task协程开启
        'task_max_request' => 0,                // 防止 PHP 内存溢出, 一个task进程处理 X 次任务后自动重启
        'enable_coroutine' => true,             // 开启协程
        'coroutine_max_num' => 10000,           // 限制协程最大数量
        'task_tmpdir' => '/tmp/swoole_task'     // 为Task Worker设置临时目录，避免权限问题
    ),

    // 应用配置 - 应用程序自定义配置参数
    'application' => array(
        'local_node_ip' => '127.0.0.1',         // 本机ip，必填，用于生成node_id，不要用127.0.0.1
        'server_ip' => '127.0.0.1',             // 服务端ip，即dht_server所在ip
        'server_port' => 2345,                  // 服务端端口
        'download_server_ip' => '127.0.0.1',    // 下载服务器IP（默认本地）
        'download_server_port' => 6882,         // 下载服务器端口（默认与本地端口相同）
        
        // 下载模式配置
        'enable_remote_download' => false,      // 是否启用远程下载转发，即所有下载metadata任务都转发给专门的服务器
        'enable_local_download' => true,        // 是否启用本地下载
        'only_remote_requests' => false,        // 是否只处理来自其他服务器的下载请求（不执行本地爬虫和下载转发）
        
        /*
         * 下载模式参数说明：
         * - enable_remote_download ：控制是否将metadata下载任务转发给专门的远程服务器
         * - enable_local_download ：控制是否在本地执行metadata下载
         * - only_remote_requests ：控制是否只处理来自其他服务器的下载请求（禁用本地爬虫和转发）
         * 
         * 关键组合效果：
         * 1. 默认完整模式 （false, true, false）：本地执行下载，同时运行DHT爬虫，处理本地和远程下载请求
         * 2. 远程转发模式 （true, false, false）：所有下载任务转发到远程服务器，本地只执行DHT爬虫
         * 3. 双下载模式 （true, true, false）：优先使用远程下载转发，本地作为备用
         * 4. 纯爬虫模式 （false, false, false）：只执行DHT爬虫，不处理任何下载请求
         * 5. 专用下载服务器模式 （false, true, true）：只处理来自其他服务器的下载请求，不执行DHT爬虫
         * 6. 限制模式 （任意, 任意, true）：当only_remote_requests为true时，会自动禁用下载转发，只处理远程下载请求
         * 
         * 核心逻辑关系：
         * - 优先级 ：only_remote_requests优先级最高，启用时会禁用下载转发并限制功能
         * - 下载策略 ：当本地和远程下载都启用时，默认优先使用远程转发
         * - 功能隔离 ：通过参数组合可实现从完整节点到专用服务器的多种角色配置
         * 
         * 最佳实践建议：
         * - 完整节点 ：使用默认配置，兼顾爬虫和本地下载
         * - 专用下载服务器 ：设置为（false, true, true），专注处理下载请求
         * - 轻量级爬虫 ：设置为（false, false, false），只执行DHT网络维护
         * - 分布式架构 ：主节点转发下载任务，专用节点处理下载，提高整体性能
         */

        // 节点相关限制
        'max_node_size' => 1500,                // 路由表最大节点数
        'table_size_multiplier' => 2,           // Swoole Table 大小乘数

        // Node ID 配置
        'node_id_pool_size' => 15,              // Node ID池大小
        'node_id_fixed_count' => 5,             // 固定Node ID数量
        'node_id_update_interval' => 3600,      // Node ID更新间隔（秒），改为60分钟
        'node_id_update_ratio' => 0.3,          // 每次更新的Node ID比例

        // 定时器相关配置
        'auto_find_time' => 1000,               // 自动查找节点的时间间隔（毫秒），从10000调整为15000，进一步降低任务生成频率
        'router_table_save_interval' => 60000,  // 路由表保存间隔（毫秒）
        'gc_interval' => 60000,                 // 垃圾回收间隔（毫秒）
        'task_status_check_interval' => 3000,   // Task Worker 状态检查间隔（毫秒），从2000调整为3000，减少检查频率

        // Task Worker 相关限制
        'task_threshold' => 0.95,               // Task Worker 使用率阈值（达到此值时暂停请求），从0.9调整为0.85，更保守的阈值

        // 网络相关限制
        'connection_timeout' => 0.8,            // 连接超时时间（秒）

        // 钓鱼式探测配置
        'fishing_detection' => array(
            'enabled' => false,                 // 是否启用钓鱼式探测，不完善，不建议开启
            'requests_per_minute' => 100,       // 每分钟发送的get_peers请求数量
            'infohash_key' => 'dht_infohashes', // Redis中Infohash存储的键名
            'max_nodes_per_request' => 8,       // 每个请求最多发送给多少个节点
            'min_interval_ms' => 15,            // 相邻请求的最小间隔（毫秒）
        ),
    ),

    // Redis配置 用于存储infohash信息进行重复校验，推荐使用redis
    'redis' => array(
        'enable' => false,                      // Redis开关，true开启，false关闭
        'host' => '',                           // redis服务器地址
        'port' => 6379,
        'password' => '',
        'database' => 0,
        'timeout' => 2,
        'persistent' => true,
        'prefix' => 'dht_',
        'infohash_expire' => 86400,             // infohash过期时间，单位：秒
        'max_connections' => 500                // 及时关注error.log，避免连接数超过redis最大连接数
    ),
    
    // MySQL配置 用于存储infohash信息进行重复校验，服务器性能不行的话不建议使用
    'mysql' => array(
        'enable' => false,                      // MySQL开关，true开启，false关闭
        'host' => '',
        'port' => 3306,
        'user' => 'dht',
        'password' => '',
        'database' => 'dht',
        'charset' => 'utf8mb4',
        'timeout' => 2,
        'persistent' => true,
        'prefix' => '',                         // 表前缀
        'table_name' => 'history',              // infohash存储表名
        'max_connections' => 500,               // MySQL连接池最大连接数
    )
);