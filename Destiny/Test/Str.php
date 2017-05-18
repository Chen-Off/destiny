<?php
namespace Landers\Substrate\Utils;
/**
 * 字符串辅助类
 * @author Landers
 */

class Str {
    public static function replace($str, $data) {
        $array = [];
        foreach ($data as $key => $value) {
            $key = '{{$' . $key  . '}}';
            $array[$key] = $value;
        }
        return strtr($str, $array);
    }

    public static function isUtf8($str) {
        if (preg_match("/^([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}/",$str) == true
        || preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}$/",$str) == true
        || preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){2,}/",$str) == true){
            return true;
        }else{
            return false;
        }
    }

    public static function contains($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ($needle != '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function regular($str) {
        $chrs = '().+[]^$-{}/'; $a = str_split($chrs);
        foreach ($a as $i => $item) {
            $a[$item] = '\\'.$item; unset($a[$i]);
        };
        return strtr($str, $a);
    }

    public static function left($s, $n){
        return mb_substr($s, 0, $n, 'utf-8');
    }

    public static function right($s, $n){
        return mb_substr($s, mb_strlen($s, 'utf-8') - $n, $n, 'utf-8');
    }

    public static function cutLeft($s, $n=1){
        return self::right($s, mb_strlen($s, 'utf-8') - $n);
    }

    public static function cutRight($s, $n=1){
        return self::left($s, mb_strlen($s, 'utf-8') - $n);
    }

    /**
     * 将字符串转为驼峰式
     * @param  [type] $s [description]
     * @return [type]    [description]
     */
    public static function camelCase($s) {
        $s = ucwords(str_replace(['-', '_'], ' ', $s));
        return str_replace(' ', '', $s);
    }

    /**
     * 将字符串转为形式
     * @param $s
     * @param string $delimiter
     * @return mixed|string
     */
    public static function snake($s, $delimiter = '_')
    {
        if (! ctype_lower($s)) {
            $s = preg_replace('/\s+/u', '', $s);
            $s = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1'.$delimiter, $s));
        }
        return $s;
    }

    public static function random($len = 8, $type = NULL){
        $number = '0123456789';
        $upper  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower  = 'abcdefghijklmnopqrstuvwxyz';
        if ( !$type ) $type = 'all';
        switch ($type) {
            case 'all'      : $str = $number.$upper.$lower; break;
            case 'number'   : $str = $number; break;
            case 'upper'    : $str = $upper; break;
            case 'lower'    : $str = $lower; break;
        };
        $a = str_split($str); shuffle($a);
        $min = 0; $max = count($a) - 1;
        for ($i = 0; $i < $len; $i++) {
            $r[] = $a[mt_rand($min, $max)];
        }
        return implode('', $r);
    }

    public static function between($s, $s1, $s2, $isall = NULL, $only0 = NULL){
        !is_null($isall) or $isall = false;
        !is_null($only0) or $only0 = true;
        $s1 = self::regular($s1);
        $s2 = self::regular($s2);

        $preg = '/'.$s1.'(.*?)'.$s2.'/si';
        if ($isall) preg_match_all($preg, $s, $match);
        else preg_match($preg, $s, $match);
        return $only0 ? $match[1] : $match[0];
    }

    //字符串切割成数组
    public static function split($xvar, $split = ','){
        if (!$xvar) return array();
        if (is_array($xvar)) return $xvar;
        if (is_array($split)) $split = implode('', $split);
        return preg_split('/['.$split.' ]+/', $xvar);
    }

    //对字符串进行遮掩
    public static function mask($str, $start, $len, $str_mask = '*'){
        $a = preg_split('/(?<!^)(?!$)/u', $str);
        for ($i = 0; $i < $len; $i++) {
            $a[$start-1+$i] = $str_mask;
        };
        return implode('', $a);
    }

    /**
     * 结字符串变量解析
     */
    public static function parse($str, $arr) {
        extract($arr);
        return eval("return \"$str\";");
    }

    //从集合是否存在某数据
    public static function existsInSet($char, $str){
        if (!strlen($str)) return false;
        $arr = self::split($str, ',');
        $arr = array_flip($arr);
        return isset($arr[$char]);
    }

    //从集合去删除某项
    public static function removeInSet($char, $str){
        $arr = self::split($str, ',');
        $arr = array_flip($arr); unset($arr[$char]);
        $arr = array_flip($arr);
        return implode(',', $arr);
    }
}
?>