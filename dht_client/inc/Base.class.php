<?php

require_once 'Bencode.class.php';

/**
 * 基础操作类
 */
class Base
{
    /**
     * 把字符串转换为数字
     * @param string $str 要转换的字符串
     * @return   string 转换后的字符串
     */
    static public function hash2int($str)
    {
        return hexdec(bin2hex($str));
    }

    /**
     * 生成随机字符串
     * @param integer $length 要生成的长度
     * @return   string 生成的字符串
     */
    static public function entropy($length = 20)
    {
        $str = '';

        for ($i = 0; $i < $length; $i++)
            $str .= chr(mt_rand(0, 255));

        return $str;
    }

    /**
     * CRC32C (Castagnoli) 实现
     * @param string $data 要计算CRC32C的数据
     * @return int CRC32C值
     */
    static private function crc32c_calculate($data)
    {
        // 检查是否支持crc32c算法
        if (in_array('crc32c', hash_algos())) {
            return hexdec(hash('crc32c', $data));
        }
        // 如果没有crc32c支持，使用标准crc32作为备选
        return crc32($data);
    }
    
    /**
     * 根据 BEP 42 规范生成 Node ID
     * BEP 42: IP 与 Node ID 的绑定协议
     * @param string $ip 公网 IPv4 地址
     * @return string 20字节的二进制 Node ID
     */
    static public function get_node_id($ip = null)
    {
        // 如果没有提供IP，从配置文件获取
        if (is_null($ip)) {
            // 从配置文件获取IP（只在第一次调用时加载）
            static $config_ip = null;
            if (is_null($config_ip)) {
                $config = require __DIR__ . '/../config.php';
                // 只使用local_node_ip配置项
                $config_ip = $config['application']['local_node_ip'];
            }
            $ip = $config_ip;
        }
        
        $ip_parts = explode('.', $ip);
        if (count($ip_parts) !== 4) {
            // 如果IP格式不正确，生成随机node_id
            return self::entropy(20);
        }

        // 1. 准备 IP 的前三个字节，并按规范进行掩码处理
        // Mask: 0x03, 0x0f, 0x3f, 0x00
        $r1 = $ip_parts[0] & 0x03;
        $r2 = $ip_parts[1] & 0x0F;
        $r3 = $ip_parts[2] & 0x3F;

        // 2. 生成随机种子 (Seed)，BEP 42 要求取随机数的最后一个字节
        $seed = rand(0, 255);
        $r = $seed & 0x07; // 取种子后3位

        // 3. 组合成 24-bit 的输入值用于 CRC32C 计算
        // 逻辑：(r << 21) | (ip[0] << 16) | (ip[1] << 8) | ip[2]
        $v = ($r << 21) | ($r1 << 16) | ($r2 << 8) | $r3;

        // 4. 计算 CRC32C (Castagnoli)
        $hash = self::crc32c_calculate(pack('N', $v));

        // 5. 构造 Node ID
        // 前 21 bits (约 2.6 字节) 必须匹配 hash
        $node_id = "";
        $node_id .= chr(($hash >> 24) & 0xFF);
        $node_id .= chr(($hash >> 16) & 0xFF);
        $node_id .= chr((($hash >> 8) & 0xF8) | (rand(0, 255) & 0x07)); // 第3字节前5位是hash，后3位随机
        
        // 剩下的 17 字节填充随机数
        for ($i = 0; $i < 17; $i++) {
            $node_id .= chr(rand(0, 255));
        }
        
        // 6. 最后一位强制设为种子，以便他人验证
        $node_id[19] = chr($seed);

        return $node_id;
    }

    static public function get_neighbor($target, $nid)
    {
        // 优化：使用前15位与目标节点一致，提高邻居相似度
        return substr($target, 0, 15) . substr($nid, 15, 5);
    }

    /**
     * bencode编码
     * @param mixed $msg 要编码的数据
     * @return   string 编码后的数据
     */
    static public function encode($msg)
    {
        return Bencode::encode($msg);
    }

    /**
     * bencode解码
     * @param string $msg 要解码的数据
     * @return   mixed      解码后的数据
     */
    static public function decode($msg)
    {
        return Bencode::decode($msg);
    }

    /**
     * 对nodes列表编码
     * @param mixed $nodes 要编码的列表
     * @return string        编码后的数据
     */
    static public function encode_nodes($nodes)
    {
        // 判断当前nodes列表是否为空
        if (count($nodes) == 0)
            return $nodes;

        $n = '';

        // 循环对node进行编码
        foreach ($nodes as $node) {
            // 检查是否为IPv6地址
            if (strpos($node->ip, ':') !== false) {
                // IPv6地址编码：20字节nid + 16字节IPv6 + 2字节端口
                $ipv6_packed = @inet_pton($node->ip);
                if ($ipv6_packed) {
                    $n .= pack('a20a16n', $node->nid, $ipv6_packed, $node->port);
                }
            } else {
                // IPv4地址编码：20字节nid + 4字节IPv4 + 2字节端口
                $n .= pack('a20Nn', $node->nid, ip2long($node->ip), $node->port);
            }
        }

        return $n;
    }

    /**
     * 对nodes列表解码
     * @param string $msg 要解码的数据
     * @return mixed      解码后的数据
     */
    static public function decode_nodes($msg)
    {
        $n = array();

        // 检查是IPv4还是IPv6格式
        $msg_len = strlen($msg);
        if ($msg_len % 26 == 0) {
            // IPv4格式：26字节/节点
            foreach (str_split($msg, 26) as $s) {
                $r = unpack('a20nid/Nip/np', $s);
                $n[] = new Node($r['nid'], long2ip($r['ip']), $r['p']);
            }
        } elseif ($msg_len % 38 == 0) {
            // IPv6格式：38字节/节点
            foreach (str_split($msg, 38) as $s) {
                $r = unpack('a20nid/a16ip/np', $s);
                $n[] = new Node($r['nid'], @inet_ntop($r['ip']), $r['p']);
            }
        }

        return $n;
    }
}