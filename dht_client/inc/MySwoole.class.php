<?php

class MySwoole
{
    public static function workStart($serv, $worker_id)
    {
        if ($worker_id >= $serv->setting['worker_num']) {
            swoole_set_process_name("php_dht_client_task_worker");
        } else {
            swoole_set_process_name("php_dht_client_event_worker");
        }

        // 移除手动信号处理，让Swoole管理进程的正常退出
        // Swoole会自动处理SIGTERM和SIGINT信号，确保进程优雅退出

        // 每分钟执行一次垃圾回收
        swoole_timer_tick(60000, function ($timer_id) use ($serv) {
            gc_mem_caches();
            gc_collect_cycles();
        });

        // 初始化并加载路由表（仅在事件worker进程中执行）
        if ($worker_id < $serv->setting['worker_num']) {
            global $table, $ROUTER_TABLE_FILE;

            // 确保路由表文件存在
            if (!file_exists($ROUTER_TABLE_FILE)) {
                // 创建空的路由表文件
                file_put_contents($ROUTER_TABLE_FILE, serialize(array()));
            }

            // 从dat文件加载路由表
            if ($table instanceof Swoole\Table) {
                $nodes = self::loadRouterTableFromFile($ROUTER_TABLE_FILE);
                foreach ($nodes as $key => $node) {
                    // 处理关联数组格式的节点数据
                    if (isset($node['nid'], $node['ip'], $node['port'])) {
                        $table->set($node['nid'], $node);
                    }
                }
            }

            // 只让第一个事件worker进程立即保存路由表，确保文件创建成功
            if ($worker_id == 0) {
                self::saveRouterTableToFile($ROUTER_TABLE_FILE);
            }
        }

        // 只让第一个事件Worker进程执行自动查找节点任务和定时保存路由表
        if ($worker_id == 0) {
            swoole_timer_tick(AUTO_FIND_TIME, function ($timer_id) use ($serv) {
                global $table, $bootstrap_nodes;
                $tableCount = $table instanceof Swoole\Table ? $table->count() : count($table);
                if ($tableCount == 0) {
                    DhtServer::join_dht($table, $bootstrap_nodes);
                } else {
                    DhtServer::auto_find_node($table, $bootstrap_nodes);
                }
            });

            // 定时保存路由表到dat文件
            global $table, $ROUTER_TABLE_FILE, $ROUTER_TABLE_SAVE_INTERVAL;

            // 简化定时保存路由表的逻辑
            swoole_timer_tick($ROUTER_TABLE_SAVE_INTERVAL, function ($timer_id) use ($serv, $ROUTER_TABLE_FILE) {
                // 保存到dat文件
                $success = self::saveRouterTableToFile($ROUTER_TABLE_FILE);
                if (!$success) {
                    error_log('Failed to save router table to file');
                }
            });
        }
    }

    /*
    $server，swoole_server对象
    $fd，TCP客户端连接的文件描述符
    $from_id，TCP连接所在的Reactor线程ID
    $data，收到的数据内容，可能是文本或者二进制内容
    */
    public static function packet($serv, $data, $fdinfo)
    {
        global $config;

        // 检查任务数量
        $stats = $serv->stats();
        if (isset($stats['tasking_num']) && $stats['tasking_num'] > 3000) {
            return false;
        }

        // 检查数据有效性
        if (!is_string($data) || strlen($data) == 0) {
            return false;
        }

        try {
            $msg = Base::decode($data);

            // 检查解码结果
            if ($msg === false || !is_array($msg)) {
                return false;
            }

            // 检查消息类型
            if (!isset($msg['y'])) {
                return false;
            }

            $msg_type = $msg['y'];
            $address = array($fdinfo['address'], $fdinfo['port']);

            if ($msg_type == 'r') {
                // 如果是回复, 且包含nodes信息 添加到路由表
                if (isset($msg['r']) && is_array($msg['r']) && isset($msg['r']['nodes'])) {
                    DhtClient::response_action($msg, $address);
                }
            } elseif ($msg_type == 'q') {
                // 如果是请求, 则执行请求判断
                DhtClient::request_action($msg, $address);
            }
        } catch (Exception $e) {
            // 记录异常但不输出到控制台
            error_log('Packet processing error: ' . $e->getMessage());
        } catch (Throwable $e) {
            // 捕获所有类型的错误，避免进程崩溃
            error_log('Packet processing critical error: ' . $e->getMessage());
        }

        return true;
    }

