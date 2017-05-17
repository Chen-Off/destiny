<?php
namespace Zhc\Utils;
use Symfony\Component\DomCrawler\Crawler;

class Http
{
    private $ch = null;
    private $info = array();
    private $_expire;
    private $crawler;
    private $content;
    private static $PROXYTYPE = array(
        "http" => 0,
        "socks4" => CURLPROXY_SOCKS4,
        "socks5" => CURLPROXY_SOCKS5
    );

    private $setopt = array(
        'port'          => 80, //访问的端口,http默认是 80
        'userAgent'     => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.64 Safari/537.31', //客户端 USERAGENT,如:"Mozilla/4.0",为空则使用用户的浏览器
        'timeOut'       => 3, //连接超时时间
        'cookie'        => '',
        'cookiefile'    => false,
        'ssl'           => true, //是否支持SSL
        'gzip'          => true, //客户端是否支持 gzip压缩
        'proxy'         => false, //是否使用代理
        'proxyType'     => 'http', //代理类型,可选择 HTTP 或 SOCKS5
        'proxyHost'     => '127.0.0.1', //代理的主机地址
        'proxyPort'     => 7777, //代理主机的端口
        'proxyAuth'     => false, //代理是否要身份认证(HTTP方式时)
        'proxyAuthType' => 'BASIC', //认证的方式.可选择 BASIC 或 NTLM 方式
        'proxyAuthUser' => 'user', //认证的用户名
        'proxyAuthPwd'  => 'password', //认证的密码
        'location'      => true
    );
    /**
     * 构造函数
     * @param array $setopt :请参考 private $setopt 来设置
     */
    public function __construct($setopt = array())
    {
        function_exists('curl_init') || die('CURL Library Not Loaded');
        $this->ch = curl_init();
        $this->setOpts($setopt);
    }

