<?php
return array(
    'daemonize' => true, //是否后台守护进程
    'worker_num' => 4, // 主进程数, 一般为CPU的1至2倍 降低内存占用
    'task_worker_num' => 300, //task进程的数量 值越大内存占用越高 根据自己的实际情况设置
    'server_ip' => '127.0.0.1', //服务端ip
    'server_port' => 2345, //服务端端口
    'max_conn' => 65535, //最大连接数
    'reload_async' => true, //设置为 false 时，Worker 进程会立即退出，不等待异步事件完成
    'max_request' => 10000, //防止 PHP 内存溢出, 一个工作进程处理 X 次任务后自动重启
    'max_wait_time' => 30, //worker退出之前最大等待时间
    'worker_exit_timeout' => 10, //worker进程退出超时时间，单位秒
    'task_exit_timeout' => 10, //task进程退出超时时间，单位秒
    'dispatch_mode' => 2, //收到会轮循分配给每一个 Worker 进程
    'discard_timeout_request' => false, //是否丢弃已关闭链接的数据请求
    'log_level' => 4, //日志级别设置,生产环境可以配置为5 (使用数字代替常量，避免版本兼容问题)
    // 'log_file' 会在 client.php 中动态设置，避免依赖 BASEPATH 常量
    'heartbeat_check_interval' => 5, //启用心跳检测，此选项表示每隔多久轮循一次，单位为秒
    'heartbeat_idle_time' => 10, //与heartbeat_check_interval配合使用。表示连接最大允许空闲的时间
    'task_enable_coroutine' => true, //task协程开启
    'task_max_request' => 5000, //防止 PHP 内存溢出, 一个task进程处理 X 次任务后自动重启
    'enable_coroutine' => true, //开启协程
    'coroutine_max_num' => 10000, //限制协程最大数量
);
