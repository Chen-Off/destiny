<?php
namespace Landers\Substrate\Classes;
use Landers\Substrate\Utils\Arr;
use Landers\Substrate\Utils\Colorize;

Class CliResponse {

    const COLS = 70;

    private $data = [];   //带有Linux终端格式，用于屏幕输出
    private $text = [];   //不带格式纯文本，用于导出
    private $prefix = 'DATETIME';
    private $startTime;
    private $report;      //上报方法: 类名(须有handle方法)、全局方法名、回调匿名函数、数组[对象, 方法]

    public function __construct() {
        $this->startTime = microtime(true);
    }

    public function __destruct()
    {
        $this->clear();
    }

    public function clear() {
        $this->data = [];
        $this->text = [];
    }

    public function setOptions($options) {
        foreach ($options as $prop => $value) {
            $this->{$prop} = $value;
        }
    }

    /**************************  以行输出  **************************/

    public function note() {
        return $this->show(func_get_args());
    }

    public function noteColor() {
        $args = func_get_args();
        $data = array_slice($args, 1, count($args)-1);
        $color = array_slice($args, 0, 1);
        return $this->show($data, $color[0]);
    }

    public function info() {
        return $this->show(func_get_args(), 'info');
    }

    public function success() {
        return $this->show(func_get_args(), 'green');
    }

    public function warn() {
        return $this->show(func_get_args(), 'warn');
    }

    public function error() {
        return $this->show(func_get_args(), 'error');
    }

    public function reply() {
        return $this->show(func_get_args(), 'cyan');
    }

    public function bool($bool, $tpl = '%s') {
        $text = $bool ? '成功' : '失败';
        if ($bool) {
            return $this->success($tpl, $text);
        } else {
            return $this->warn($tpl, $text);
        }
    }

    /**************************  echo  **************************/

    public function echoText($text, $color = NULL) {
        if (is_array($text)) {
            $dat = array_slice($text, 1);
            $text = vsprintf($text[0], $dat);
        }
        if ($color) $text = $this->colorize($text, $color);
        echo $text;
    }

    public function echoBool($bool, $tpl = '%s') {
        $text = $bool ? '成功' : '失败';
        $color = $bool ? 'green' : 'yellow';
        $text = sprintf($tpl, $text);
        $this->echoText('  [ ');
        $this->echoText($text, $color);
        $this->echoText(' ]  ');
    }

    public function echoSuccess() {
        $args = func_get_args();
        $text = vsprintf($args[0], array_slice($args, 1));
        $this->echoText('  [ ');
        $this->echoText($text, 'green');
        $this->echoText(' ]  ');
    }

    public function echoWarn() {
        $args = func_get_args();
        $text = vsprintf($args[0], array_slice($args, 1));
        $this->echoText('  [ ');
        $this->echoText($text, 'yellow');
        $this->echoText(' ]  ');
    }

    public function echoError() {
        $args = func_get_args();
        $text = vsprintf($args[0], array_slice($args, 1));
        $this->echoText('  [ ');
        $this->echoText($text, 'red');
        $this->echoText(' ]  ');
    }


    /**************************  特殊字符  **************************/

    public function tab($text){
        $rep = array('#tab' => str_repeat(' ', 4));
        return str_replace(array_keys($rep), array_values($rep), $text);
    }

    public function line(){
        return $this->colorize(str_repeat('-', self::COLS), 'gray');
    }

    public function dbline(){
        return $this->colorize(str_repeat('=', self::COLS), 'gray');
    }


    /**************************  导出日志  **************************/
    /**
     * 上报
     */
    public function report() {
        if ( !$this->report ) return null;
        $data = &$this->data;
        $text = &$this->text;
        if ( is_callable($this->report) || function_exists($this->report) ) {
            $func = &$this->report;
            return $func( $data, $text );
        } elseif ( class_exists($this->report) ) {
            return (new $this->report)->handle($data, $text);
        } elseif ( is_array($this->report) ) {
            return call_user_func_array($this->report, [$data, $text]);
        } else {
            throw new \Exception('report参数有误!');
        }
    }

    /**
     * 导出html
     * @param  boolean $is_clear [description]
     * @return [type]            [description]
     */
    public function export($is_clear = false) {
        $html = implode("<br/>", $this->text);
        $html = $this->stripFormat($html);
        if ($is_clear) {
            $this->clear();
        }
        return $html;
    }

    /**
     * 保存到文件
     * @return [type] [description]
     */
    private function save($path) {
        $file = $path.'/'.date('Ymd/H/i').'.log';
        $content = implode("\n", $this->data);
        return @file_put_contents($file, $content.PHP_EOL, true);
    }


    /**************************  场景方法 **************************/

    public function exception($e) {
        $this->error(
            '%s: %s -> %s',
            $class == 'ErrorException' ? '错误捕获' : '异常捕获',
            $class, $e->getMessage()
        );
        $this->error(
            '%s : %s 行',
            $e->getFile(), $e->getLine()
        );
    }

    public function result($result) {
        if ($result->success) {
            return $this->success($result->message);
        } else {
            return $this->error($result->message);
        }
    }

    /**
     * 任务终止回调
     */
    public function continues($sleep = false){
        $msg = '本轮任务结束';
        if ($sleep) $msg .= sprintf('，等待%s秒后继续...', $sleep);
        $this->note(array('#line', '#blank', "$msg"));
        if (!$sleep) {
            $this->complete();
        } else {
            echo PHP_EOL.PHP_EOL;
            sleep($sleep);
        }
    }

    public function start($str) {
        $args = func_get_args();
        $ret = $this->show($args);
        $this->note('#dbline');
        return $ret;
    }

    public function complete() {
        $args = arguments_classify(func_get_args());
        $is_exit = Arr::get($args, 'boolean', false);
        $len = microtime(true) - $this->startTime;
        $this->note(['#line']);
        $this->note('本轮任务结束, 耗时: %s ms', number_format($len * 1000, 2, '.', ''));
        $this->report();
        $this->clear();
        echo PHP_EOL . PHP_EOL;
        if ($is_exit) exit();
        return null;
    }

    public function halt( ) {
        $args = arguments_classify(func_get_args());
        $is_exit = Arr::get($args, 'boolean', false);
        $msg = Arr::get($args, 'string', '任务出错，结束任务');
        $this->note('#line');
        $this->error($msg);
        $this->complete($is_exit);
        echo PHP_EOL . PHP_EOL;
        if ($is_exit) exit();
        return false;
    }

    public function transactBegin($str = '') {
        $this->note('事务处理开始：%s', $str);
    }

    public function transactEnd($bool) {
        $this->note('事务处理');
        $this->echoBool($bool);
        return $bool;
    }

    /**************************  核心私有方法 **************************/
    /**
     * 去除格式
     * @param  [type] $val [description]
     * @return [type]      [description]
     */
    private function stripFormat($val) {
        $val = str_replace(['[0m', '[37m', '[0m', '[35;40m', '[37;40m', '[33;40m', '[33;40;1m', '[32;40;1m'], '', $val);
        $val = str_replace(['#tab'], str_repeat('　', 2), $val);
        return $val;
    }

    /**
     * 颜色格式化
     * @param  [type] $text  [description]
     * @param  [type] $color [description]
     * @return [type]        [description]
     */
    private function colorize($text, $color) {
        $left = strlen($text) - strlen(ltrim($text));
        $right = strlen($text) - strlen(rtrim($text));
        $text = trim($text);
        $text = str_repeat(' ' , $left) . Colorize::{$color}($text) . str_repeat(' ' , $right);

        return $text;
    }

    /**
     * 解析参数
     * @param  [type] $args [description]
     * @return [type]       [description]
     */
    private function parse($args){
        $args_count = count($args);
        $ret = array();
        if ( $args_count == 1 ) {
            $arr = (array)$args[0];
            foreach ($arr as $item) {
                switch ($item) {
                    case '#line' :
                        $item = $this->line();
                        break;
                    case '#dbline':
                        $item = $this->dbline();
                        break;
                    case '#blank':
                        $item = '';
                        break;
                    default :
                        $item = $this->tab($item);
                        break;
                }
                $ret[] = $item;
            }
        } elseif ( $args_count == 2 ) {
            $tpl = (string)$args[0];
            $dat = (array)$args[1];
            $ret[] = $this->tab(vsprintf($tpl, $dat));
        } else {
            $tpl = (string)$args[0];
            $dat = array_slice($args, 1);
            $ret[] = $this->tab(vsprintf($tpl, $dat));
        }
        return $ret;
    }

    private function show($args, $color = '') {
        $items = $this->parse($args);
        $datas = &$this->data;
        $texts = &$this->text;
        $ret = [];
        foreach ($items as $item) {
            $ret[] = $item;
            if ($this->prefix == 'DATETIME') {
                $prefix = date('Y-m-d H:i:s');
                $prefix = "【{$prefix}】";
            } elseif ($this->prefix == 'TIME') {
                $prefix = date('H:i:s');
                $prefix = "【{$prefix}】";
            } elseif ($this->prefix == 'INDEX') {
                $prefix = count($datas) + 1;
                $prefix = str_pad("【{$prefix}】", 10, ' ');
            } else {
                $prefix = $this->prefix;
            }
            $text = $prefix.$item;
            $data = $prefix.($color ? $this->colorize($item, $color) : $item);
            echo ("\n". $data);
            $datas[] = $data;
            $texts[] = $text;
        }
        return count($ret) == 1 ? implode('', $ret) : $ret;
    }
}