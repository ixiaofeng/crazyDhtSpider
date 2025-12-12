<?php
return array(
    'reload_async' => true, //设置为 true 时，将启用异步安全重启特性，Worker 进程会等待异步事件完成后再退出
    'daemonize' => true, //是否后台守护进程
    'enable_coroutine' => false, //是否开启协程
    'worker_num' => 4, //设置启动的worker进程数
    'max_request' => 5000, //防止 PHP 内存溢出, 一个工作进程处理 X 次任务后自动重启
    'max_wait_time' => 30, //worker退出之前最大等待时间
    'dispatch_mode' => 1, //收到会轮循分配给每一个 Worker 进程
    'discard_timeout_request' => false, //是否丢弃已关闭链接的数据请求
    'log_level' => 3, //日志级别设置,生产环境可以配置为4 (使用数字代替常量，避免版本兼容问题)
    'log_file' => BASEPATH . '/logs/error.log', //日志路径
    'max_conn' => 65535, //最大连接数
    'heartbeat_check_interval' => 5, //启用心跳检测，此选项表示每隔多久轮循一次，单位为秒
    'heartbeat_idle_time' => 10, //与heartbeat_check_interval配合使用。表示连接最大允许空闲的时间
);