    public static function task(Swoole\Server $server, Swoole\Server\Task $task)
    {
        try {
            global $config;

            // 验证任务数据
            if (!isset($task->data) || !is_array($task->data)) {
                $task->finish("ERROR: Invalid task data");
                return;
            }

            // 提取任务数据
            $ip = $task->data['ip'] ?? null;
            $port = $task->data['port'] ?? null;
            $infohash_serialized = $task->data['infohash'] ?? null;

            // 验证任务数据完整性
            if (empty($ip) || empty($port) || empty($infohash_serialized)) {
                $task->finish("ERROR: Incomplete task data");
                return;
            }

            // 验证IP和端口
            if (!filter_var($ip, FILTER_VALIDATE_IP) || $port < 1 || $port > 65535) {
                $task->finish("ERROR: Invalid IP or port");
                return;
            }

            // 反序列化infohash
            $infohash = @unserialize($infohash_serialized);
            if ($infohash === false) {
                $task->finish("ERROR: Invalid infohash");
                return;
            }

            // 创建TCP客户端
            $client = new Swoole\Client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
            $client->set(array(
                'open_eof_check' => false,
                'package_max_length' => 1024 * 1024,
            ));

            // 连接到目标服务器
            if (@$client->connect($ip, $port, 0.8)) {
                try {
                    $rs = Metadata::download_metadata($client, $infohash);
                    if ($rs !== false && is_array($rs)) {
                        // 发送响应
                        DhtServer::send_response($rs, array($config['server_ip'] ?? '127.0.0.1', $config['server_port'] ?? 6882));
                    }
                } catch (Throwable $e) {
                    error_log('Metadata download error: ' . $e->getMessage());
                }

                $client->close(true);
            }

            $task->finish("OK");
        } catch (Throwable $e) {
            // 捕获所有类型的错误，避免进程崩溃
            error_log('Task processing critical error: ' . $e->getMessage());
            $task->finish("ERROR: " . $e->getMessage());
        }
    }

    /**
     * 将路由表保存到dat文件
     * @param string $file_path 文件路径
     * @return bool 是否保存成功
     */
    public static function saveRouterTableToFile($file_path)
    {
        try {
            // 验证文件路径
            if (empty($file_path) || !is_string($file_path)) {
                error_log('Invalid file path for saving router table');
                return false;
            }

            // 获取节点数据
            $nodes = self::getNodesFromTable();
            if ($nodes === false) {
                return false;
            }

            // 序列化路由表数据
            $serialized_data = serialize($nodes);
            if ($serialized_data === false) {
                error_log('Failed to serialize router table data');
                return false;
            }

            // 确保目标目录存在
            if (!self::ensureDirectoryExists(dirname($file_path))) {
                return false;
            }

            // 使用原子操作保存文件
            return self::saveFileAtomically($file_path, $serialized_data);
        } catch (Exception $e) {
            error_log('Exception when saving router table to file: ' . $e->getMessage());
            self::cleanupTempFile($file_path . '.tmp');
            return false;
        }
    }

    /**
     * 从全局table中获取节点数据
     * @return array|false 节点数组或false
     */
    private static function getNodesFromTable()
    {
        global $table;
        
        // 验证table实例
        if (!isset($table)) {
            error_log('Router table not initialized');
            return false;
        }

        $nodes = array();
        
        // 从不同类型的table中获取节点数据
        if ($table instanceof Swoole\Table) {
            foreach ($table as $key => $node) {
                if (isset($node['nid'], $node['ip'], $node['port'])) {
                    $nodes[$key] = array(
                        'nid' => $node['nid'],
                        'ip' => $node['ip'],
                        'port' => $node['port']
                    );
                }
            }
        } else {
            foreach ($table as $key => $node) {
                if ($node instanceof Node && isset($node->nid, $node->ip, $node->port)) {
                    $nodes[$node->nid] = array(
                        'nid' => $node->nid,
                        'ip' => $node->ip,
                        'port' => $node->port
                    );
                }
            }
        }
        
        return $nodes;
    }

