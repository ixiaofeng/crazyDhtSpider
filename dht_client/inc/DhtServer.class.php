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
                // 直接使用usleep，但在协程环境中会自动被Swoole hook
                usleep(5); // 5毫秒
            }
        }
    }

    public static function find_node($address, $id = null)
    {
        global $nids;
        
        // 按目标网段选择Node ID，在同一网段使用相同的ID
        $current_nid = self::select_node_id_by_address($address);
        
        if (is_null($id)) {
            $mid = Base::get_node_id();
        } else {
            $mid = Base::get_neighbor($id, $current_nid); // 否则伪造一个相邻id
        }
        
        // 定义发送数据 认识新朋友的。
        $msg = array(
            't' => Base::entropy(2),
            'y' => 'q',
            'q' => 'find_node',
            'a' => array(
                'id' => $current_nid,
                'target' => $mid
            )
        );
        
        // 发送请求数据到对端
        self::send_response($msg, $address);
    }
    
    /**
     * 按目标地址选择合适的Node ID
     * 同一网段使用相同的Node ID，提高在该区域的信誉值
     * @param array $address 目标地址 [ip, port]
     * @return string 选择的Node ID
     */
    private static function select_node_id_by_address($address)
    {
        global $nids;
        $ip = $address[0];
        
        // 检查是否为IPv6地址
        if (strpos($ip, ':') !== false) {
            // IPv6地址：使用前4个部分作为网络标识
            $ipv6_parts = explode(':', $ip);
            if (count($ipv6_parts) >= 4) {
                $network_key = implode(':', array_slice($ipv6_parts, 0, 4));
            } else {
                $network_key = $ip;
            }
        } else {
            // IPv4地址：使用前三位作为网络标识
            $ip_parts = explode('.', $ip);
            if (count($ip_parts) >= 3) {
                $network_key = $ip_parts[0] . '.' . $ip_parts[1] . '.' . $ip_parts[2];
            } else {
                $network_key = $ip;
            }
        }
        
        // 使用网段的哈希值选择Node ID，确保同一网段使用相同ID
        $hash = crc32($network_key);
        $index = abs($hash) % count($nids);
        
        return $nids[$index];
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
    
    /**
     * 发送get_peers请求
     * @param array $address 目标地址 [ip, port]
     * @param string $infohash 要查询的infohash
     * @param string $nid 目标节点ID
     * @return void
     */
    public static function get_peers($address, $infohash, $nid = null)
    {
        global $nids;
        
        // 按目标网段选择Node ID，在同一网段使用相同的ID
        $current_nid = self::select_node_id_by_address($address);
        
        // 如果提供了节点ID，伪造一个相邻ID，否则使用当前ID
        $mid = is_null($nid) ? $current_nid : Base::get_neighbor($nid, $current_nid);
        
        // 定义get_peers请求消息
        $msg = array(
            't' => Base::entropy(2),
            'y' => 'q',
            'q' => 'get_peers',
            'a' => array(
                'id' => $current_nid,
                'info_hash' => $infohash
            )
        );
        
        // 发送请求数据到对端
        self::send_response($msg, $address);
    }
}