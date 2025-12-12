<?php
/*
 * 设置服务器 ulimit -n 65535
 * 记得放开防火墙6882端口
 */
// 在Windows系统中，ulimit命令无效，已移除
// 在Linux系统中，可以手动执行：ulimit -n 65535
// Swoole会自动使用系统允许的最大文件描述符数
error_reporting(E_ERROR );
define('BASEPATH', dirname(__FILE__));
define('AUTO_FIND_TIME', 5000);
define('MAX_NODE_SIZE', 200); 
define('BIG_ENDIAN', pack('L', 1) === pack('N', 1));

// 读取配置文件
$config = require_once __DIR__ . '/config.php';


// 动态设置错误日志路径，避免依赖BASEPATH
$config['log_file'] = __DIR__ . '/logs/error.log';
require_once __DIR__ . '/inc/Node.class.php';
require_once __DIR__ . '/inc/Bencode.class.php';
require_once __DIR__ . '/inc/Base.class.php';
require_once __DIR__ . '/inc/Func.class.php';
require_once __DIR__ . '/inc/DhtClient.class.php';
require_once __DIR__ . '/inc/DhtServer.class.php';
require_once __DIR__ . '/inc/Metadata.class.php';
require_once __DIR__ . '/inc/MySwoole.class.php';
require_once "vendor/autoload.php";

// 节点ID文件路径
$NODE_ID_FILE = __DIR__ . '/node_id.dat';

// 路由表文件路径
$ROUTER_TABLE_FILE = __DIR__ . '/router_table.dat';

// 路由表保存间隔（毫秒）
$ROUTER_TABLE_SAVE_INTERVAL = 60000; // 1分钟

// 读取或生成节点ID
if (file_exists($NODE_ID_FILE)) {
    // 读取保存的节点ID
    $nid = file_get_contents($NODE_ID_FILE);
} else {
    // 生成新ID并保存
    $nid = Base::get_node_id();
    file_put_contents($NODE_ID_FILE, $nid);
}


// 使用Swoole\Table实现进程间共享的路由表
$table = new Swoole\Table(MAX_NODE_SIZE * 2);
$table->column('nid', Swoole\Table::TYPE_STRING, 20);
$table->column('ip', Swoole\Table::TYPE_STRING, 16);
$table->column('port', Swoole\Table::TYPE_INT);
$table->create();

$time = microtime(true);
$bootstrap_nodes = array(
    array('router.bittorrent.com', 6881),
    array('dht.transmissionbt.com', 6881),
    array('router.utorrent.com', 6881)
);

// 记录启动信息到 start 日志
Func::Logs(date('Y-m-d H:i:s', time()) . " - 服务启动..." . PHP_EOL, 1);

Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);
$serv = new Swoole\Server('0.0.0.0', 6882, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
$serv->set($config);
// 注册启动事件
$serv->on('start', function ($serv) {
    // 设置主进程名称，同时包含原始命令信息以便grep查找
    swoole_set_process_name("php_dht_client_master");
    
    // 调用MySwoole的start方法
    MySwoole::start($serv);
});
$serv->on('WorkerStart', 'MySwoole::workStart');
$serv->on('Packet', 'MySwoole::packet');
$serv->on('task', 'MySwoole::task');
$serv->on('WorkerExit', 'MySwoole::workerExit');
$serv->on('finish', 'MySwoole::finish');
$serv->start();
