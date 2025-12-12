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
        $wait = 1.0 / MAX_NODE_SIZE;
        
        if ($table instanceof Swoole\Table) {
            // 处理Swoole\Table格式
            // 直接遍历Swoole\Table
            foreach ($table as $key => $node) {
                // 发送查找find_node到node中
                self::find_node(array($node['ip'], $node['port']), $node['nid']);
                //  usleep($wait);
            }
        } else {
            // 处理普通数组格式
            foreach ($table as $node) {
                // 发送查找find_node到node中
                self::find_node(array($node->ip, $node->port), $node->nid);
                //  usleep($wait);
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
        try {
            global $serv;

            // 验证服务器实例
            if (!isset($serv) || !($serv instanceof Swoole\Server)) {
                error_log('DhtServer::send_response: Invalid server instance');
                return false;
            }
            
            // 验证地址信息
            if (!isset($address) || !is_array($address) || count($address) < 2) {
                error_log('DhtServer::send_response: Invalid address');
                return false;
            }
            
            $ip = $address[0];
            $port = $address[1];
            
            // 验证IP和端口
            if (!filter_var($ip, FILTER_VALIDATE_IP) || !is_numeric($port) || $port < 1 || $port > 65535) {
                error_log('DhtServer::send_response: Invalid IP or port');
                return false;
            }
            
            // 验证消息数据
            if (empty($msg) || !is_array($msg)) {
                error_log('DhtServer::send_response: Invalid message');
                return false;
            }
            
            // 编码消息
            $data = Base::encode($msg);
            if ($data === false || empty($data)) {
                error_log('DhtServer::send_response: Failed to encode message');
                return false;
            }
            
            // 发送消息
            return $serv->sendto($ip, $port, $data);
        } catch (Throwable $e) {
            // 捕获所有类型的错误，避免进程崩溃
            error_log('DhtServer::send_response critical error: ' . $e->getMessage());
            return false;
        }
    }
}