    public function setOpts($setopt = array())
    {
        $this->setopt = array_merge($this->setopt, $setopt);
        //使用代理
        if ($this->setopt['proxy']) {
            if (in_array($this->setopt['proxyType'], self::$PROXYTYPE)) {
                $proxyType = self::$PROXYTYPE[$this->setopt['proxyType']];
            } else {
                $proxyType = self::$PROXYTYPE['http'];
            }
            curl_setopt($this->ch, CURLOPT_PROXYTYPE, $proxyType);
            curl_setopt($this->ch, CURLOPT_PROXY, $this->setopt['proxyHost']);
            curl_setopt($this->ch, CURLOPT_PROXYPORT, $this->setopt['proxyPort']);
            //代理要认证
            if ($this->setopt['proxyAuth']) {
                $proxyAuthType = $this->setopt['proxyAuthType'] == 'BASIC' ? CURLAUTH_BASIC : CURLAUTH_NTLM;
                curl_setopt($this->ch, CURLOPT_PROXYAUTH, $proxyAuthType);
                $user = "[{$this->setopt['proxyAuthUser']}]:[{$this->setopt['proxyAuthPwd']}]";
                curl_setopt($this->ch, CURLOPT_PROXYUSERPWD, $user);
            }
        }
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, $this->setopt['location']); //启用时会将服务器服务器返回的“Location:”放在header中递归的返回给服务器
        //打开的支持SSL
        if ($this->setopt['ssl']) {
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE); //不对认证证书来源的检查
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, FALSE); //从证书中检查SSL加密算法是否存在

        }
        $header[] = 'Expect:'; //设置http头,支持lighttpd服务器的访问
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);
        $userAgent = $this->setopt['userAgent'] ? $this->setopt['userAgent'] : $_SERVER['HTTP_USER_AGENT']; //设置 HTTP USERAGENT
        curl_setopt($this->ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->setopt['timeOut']); //设置连接等待时间,0不等待
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->setopt['timeOut']); //设置curl允许执行的最长秒数
        //设置客户端是否支持 gzip压缩
        if ($this->setopt['gzip']) {
            curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip');
        }
        //是否使用到COOKIE
        if ($this->setopt['cookiefile']) {
            $cookfile = tempnam(sys_get_temp_dir() , 'cuk'); //生成存放临时COOKIE的文件(要绝对路径)
            curl_setopt($this->ch, CURLOPT_COOKIEJAR, $cookfile);
            curl_setopt($this->ch, CURLOPT_COOKIEFILE, $cookfile);
        }
        if ($this->setopt['cookie']) {
            curl_setopt($this->ch, CURLOPT_COOKIE, $this->setopt['cookie']);
        }
        curl_setopt($this->ch, CURLOPT_HEADER, true); //是否将头文件的信息作为数据流输出(HEADER信息),这里保留报文
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true); //获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($this->ch, CURLOPT_BINARYTRANSFER, true);
    }
    /**
     * 以 GET 方式执行请求
     * @param string $url : 请求的URL
     * @param array $referer :引用页面,为空时自动设置,如果服务器有对这个控制的话则一定要设置的.
     * @return 错误返回:false 正确返回:结果内容
     */
    public function get($url, $referer = '')
    {
        $content = $this->_request('GET', $url, array(), array() , $referer);
        return $content;
    }
    /**
     * 以 POST 方式执行请求
     * @param string $url :请求的URL
     * @param array $params ：请求的参数,格式如: array('id'=>10,'name'=>'yuanwei')
     * @param array $uploadFile :上传的文件,支持相对路径,格式如下:
     * 单个文件上传:array('img1'=>'./file/a.jpg'),同字段多个文件上传:array('img'=>array('./file/a.jpg','./file/b.jpg'))
     * @param array $referer :引用页面,引用页面,为空时自动设置,如果服务器有对这个控制的话则一定要设置的.
     * @return 错误返回:false 正确返回:结果内容
     */
    public function post($url, $params = array() , $uploadFile = array() , $referer = '')
    {
        return $this->_request('POST', $url, $params, $uploadFile, $referer);
    }
    /**
     * 得到错误信息
     * @return string
     */
    public function error() {
        return curl_error($this->ch);
    }
    /**
     * 得到错误代码
     * @return int
     */
    public function errno() {
        return curl_errno($this->ch);
    }
    /**
     * 得到发送请求前和请求后所有的服务器信息和服务器Header信息:
     * [before] ：请求前所设置的信息
     * [after] :请求后所有的服务器信息
     * [header] :服务器Header报文信息
     * @return array
     */
    public function getInfo()
    {
        return $this->info;
    }

    public function getCookie()
    {
        return $this->_parse_cookie($this->info['header'], true);
    }
    /**
     * 析构函数
     */
    public function __destruct()
    {
        curl_close($this->ch);
    }
    /**
     * 执行请求
     * @param string $method :HTTP请求方式
     * @param string $url :请求的URL
     * @param array $params ：请求的参数
     * @param array $uploadFile :上传的文件(只有POST时才生效)
     * @param array $referer :引用页面
     * @return 错误返回:false 正确返回:结果内容
     */
    private function _request($method, $url, $params = array() , $uploadFile = array() , $referer = '')
    {
        if ($method == 'GET') {
            curl_setopt($this->ch, CURLOPT_POST, false);
        }
        curl_setopt($this->ch, CURLOPT_URL, $url);
        if ($method == 'POST') {
            curl_setopt($this->ch, CURLOPT_POST, true);
            if ($uploadFile) {
                $postData = $params;
                foreach ($uploadFile as $key => $file) {
                    if (is_array($file)) {
                        $n = 0;
                        foreach ($file as $f) {
                            $postData[$key . '[' . $n++ . ']'] = '@' . realpath($f); //文件必需是绝对路径

                        }
                    } else {
                        $postData[$key] = '@' . realpath($file);
                    }
                }
            } else {
                $postData = http_build_query($params);
            }
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postData);
        }
        //设置了引用页,否则自动设置
        if ($referer) {
            curl_setopt($this->ch, CURLOPT_REFERER, $referer);
        } else {
            curl_setopt($this->ch, CURLOPT_AUTOREFERER, true);
        }
        $this->info['before'] = curl_getinfo($this->ch); //得到所有设置的信息
        $result               = curl_exec($this->ch); //开始执行请求
        $headerSize           = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE); //得到报文头
        $this->info['header'] = substr($result, 0, $headerSize);
        $result               = substr($result, $headerSize); //去掉报文头
        $this->info['after']  = curl_getinfo($this->ch); //得到所有包括服务器返回的信息
        //如果请求成功
        if ($this->errno() == 0) { //&& $this->info['after']['http_code'] == 200
            $this->content = $result;
            return $result;
        } else {
            return false;
        }
    }

    public function getCrawler()
    {
        if (!class_exists('Symfony\Component\DomCrawler\Crawler')) {
            return;
        }

        if ($this->crawler) {
            $this->crawler->clear();
        } else {
            $this->crawler = new Crawler();
        }
        $this->crawler->addContent($this->content);
        return $this->crawler;
    }

    private function _parse_cookie($header, $toString = false)
    {
        $cookies   = array();
        $cookieStr = '';
        if (preg_match_all('/Set-Cookie:([^\n]+)/i', $header, $matches)) {
            foreach ($matches[1] as $v) {
                $csplit = explode(';', trim($v));
                $cdata  = array();
                 foreach($csplit as $data) {
                        $cinfo       = explode('=', $data);
                        $cinfo[0]    = trim($cinfo[0]);
                        if($cinfo[0] == 'expires') $cinfo[1] = strtotime($cinfo[1]);
                        if($cinfo[0] == 'secure') $cinfo[1] = "true";
                        if($cinfo[0] == 'httponly') $cinfo[1] = "true";
                        if(in_array($cinfo[0], array('domain', 'expires', 'path', 'secure', 'comment', 'httponly'))) {
                                $cdata[trim($cinfo[0])] = $cinfo[1];
                        }
                        else {
                            $cdata['value']['key']   = $cinfo[0];
                            $cdata['value']['value'] = $cinfo[1];
                            if ($cinfo[1] != "deleted") {
                                $cookieStr .= $cinfo[0]."=".$cinfo[1]."; ";
                            }
                        }
                 }
                 $cookies[] = $cdata;
            }
        }

        return $toString ? $cookieStr : $cookies;
    }

    private function _parse_headers($raw_headers)
    {
        if (function_exists('http_parse_headers')) {
            return http_parse_headers($raw_headers);
        }
        $headers = array();
        $key = '';
        foreach (explode("\n", $raw_headers) as $i => $h) {
            $h = explode(':', $h, 2);
            if (isset($h[1])) {
                if (!isset($headers[$h[0]])) $headers[$h[0]] = trim($h[1]);
                elseif (is_array($headers[$h[0]])) {
                    // $tmp = array_merge($headers[$h[0]], array(trim($h[1])));
                    // $headers[$h[0]] = $tmp;
                    $headers[$h[0]] = array_merge($headers[$h[0]], array(
                        trim($h[1])
                    ));

                } else {
                    // $tmp = array_merge(array($headers[$h[0]]), array(trim($h[1])));
                    // $headers[$h[0]] = $tmp;
                    $headers[$h[0]] = array_merge(array(
                        $headers[$h[0]]
                    ) , array(
                        trim($h[1])
                    ));

                }
                $key = $h[0];

            } else

            {
                if (substr($h[0], 0, 1) == "\t")
                $headers[$key].= "\r\n\t" . trim($h[0]);
                elseif (!$key)
                $headers[0] = trim($h[0]);
                trim($h[0]);

            }

        }
        return $headers;
    }
}
//调用示例
//使用代理
//$setopt = array('proxy'=>true,'proxyHost'=>'','proxyPort'=>'');
// $cu = new Curl();
// //得到 baidu 的首页内容
// echo $cu->get('http://1111.ip138.com/ic.asp');
// echo $cu->get('http://1111.ip138.com/ic.asp');
// //模拟登录
// $cu->post('http://www.***.com',array('uname'=>'admin','upass'=>'admin'));
// echo $cu->get('http://www.***.com');
// //上传内容和文件
// echo $cu->post('http://a.com/a.php',array('id'=>1,'name'=>'yuanwei'),
// array('img'=>'file/a.jpg','files'=>array('file/1.zip','file/2.zip')));
//得到所有调试信息
// echo 'ERRNO='.$cu->errno();
// echo 'ERROR='.$cu->error();
// print_r($cu->getinfo());

