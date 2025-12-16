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
     * @return object      解码后的数据
     */
    static public function decode($str)
    {
        try{
            return Bencode2::decode($str);
        }catch (ParseException $e){
            $e->getMessage();
        }
    }

    /**
     * bencode编码
     * @param object $value 要编码的数据
     * @return string        编码后的数据
     */
    static public function encode($value)
    {
        return Bencode2::encode($value);
    }
}