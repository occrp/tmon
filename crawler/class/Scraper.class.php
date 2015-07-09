<?php 
//error_reporting(0);
//ini_set('display_errors', 0);

define("PRIORITY_INFO", 5);
define("PRIORITY_LOW", 10);
define("PRIORITY_AVERAGE", 25);
define("PRIORITY_HIGH", 50);

require_once(realpath(__DIR__) . '/phpQuery/phpQuery.php');
require_once(realpath(__DIR__) . '/phpQuery/phpQuery/Zend/Http/Client.php');
require_once(realpath(__DIR__) . '/phpQuery/phpQuery/Zend/Http/CookieJar.php');
require_once(realpath(__DIR__) . '/CustomCURL.class.php');

class Scraper
{
    private $content;
    protected $rules;
    protected $processed_data;
    protected $base_url;
    protected $final_url;

    public function __costruct()
    {
        $this->rules = null;
        $this->final_url = '';
        $this->base_url = '';
    }

    public function setRules($rules)
    {
        $this->rules = (array)$rules;
        return $this;
    }

    public function getRules()
    {
        return $this->rules;
    }

    public function getFinalURL()
    {
        return $this->final_url;
    }

    public function setBaseURL($url)
    {
        $this->base_url = $url;
    }

    public function setContent($content)
    {
        if (!empty($content)) {
            $this->content = $content;
        }
        
        return $this;
    }

    public function processRule($contents = null, $rule, $string_mode = true)
    {
        $out = array();
        $contents = ($contents == null) ? $this->content : $contents;
        $contents = preg_replace("#[\x95-\x96]\x20#", '', $contents);

        $doc = phpQuery::newDocument($contents);

        if (empty($rule))
            return null;

        if (is_array($rule)) {
            if (is_array($rule[1])) {
                $result = $doc->find($rule[0]);
                foreach ($rule[1] as $func) {
                    $result = call_user_func_array(array($result, $func), array());
                }
                return $result->text();
            } else {
                switch ($rule[1]) {
                    case 'href':
                        foreach ($doc->find($rule[0]) as $element) {
                            $out[] = $element->getAttribute('href');
                        }
                        break;
                    case 'html-elements':
                        foreach ($doc->find($rule[0]) as $element) {
                            $out[] = pq($element)->html();
                        }
                        break;
                    case 'html':
                        $out[] = $doc->find($rule[0])->html();
                        break;
                    case 'value':
                        foreach ($doc->find($rule[0]) as $element) {
                            $out[] = $element->getAttribute('value');
                        }
                        break;
                    case 'src':
                        foreach ($doc->find($rule[0]) as $element) {
                            $out[] = $element->getAttribute('src');
                        }
                        break;
                    case 'flashvars':
                        foreach ($doc->find($rule[0]) as $element) {
                            $out[] = $element->getAttribute('flashvars');
                        }
                        break;    
                    default:
                        foreach ($doc->find($rule[0]) as $element) {
                            $out[] = $element->getAttribute($rule[1]);
                        }
                        break;
                }
            }
        } else {

            foreach ($doc->find($rule) as $element) {

                $doc = new DOMDocument();
                foreach ($element->childNodes as $child)
                    $doc->appendChild($doc->importNode($child, true));

                $html = $doc->saveHTML();

                $doc = null;

                $html = str_replace(array('<br>', '<br />', '<br/>', '&nbsp;'), "\n", $html);
                $out[] = strip_tags($html);
            }
        }

        phpQuery::unloadDocuments();
        return ($string_mode) ? trim(implode("\n", $out)) : $out;
    }

    //EO Method

    public function processMetaRules()
    {
        $this->processed_data['title'] = Scraper::getCleanString($this->processed_data['title']);
    }

    public function processRules()
    {
        $out = array();

        foreach ($this->rules as $rule_name => $rule_selector) {
            $out[$rule_name] = $this->processRule($this->content, $rule_selector);
        }

        $this->processed_data = $out;
        $this->processHooks();
        $this->processMetaRules();
        return $this;
    }