    /**
     * 确保目录存在，如果不存在则创建
     * @param string $dir 目录路径
     * @return bool 是否成功
     */
    private static function ensureDirectoryExists($dir)
    {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log('Failed to create directory for router table: ' . $dir);
                return false;
            }
        }
        return true;
    }

    /**
     * 原子方式保存文件
     * @param string $file_path 文件路径
     * @param string $data 要保存的数据
     * @return bool 是否成功
     */
    private static function saveFileAtomically($file_path, $data)
    {
        $temp_file_path = $file_path . '.tmp';
        
        // 写入临时文件
        if (file_put_contents($temp_file_path, $data) === false) {
            error_log('Failed to write router table to temp file: ' . $temp_file_path);
            self::cleanupTempFile($temp_file_path);
            return false;
        }
        
        // 确保临时文件存在
        if (!file_exists($temp_file_path)) {
            self::cleanupTempFile($temp_file_path);
            return false;
        }
        
        // 重命名临时文件为正式文件
        if (rename($temp_file_path, $file_path)) {
            return true;
        }
        
        // 尝试备选方案：删除目标文件后重命名
        if (file_exists($file_path) && !@unlink($file_path)) {
            self::cleanupTempFile($temp_file_path);
            return false;
        }
        
        // 再次尝试重命名
        if (rename($temp_file_path, $file_path)) {
            return true;
        }
        
        // 最后尝试直接写入目标文件
        if (file_put_contents($file_path, $data) !== false) {
            self::cleanupTempFile($temp_file_path);
            return true;
        }
        
        // 所有尝试都失败
        self::cleanupTempFile($temp_file_path);
        error_log('Failed to save router table to file: ' . $file_path);
        return false;
    }

    /**
     * 清理临时文件
     * @param string $temp_file_path 临时文件路径
     */
    private static function cleanupTempFile($temp_file_path)
    {
        if (file_exists($temp_file_path)) {
            @unlink($temp_file_path);
        }
    }

    /**
     * 从dat文件加载路由表
     * @param string $file_path 文件路径
     * @return array 路由表节点数组
     */
    public static function loadRouterTableFromFile($file_path)
    {
        try {
            // 检查文件是否存在
            if (!file_exists($file_path)) {
                return array();
            }

            // 读取文件内容
            $serialized_data = file_get_contents($file_path);

            if ($serialized_data === false) {
                error_log('Failed to read router table from file: ' . $file_path);
                return array();
            }

            // 反序列化数据
            $nodes = @unserialize($serialized_data);

            // 验证数据格式
            if ($nodes === false || !is_array($nodes)) {
                error_log('Invalid router table data format in file: ' . $file_path);
                // 不再删除损坏的文件，而是返回空数组让系统重新构建
                return array();
            }

            return $nodes;
        } catch (Exception $e) {
            error_log('Exception when loading router table from file: ' . $e->getMessage());
            // 不再删除损坏的文件，而是返回空数组让系统重新构建
            return array();
        }
    }

    public static function workerExit($serv, $worker_id)
    {
        try {
            // 清理所有定时器，确保进程能快速退出
            swoole_timer_clear_all();

            // 释放全局变量资源
            if (function_exists('gc_mem_caches')) {
                gc_mem_caches();
            }
        } catch (Throwable $e) {
            // 捕获所有异常，避免影响进程退出
            // 不记录日志，避免额外IO操作
        }

        // 立即返回，确保进程快速退出
        return;
    }

    public static function finish($serv, $task_id, $data)
    {
        // 任务完成回调，目前不需要额外处理
    }

    public static function start($serv)
    {
        swoole_timer_tick(10000, function ($timer_id) use ($serv) {
            //Func::Logs(json_encode($serv->stats()) . PHP_EOL, 3);
            // 使用__DIR__构建日志文件路径，避免依赖BASEPATH常量
            $logFile = __DIR__ . '/../logs/error.log';
            $maxSize = 1024 * 1024;
            if (file_exists($logFile) && filesize($logFile) > $maxSize) {
                $handle = fopen($logFile, 'w');
                if ($handle) {
                    ftruncate($handle, 0);
                    fclose($handle);
                }
            }
        });
    }
}
