<?php
use Rhilip\Bencode\Bencode as Bencode2;
use Rhilip\Bencode\ParseException;
/**
 * bencode编码解码类
 */
class Bencode
{
    /**
     * bencode解码
     * @param string $str 要解码的数据
     * @return mixed      解码后的数据
     */
    static public function decode($str)
    {
        // 先验证输入是否为字符串
        if (!is_string($str) || empty($str)) {
            return false;
        }
        
        try {
            return Bencode2::decode($str);
        } catch (ParseException $e) {
            // 记录错误但不抛出异常
            error_log('Bencode decode error: ' . $e->getMessage());
            return false;
        } catch (Throwable $e) {
            // 捕获所有类型的错误，避免进程崩溃
            error_log('Bencode decode critical error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * bencode编码
     * @param mixed $value 要编码的数据
     * @return string|false 编码后的数据或false
     */
    static public function encode($value)
    {
        // 验证输入是否有效
        if ($value === null) {
            return false;
        }
        
        try {
            return Bencode2::encode($value);
        } catch (Throwable $e) {
            // 捕获所有类型的错误，避免进程崩溃
            error_log('Bencode encode error: ' . $e->getMessage());
            return false;
        }
    }
}