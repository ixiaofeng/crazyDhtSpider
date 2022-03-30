<?php
/*
 * 设置服务器 ulimit -n 65535
 * 记得放开防火墙6882端口
 */
error_reporting(E_ERROR);
ini_set('date.timezone', 'Asia/Shanghai');
ini_set("memory_limit", "-1");
define('BASEPATH', dirname(__FILE__));
define('DEBUG', false);
$config = require_once BASEPATH . '/config.php';
require_once BASEPATH . '/inc/Func.class.php';
require_once BASEPATH . '/inc/Bencode.class.php';
require_once BASEPATH . '/inc/Base.class.php';
require_once "vendor/autoload.php";

use Medoo\Medoo;

//记录启动日志
Func::Logs(date('Y-m-d H:i:s', time()) . " - 服务启动..." . PHP_EOL, 1);

//SWOOLE_PROCESS 使用进程模式，业务代码在Worker进程中执行
//SWOOLE_SOCK_UDP 创建udp socket
$serv = new Swoole\Server('0.0.0.0', 2345, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
$serv->set($config);
//一键协程HOOK
Co::set(['hook_flags' => SWOOLE_HOOK_ALL]);

$serv->on('WorkerStart', function ($serv, $worker_id) use ($config) {
    swoole_set_process_name("php_dht_server_event_worker");
    try{
        if (!DEBUG) {
            $database = new Medoo([
                'database_type' => 'mysql',
                'database_name' => $config['db']['name'],
                'server' => $config['db']['host'],
                'username' => $config['db']['user'],
                'password' => $config['db']['pass'],
            ]);
            $serv->mysql = $database;
        }
    }catch (Exception $e){
        Func::Logs("数据库连接失败".PHP_EOL);
    }

});

$serv->on('Packet', function ($serv, $data, $clientInfo) {
    if (strlen($data) == 0) {
        $serv->close(true);
        return false;
    }
    $rs = Base::decode($data);
    if (is_array($rs) && isset($rs['infohash'])) {
        $files = '';
        $length = 0;
        if ($rs['files'] != '') {
            $files = json_encode(Func::array_transcoding($rs['files']), JSON_UNESCAPED_UNICODE);
            if($files==0){
                return false;
            }
            foreach ($rs['files'] as $value) {
                $length += $value['length'];
            }
        } else {
            $length = $rs['length'];
        }
        $bt_data = [
            'name' => $rs['name'],
            'keywords' => Func::getKeyWords($rs['name']),
            'infohash' => $rs['infohash'],
            'files' => $files,
            'length' => $length,
            'piece_length' => $rs['piece_length'],
            'hits' => 0,
            'time' => date('Y-m-d H:i:s'),
            'lasttime' => date('Y-m-d H:i:s'),
        ];
        try{
            if (!DEBUG) {
                $data = $serv->mysql->count("history", [
                    "infohash" => $rs['infohash']
                ]);
                if ($data > 0) {
                    $serv->mysql->update("bt", [
                        "hot[+]" => 1,
                        "lasttime" => date('Y-m-d H:i:s'),
                    ], [
                        "infohash" => $rs['infohash']
                    ]);
                } else {
                    $serv->mysql->insert("history", [
                        "infohash" => $rs['infohash']
                    ]);
                    $serv->mysql->insert("bt", $bt_data);
                }
            } else {
                Func::Logs(json_encode($bt_data, JSON_UNESCAPED_UNICODE), 2) . PHP_EOL;
            }
        }catch (Exception $e){
            Func::Logs("数据插入失败".PHP_EOL);
        }
    }
    $serv->close(true);
});

$serv->on('WorkerExit', function ($server, $worker_id) {
    Swoole\Timer::clearAll();
});

$serv->start();