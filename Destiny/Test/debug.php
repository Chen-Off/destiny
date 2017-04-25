<?php
class Debug {
	private static $php_sapi_name;

    private static $breakTime;

	public static function init () {
		self::$php_sapi_name = php_sapi_name();
	}

	private static function file2url($file){
		return str_replace(array(
			$_SERVER['DOCUMENT_ROOT'],
			DIRECTORY_SEPARATOR,
			DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR
		), 	'/',  $file);
	}

	private static function encode($x) {
		if (is_array($x) || is_object($x)) {
			foreach ($x as &$item) {
				$item = self::encode($item);
			}
		} else if (is_string($x)) {
			$x = htmlspecialchars($x, ENT_NOQUOTES);
		}
		return $x;
	}

	public static function var_dump($x, $is_show = false) {
		$a = array();
		$a[] = 'border:1px solid #666666';
		$a[] = 'background-color:#333333';
		$a[] = 'color:#ffffff';
		$a[] = 'text-align:left';
		$a[] = 'margin-top:5px; padding:5px;';
		$a[] = 'word-wrap:break-word;word-break:normal;';
		$style	 = implode(';', $a);
		if (is_array($x) || is_object($x)) {
			$x = self::encode($x);
			$ret = "<pre style=\"$style\">".print_r($x, true).'</pre>';
		} else {
			if (is_string($x)) $x = htmlspecialchars($x, ENT_NOQUOTES);
			$ret = "<textarea style='$style height:300px;width:99.8%;'>$x</textarea>";
		}

		if ($is_show) echo $ret;
		return $ret;
	}

	private static $header = false;
	private static function header() {
		if ( !self::$header ) {
			header('content-type:text/html; charset=utf-8');
	        $description = "401 Landers Debuger";
	        header("HTTP/1.1 $description");
	        header("Status: $description");
	        self::$header = true;
	    }
	}

	public static function show($x = NULL, $is = true, $opts = array()){
		$defaults = array('back' => 0, 'log_file' => false, 'exception' => false);
		if (is_numeric($opts)) $opts = array('back' => $opts);
		$opts = array_merge($defaults, $opts);

		if (!defined('ENV_DEBUG_client') ||  ENV_DEBUG_client!== false){
			switch(gettype($x)){
				case 'NULL' 	: $type = '[NULL(空)]'; $x = strval($x); break;
				case 'integer'	: $type = '[整数]'; $x = strval($x); break;
				case 'long'		: $type = '[长整数]'; $x = strval($x); break;
				case 'double'	: $type = '[双精度]'; $x = strval($x); break;
				case 'string' 	: $type = '[字符串('.strlen($x).')]'; $x = strval($x); break;
				case 'boolean'	: $type = '[布尔]'; $x = $x ? 'true' : 'false'; break;
				case 'resource' : $type = '[资源]'; break;
				case 'object'	: $type = '[对象]'; break;
				case 'array' 	: $type = '[数组('.count($x).')]'; break;
				default			: $type = '[未知数据类型]'; $x = ''; break;
			}

			$a = debug_backtrace(0); $info = &$a[$opts['back']];

			if ( self::$php_sapi_name == 'cli' ) {
				$output = PHP_EOL.PHP_EOL;
			    $file = sprintf("%s(line:%s)".PHP_EOL, $info['file'], $info['line']);
			    $output .= $file . str_repeat('-', strlen($file)).PHP_EOL;
			    $output .= $type.'：';
			    $output .= print_r($x, true);
			    $output .= PHP_EOL.PHP_EOL;
			    if ($opts['exception']) {
			    	throw new \Exception($output);
			    } else {
			    	echo $output;
				    if ($is) die;
			    }
			} else {
				if ($is) self::header();

				$html = '<div style="zoom:1; overflow:hidden; clear:both;">';
				$html .= '<span style="float:left;">%s</span>';
				$html .= '<span style="float:right;">%s %s</span>';
				$html .= '</div>';
				$reload	= '<a href="javascript:window.location.reload();">刷新</a>';

				$url = self::file2url($info['file']);
				$codeline = sprintf('<a title="%s">%s(%s行)</a>', $info['file'], $url, $info['line']);
				echo sprintf($html, $type, $codeline, $reload);
				self::var_dump($x, true);
				if ($is) exit();
			}
		}
	}

	public static function pause($back = 0){
		$a = debug_backtrace(0); $info = &$a[$back];
		if ( self::$php_sapi_name == 'cli' ) {
		    $x = str_repeat('=', 40);
		    echo ("\n".$x.'运行暂停'.$x."\n");
		    echo sprintf("程序暂停于：%s(line:%s)\n\n", $info['file'], $info['line']);
	    	exit();
	    } else {
			$url = self::file2url($info['file']);
			$msg = '程序暂停于：'.$url.'：第'.$info['line'].'行';
			self::show($msg, true, $back);
		}
	}

	public static function startTime() {
        self::$breakTime = microtime(true);
    }

	public static function duration($is_break = false) {
	    $time = microtime(true);
        $duration = $time - self::$breakTime;
        self::$breakTime = $time;
        $duration = round($duration * 1000);
        $mesage = sprintf('耗时：%s ms', $duration);
        self::show($mesage, $is_break, 2);
    }
}
Debug::init();
?>
