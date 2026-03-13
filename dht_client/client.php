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
define('BIG_ENDIAN', pack('L', 1) === pack('N', 1));

// 读取配置文件
$config = require_once __DIR__ . '/config.php';

// 动态设置错误日志路径，避免依赖BASEPATH
$config['server']['log_file'] = __DIR__ . '/logs/error.log';
require_once __DIR__ . '/inc/Node.class.php';
require_once __DIR__ . '/inc/Bencode.class.php';
require_once __DIR__ . '/inc/Base.class.php';
require_once __DIR__ . '/inc/Func.class.php';
require_once __DIR__ . '/inc/DhtClient.class.php';
require_once __DIR__ . '/inc/DhtServer.class.php';
require_once __DIR__ . '/inc/Metadata.class.php';
require_once __DIR__ . '/inc/MySwoole.class.php';
require_once __DIR__ . '/inc/Redis.class.php';
require_once __DIR__ . '/inc/Mysql.class.php';
require_once __DIR__ . "/../vendor/autoload.php";

// 初始化Redis连接池，使用配置的最大连接数
$redisPool = RedisPool::getInstance()->init($config['redis']);

// 初始化MySQL连接池，使用配置的最大连接数
$mysqlPool = MysqlPool::getInstance()->init($config['mysql']);

// 节点ID文件路径
$NODE_ID_FILE = __DIR__ . '/node_id.dat';

// 路由表文件路径
$ROUTER_TABLE_FILE = __DIR__ . '/router_table.dat';

// 路由表保存间隔（毫秒）
$ROUTER_TABLE_SAVE_INTERVAL = $config['application']['router_table_save_interval']; // 路由表保存间隔

// 读取或生成节点ID
$NODE_ID_POOL_SIZE = $config['application']['node_id_pool_size'] ?? 15; // 从配置文件读取，默认15
$NODE_ID_FIXED_COUNT = $config['application']['node_id_fixed_count'] ?? 5; // 固定Node ID数量
$NODE_ID_UPDATE_RATIO = $config['application']['node_id_update_ratio'] ?? 0.3; // 每次更新的比例

if (file_exists($NODE_ID_FILE)) {
    // 读取保存的节点ID数组
    $nids = unserialize(file_get_contents($NODE_ID_FILE));
    // 确保至少有NODE_ID_POOL_SIZE个节点ID
    if (count($nids) < $NODE_ID_POOL_SIZE) {
        // 生成足够的节点ID
        while (count($nids) < $NODE_ID_POOL_SIZE) {
            $new_nid = Base::get_node_id();
            if (!in_array($new_nid, $nids)) {
                $nids[] = $new_nid;
            }
        }
        // 保存更新后的节点ID数组
        file_put_contents($NODE_ID_FILE, serialize($nids));
    }
} else {
    // 生成NODE_ID_POOL_SIZE个新ID并保存
    $nids = [];
    while (count($nids) < $NODE_ID_POOL_SIZE) {
        $new_nid = Base::get_node_id();
        if (!in_array($new_nid, $nids)) {
            $nids[] = $new_nid;
        }
    }
    file_put_contents($NODE_ID_FILE, serialize($nids));
}

// 当前使用的node_id索引
$current_nid_index = 0;

// 节点ID池更新时间
$nids_last_update = time();
$NIDS_UPDATE_INTERVAL = $config['application']['node_id_update_interval'] ?? 1800; // 从配置文件读取，默认1800秒

// 设置全局变量
$GLOBALS['NODE_ID_POOL_SIZE'] = $NODE_ID_POOL_SIZE;
$GLOBALS['NODE_ID_FIXED_COUNT'] = $NODE_ID_FIXED_COUNT;
$GLOBALS['NODE_ID_UPDATE_RATIO'] = $NODE_ID_UPDATE_RATIO;
$GLOBALS['NIDS_UPDATE_INTERVAL'] = $NIDS_UPDATE_INTERVAL;
$GLOBALS['NODE_ID_FILE'] = $NODE_ID_FILE;


// 使用Swoole\Table实现进程间共享的路由表
$table = new Swoole\Table($config['application']['max_node_size'] * $config['application']['table_size_multiplier']);
$table->column('nid', Swoole\Table::TYPE_STRING, 20);
$table->column('ip', Swoole\Table::TYPE_STRING, 45); // 增加ip字段长度以支持IPv6地址
$table->column('port', Swoole\Table::TYPE_INT);
$table->create();

// 添加IP+端口索引表，用于快速查找节点，避免遍历整个路由表
$ip_port_index = new Swoole\Table($config['application']['max_node_size'] * $config['application']['table_size_multiplier']);
$ip_port_index->column('nid', Swoole\Table::TYPE_STRING, 20);
$ip_port_index->create();


$time = microtime(true);
$bootstrap_nodes = array(
    // IPv4引导节点
    array('router.bittorrent.com', 6881),
    array('dht.transmissionbt.com', 6881),
    array('router.utorrent.com', 6881),
    // IPv6引导节点
    array('router.silotis.us', 6881)
);

// 记录启动信息到 start 日志
Func::Logs(date('Y-m-d H:i:s', time()) . " - 服务启动..." . PHP_EOL, 1);

Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);
$serv = new Swoole\Server('0.0.0.0', 6882, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
// 额外添加一个 IPv6 监听端口
$serv->addListener('::', 6882, SWOOLE_SOCK_UDP6);
$serv->set($config['server']); // 只传递Swoole相关配置
// 注册启动事件
$serv->on('start', function ($serv) {
    // 设置主进程名称
    swoole_set_process_name("php_dht_client_master");
    
    // 调用MySwoole的start方法处理日志文件大小控制
    MySwoole::start($serv);
});
$serv->on('WorkerStart', 'MySwoole::workStart');
$serv->on('Packet', 'MySwoole::packet');
$serv->on('task', 'MySwoole::task');
$serv->on('WorkerExit', 'MySwoole::workerExit');
$serv->on('finish', 'MySwoole::finish');
$serv->start();