    public function processHooks()
    {
        foreach ($this->rules as $rule_name => $rule_selector) {
            $hook_name = 'hook_' . $rule_name;
            if (method_exists($this, $hook_name)) {
                $this->processed_data[$rule_name] = $this->$hook_name($this->processed_data[$rule_name], $this->processed_data, $this->content, $this);
            }
        }
    }

    public function getProcessedData()
    {
        return $this->processed_data;
    }

    public function getScraperObject()
    {
        return $this;
    }

    public function setDataValue($value_name, $value)
    {
        if (array_key_exists($value_name, $this->processed_data)) {
            $this->processed_data[$value_name] = $value;
        }
    }

    public static function getInteger($data)
    {
        if (is_numeric($data)) {
            return (int)$data;
        }
        return (int)self::getFloat($data);
    }

    public static function getFloat($data)
    {
        if (is_float($data)) {
            return $data;
        }
        if (preg_match('/((\d+([\.,]?\d+)?)([\.]\d{1,2})?)/', $data, $match)) {
            if (isset($match[3]) && !isset($match[4]) && strlen($match[3]) > 3) {
                return (float)str_replace(array(',', '.'), array('', ''), $match[0]);
            }

            return (float)(isset($match[4]) ? str_replace(',', '', $match[2]) . $match[4]
                    : str_replace(',', '.', $match[1]));
        }
    }

    public static function getLongString($data)
    {
        return trim(str_replace(array("\r\n", "\n"), ' ', $data));
    }

    public static function getCleanString($data)
    {
        $data = trim(str_replace(array("\r\n", "\n"), ' ', strip_tags($data)));
        $data = preg_replace('/[\t]/', ' ', $data);
        $data = preg_replace('/[ ]{2,}/', ' ', $data);
        
        return trim($data);
    }

    public static function getUrlParams($url)
    {
        $params = array();
        $match = array();
        $parts = parse_url($url);
        if (isset($parts['query'])) {
            $parts['query'] = str_replace('&amp;', '%26', $parts['query']);
            $paramsPairs = explode("&", $parts['query']);
            foreach ($paramsPairs as $pair) {
                if (strpos($pair, '=') !== FALSE) {
                    list($paramName, $paramValue) = explode("=", $pair, 2);
                    $params[trim($paramName)] = urldecode(trim($paramValue));
                }
            }
        }
        return $params;
    }

    // Page scraping using Zend Http Client

    public function downloadPage($url, $cookies_jar = null, $params = array())
    {
        $url = trim(Scraper::getLongString($url));
        $url = str_replace('|', '%7C', $url);

        try {
            Zend_Uri::setConfig(array('allow_unwise' => true));

            $client = new Zend_Http_Client($url);

            $client->setConfig(array(
                                    'maxredirects' => 10,
                                    'timeout' => 30,
                                    'persistent' => true, 
                                    'keepalive' => true));                                    

            if (is_object($cookies_jar)) {
                $client->setCookieJar($cookies_jar);
            }

            if (sizeof($params)) {
                $client->setMethod(Zend_Http_Client::POST);

                foreach ($params as $pind => $pval) {
                    $client->setParameterPost($pind, $pval);
                }
            }

            $resp = $client->request();
            return str_replace("\x00", '', $resp->getBody());
        } catch (Exception $e) {
            echo $e->getMessage();
            return '';
        }
    }

    // Page scraping using cURL
    function getPage($page, $redirect = 0, $cookieFile = '', $referer = '')
    {
        if ($cookieFile == '') {
            $cookieFile = dirname(__FILE__) . '/tmp_files/cookies.txt';
        } else {
            $cookieFile = dirname(__FILE__) . '/tmp_files/' . $cookieFile;
        }

        $ch = curl_init();

        // to speed up curl
        $headers = array("Expect:");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($redirect) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        }

        if ($referer != '') {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }

        curl_setopt($ch, CURLOPT_URL, $page);

        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.0.6) Gecko/20060728 Firefox/1.5.0.6');

        $return = curl_exec($ch);

        $this->final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        curl_close($ch);

        return $return;

    }

    //EO Method

    function getPages($pages, $redirect = 0, $cookieFile = '', $referer = '')
    {
        if ($cookieFile == '') {
            $cookieFile = dirname(__FILE__) . '/tmp_files/cookies.txt';
        } else {
            $cookieFile = dirname(__FILE__) . '/tmp_files/' . $cookieFile;
        }

        $headers = array("Expect:"); // to speed up curl
        $options = array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => $redirect,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.0.6) Gecko/20060728 Firefox/1.5.0.6',
        );

        $curl = new CustomCURL();

        foreach ($pages as $page) {
            $page = trim($page);
            if ($page != '') {
                $curl->addSession($page, $options);
            }
        }

        $results = $curl->exec();
        $curl->clear();

        return $results;
    }

    function postData($page, $data, $redirect = 0, $cookieFile = '', $referer = '')
    {
        $ch = curl_init();

        if ($cookieFile == '') {
            $cookieFile = dirname(__FILE__) . '/tmp_files/cookies.txt';
        } else {
            $cookieFile = dirname(__FILE__) . '/tmp_files/' . $cookieFile;
        }

        // to speed up curl
        $headers = array("Expect:");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if ($redirect) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        }

        if($referer!='')
        {
           curl_setopt($ch, CURLOPT_REFERER, $referer);
        }

        curl_setopt($ch, CURLOPT_URL, $page);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.0.6) Gecko/20060728 Firefox/1.5.0.6');

        $return = curl_exec($ch);

        $this->final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        echo curl_error($ch);

        curl_close($ch);

        return $return;
    }

    public function getRedirectURL($url, $type = 'HEAD', $step = 1)
    {
        $url = trim($url);
        if (empty($url) || $url == '') return false;

        $url_parts = @parse_url($url);
        if (!$url_parts) return false;
        if (!isset($url_parts['host'])) return false; //can't process relative URLs
        if (!isset($url_parts['path'])) $url_parts['path'] = '/';

        $sock = fsockopen($url_parts['host'], (isset($url_parts['port']) ? (int)$url_parts['port']
                    : 80), $errno, $errstr, 30);
        if (!$sock) return false;

        $request = $type . ' ' . $url_parts['path'] . (isset($url_parts['query']) ? '?' . $url_parts['query']
                : '') . " HTTP/1.1\r\n";
        $request .= 'Host: ' . $url_parts['host'] . "\r\n";
        $request .= "Connection: Close\r\n\r\n";
        fwrite($sock, $request);
        $response = '';
        while (!feof($sock)) {
            $response .= fread($sock, 8192);
        }
        $lines = explode("\n", $response);

        if (strpos(@$lines[0], '404') !== FALSE) {
            return false;
        }

        if (strpos(@$lines[0], '403') !== FALSE) {
            return $type == 'HEAD' ? $this->getRedirectURL($url, $type = 'GET') : false;
        }

        if (preg_match('/^Location: (.+?)$/m', $response, $matches)) {
            $r = (substr($matches[1], 0, 1) == "/")
                    ? $url_parts['scheme'] . "://" . $url_parts['host'] . trim($matches[1])
                    : trim($matches[1]);
            return (!stristr($r, $url_parts['host']) || $step >= 5) ? $r : $this->getRedirectURL($r, $type, ++$step);
        } else {
            return $url;
        }
    }

    public function getAbsoluteURL($url = NULL)
    {
        if (is_null($url)) {
            return $this->base_url;
        }

        $matches = array();
        if (preg_match("/((http(s?)):\/\/(.+))?[\/]?(.+)([\/]?)/", $url, $matches) && empty($matches[2])) {
            $url = $this->base_url . '/' . $matches[0];
        }

        list($urlPart1, $urlPart2) = explode('://', $url, 2);
        $urlPart2 = str_replace('../', '/', $urlPart2);

        return $urlPart1 . '://' . preg_replace('#//+#', '/', $urlPart2);

    }
}

//EO Class
?>