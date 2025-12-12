<?php

class Func
{

    /*
     * 日志记录
     * @param $msg
     * @param int $type
     */
    public static function Logs($msg, $type = 1)
    {
        // 使用__DIR__构建日志文件路径，避免依赖BASEPATH常量
        $log_dir = __DIR__ . '/../logs/';
        
        if ($type == 1) { //启动信息
            $mode = 'ab';
            $path = $log_dir . 'start_' . date('Ymd') . '.log';
        } elseif ($type == 2) { //hash信息
            $mode = 'ab';
            $path = $log_dir . 'hashInfo_' . date('Ymd') . '.log';
        } else {
            $mode = 'ab';
            $path = $log_dir . 'otherInfo_' . date('Ymd') . '.log';
        }
        
        // 确保日志目录存在
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $fp = fopen($path, $mode);
        fwrite($fp, $msg);
        fclose($fp);
    }

    public static function sizecount($filesize)
    {
        if ($filesize == null || $filesize == '' || $filesize == 0) return '0';
        if ($filesize >= 1073741824) {
            $filesize = round($filesize / 1073741824 * 100) / 100 . ' gb';
        } elseif ($filesize >= 1048576) {
            $filesize = round($filesize / 1048576 * 100) / 100 . ' mb';
        } elseif ($filesize >= 1024) {
            $filesize = round($filesize / 1024 * 100) / 100 . ' kb';
        } else {
            $filesize = $filesize . ' bytes';
        }
        return $filesize;
    }

    public static function characet($data)
    {
        if (!empty($data)) {
            $fileType = mb_detect_encoding($data, array('UTF-8', 'GBK', 'LATIN1', 'BIG5'));
            if ($fileType != 'UTF-8') {
                $data = mb_convert_encoding($data, 'utf-8', $fileType);
            }
        }
        return $data;
    }

    public static function getKeyWords($title)
    {
        if ($title == '') {
            return '';
        }
        $title = explode(' ', $title);
        if (!is_array($title)) {
            return '';
        }
        $title = str_replace(',', '', $title);
        foreach ($title as $key => $value) {
            if (strlen($value) < 5) {
                unset($title[$key]);
            }
            if (strpos($value, '.') !== false) {
                unset($title[$key]);
            }
        }
        return implode(',', $title);
    }

    public static function strToUtf8($str)
    {
        $encode = mb_detect_encoding($str, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
        if ($encode == 'UTF-8') {
            return $str;
        } else {
            return mb_convert_encoding($str, 'UTF-8', $encode);
        }
    }

    public static function array_transcoding($array)
    {
        if (is_array($array)) {
            foreach ($array as $k => $v) {
                $array[$k] = self::array_transcoding($v);
            }
            return $array;
        } else {
            $encode = mb_detect_encoding($array, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            if ($encode == 'UTF-8') {
                return $array;
            } else {
                try {
                    $result = mb_convert_encoding($array, 'UTF-8');
                } catch (Exception $e) {
                    self::Logs($array);
                }
                return $result;
            }
        }
    }
}
