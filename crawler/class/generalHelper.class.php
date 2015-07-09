<?php
class GeneralHelper{
protected $markup = '';
protected $link_url_regex = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#iS';

public function __construct() {
     
     }

protected function get_links($downloaded_page) {
	
		if (!empty($downloaded_page)){
			preg_match_all($this->link_url_regex, $downloaded_page, $links);
			return !empty($links[1]) ? $links[1] : false;
		}
		return false;
	}

//OUTPUT METHOD
public function jsonResponse($param, $print = false, $header = true) {
	    if (is_array($param)) {
	        $out = array(
	            'success' => true
	        );
	 
	        if (is_array($param['data'])) {
	            $out['data'] = $param['data'];
	            unset($param['data']);
	            $out = array_merge($out, $param);
	        } else {
	            $out['data'] = $param;
	        }
	 
	    } else if (is_bool($param)) {
	        $out = array(
	            'success' => $param
	        );
	    } else {
	        $out = array(
	            'success' => false,
	            'errors' => array(
	                'reason' => $param
	            )
	        );
	    }
	 
	    $out = json_encode($out);
	 
	    if ($print) {
	        if ($header) header('Content-type: application/json');
	 
	        echo $out;
	        return;
	    }
	 
	    return $out;
	}
	//Eof OUTPUT METHOD	

}


?>