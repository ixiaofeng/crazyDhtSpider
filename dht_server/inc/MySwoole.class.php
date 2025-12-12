<?php

class MySwoole
{
    public static function workStart($serv, $worker_id)
    {
        global $config;
        swoole_set_process_name("php_dht_server_event_worker");
        
        // 添加内存管理定时器（每10秒执行一次）
        swoole_timer_tick(10000, function ($timer_id) use ($serv) {
            // 执行垃圾回收
            gc_mem_caches();
            gc_collect_cycles();
            
            // 记录服务器状态和内存使用情况
            $stats = $serv->stats();
            $memory = memory_get_usage(true);
            Func::Logs(json_encode($stats) . " | Memory: " . Func::sizecount($memory) . PHP_EOL, 3);
        });
        
        if (!DEBUG) {
            try {
                // 初始化数据库连接池（单例模式，无需存储在$serv对象上）
                DbPool::getInstance();
            } catch (Exception $e) {
                Func::Logs("数据库连接失败: " . $e->getMessage() . PHP_EOL);
            }
        }
    }

    public static function packet($serv, $data, $fdinfo)
    {
        if (strlen($data) == 0) {
            $serv->close(true);
            return false;
        }
        
        $rs = null;
        $bt_data = null;
        
        try {
            $rs = Base::decode($data);
            if (!is_array($rs) || !isset($rs['infohash'])) {
                $serv->close(true);
                return false;
            }
            
            if (empty(Func::getBtFiles($rs))) {
                $serv->close(true);
                return false;
            }
            
            $rs = Func::getBtFiles($rs);
            $bt_data = Func::getBtData($rs);
            
            if (DEBUG) {
                Func::Logs(json_encode($bt_data, JSON_UNESCAPED_UNICODE) . PHP_EOL, 2);
                $serv->close(true);
                return false;
            }
            
            // 使用协程处理数据库操作，确保资源正确释放
            \Swoole\Coroutine::create(function () use ($bt_data, $rs) {
                try {
                    // 确保数据库查询操作在协程内安全执行
                    DbPool::sourceQuery($rs, $bt_data);
                } catch (Exception $e) {
                    Func::Logs("数据插入失败: " . $e->getMessage() . PHP_EOL);
                } finally {
                    // 释放协程内的变量
                    unset($bt_data, $rs);
                    // 执行协程内的垃圾回收
                    gc_collect_cycles();
                }
            });

        } catch (Exception $e) {
            Func::Logs("Packet处理失败: " . $e->getMessage() . PHP_EOL);
        } finally {
            // 确保连接关闭
            $serv->close(true);
            // 释放主函数内的变量
            unset($data, $rs, $bt_data, $fdinfo);
            // 执行主函数内的垃圾回收
            gc_collect_cycles();
        }
        
        return true;
    }

    public static function task(Swoole\Server $serv, Swoole\Server\Task $task)
    {

    }

    public static function workerExit($serv, $worker_id)
    {
        Swoole\Timer::clearAll();
        
        // 释放数据库连接池
        DbPool::close();
        
        // 执行最后一次垃圾回收
        gc_mem_caches();
        gc_collect_cycles();
    }

    public static function finish($serv, $task_id, $data)
    {

    }
}