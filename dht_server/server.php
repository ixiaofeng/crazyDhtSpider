<?php
/*
 * 设置服务器 ulimit -n 65535
 * 记得放开防火墙6882端口
 */
// 自动执行ulimit命令设置文件描述符限制
exec('ulimit -n 65535');
define('BASEPATH', dirname(__FILE__));
define('DEBUG', false);
$config = require_once BASEPATH . '/config.php';
$database_config = require_once BASEPATH . '/database.php';
require_once BASEPATH . '/inc/Func.class.php';
require_once BASEPATH . '/inc/DbPool.class.php';
require_once BASEPATH . '/inc/Bencode.class.php';
require_once BASEPATH . '/inc/Base.class.php';
require_once BASEPATH . '/inc/MySwoole.class.php';
require_once "vendor/autoload.php";

Func::Logs(date('Y-m-d H:i:s', time()) . " - 服务启动..." . PHP_EOL, 1);
$serv = new Swoole\Server('0.0.0.0', 2345, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
$serv->set($config);
Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

// 注册启动事件，设置主进程名称
$serv->on('start', function ($serv) {
    // 设置主进程名称，与客户端区分开
    swoole_set_process_name("php_dht_server_master");
});

$serv->on('WorkerStart', 'MySwoole::workStart');
$serv->on('Packet', 'MySwoole::packet');
$serv->on('WorkerExit', 'MySwoole::workerExit');
$serv->start();
