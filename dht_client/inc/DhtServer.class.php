<?php

class DhtServer
{
    /**
     * 加入dht网络
     * @param array $table 路由表
     * @param array $bootstrap_nodes 引导节点列表
     */
    public static function join_dht($table, $bootstrap_nodes)
    {
        if (empty($bootstrap_nodes)) {
            return;
        }
        
        if ($table instanceof Swoole\Table) {
            if ($table->count() == 0) {
                foreach ($bootstrap_nodes as $node) {
                    $resolvedIp = gethostbyname($node[0]);
                    self::find_node(array($resolvedIp, $node[1])); //将自身伪造的ID 加入预定义的DHT网络
                }
            }
        } else {
            if (count($table) == 0) {
                foreach ($bootstrap_nodes as $node) {
                    $resolvedIp = gethostbyname($node[0]);
                    self::find_node(array($resolvedIp, $node[1])); //将自身伪造的ID 加入预定义的DHT网络
                }
            }
        }
    }

    public static function auto_find_node($table, $bootstrap_nodes)
    {
        // 使用协程批量发送find_node请求，提高并发性能
        $nodes = [];
        
        if ($table instanceof Swoole\Table) {
            // 处理Swoole\Table格式
            foreach ($table as $key => $node) {
                $nodes[] = [$node['ip'], $node['port'], $node['nid']];
            }
        } else {
            // 处理普通数组格式
            foreach ($table as $node) {
                $nodes[] = [$node->ip, $node->port, $node->nid];
            }
        }
        
        // 使用Swoole的协程并发发送请求
        if (!empty($nodes)) {
            // 降低并发数，避免过多连接导致系统资源耗尽
            $concurrency = 20; // 从50降低到20，减少每个批次的任务量
            $node_chunks = array_chunk($nodes, $concurrency);
            
            foreach ($node_chunks as $chunk) {
                // 为每个节点创建一个协程发送请求
                foreach ($chunk as $node_info) {
                    go(function () use ($node_info) {
                        list($ip, $port, $nid) = $node_info;
                        self::find_node(array($ip, $port), $nid);
                    });
                }
                // 增加批次间隔，避免瞬间发送过多请求
                usleep(5); // 从1毫秒增加到5毫秒，使用传统睡眠函数，兼容非协程环境
            }
        }
    }

    public static function find_node($address, $id = null)
    {
        global $nid;
        
        if (is_null($id)) {
            $mid = Base::get_node_id();
        } else {
            $mid = Base::get_neighbor($id, $nid); // 否则伪造一个相邻id
        }
        
        // 定义发送数据 认识新朋友的。
        $msg = array(
            't' => Base::entropy(2),
            'y' => 'q',
            'q' => 'find_node',
            'a' => array(
                'id' => $nid,
                'target' => $mid
            )
        );
        
        // 发送请求数据到对端
        self::send_response($msg, $address);
    }

    public static function send_response($msg, $address)
    {
        global $serv;

        if (!filter_var($address[0], FILTER_VALIDATE_IP)) {
            return false;
        }
        $ip = $address[0];
        $data = Base::encode($msg);
        $serv->sendto($ip, $address[1], $data);
    }
}