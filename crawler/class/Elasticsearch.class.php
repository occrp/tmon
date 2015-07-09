<?php
class Elasticsearch {
	
	public $conn ;
	
	function __construct() {
		
		$this->conn = new stdClass();
        $this->conn->server 	= 'localhost';//$ci->config->item('es_server');
        $this->conn->port 		= 9200;//$ci->config->item('es_port');
        $this->conn->username 	= '';//$ci->config->item('es_username');
        $this->conn->password 	= '';//$ci->config->item('es_password');
        
		
		
	}
	
	function _request($path, $data, $method) {
			
		$ch = curl_init();
		$url = $this->conn->server . "/" . $path;
		 
		$qry = json_encode($data);
		 		 
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_PORT, $this->conn->port);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		//curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);		  
		curl_setopt($ch, CURLOPT_TIMEOUT, 2);		  
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: ' . strtoupper($method)));
		if (!empty($data))
		curl_setopt($ch, CURLOPT_POSTFIELDS, $qry);

		$result = curl_exec($ch);		 
		 
		 
		if($result === false) {
			
			$ret['error'] = 'Curl error: '. curl_error($ch);
			
			return $ret;
		
		} else { 
			
			$ret = json_decode($result,true);
			$ret['_url'] = $url;
								 
			return $ret;		
		
		}
		
		curl_close($ch);
		
	}

	function remove($path) {
		
		return $this->_request($path, array(), "DELETE");
		
	}
	
	function search($path, $data) {
		
		return $this->_request($path . "/_search", $data, "POST");
		
	}
	
	function update($path, $data, $version = 0) {
		
		$ver = $version > 0 ? "?version=".$version."&version_type=external" : "";
		
		$new_arr = array("doc"=>$data);
		return $this->_request($path . "/_update" . $ver, $new_arr, "POST");
		
	}
	
	function insert($path, $data, $version = 0) {

		$ver = $version > 0 ? "?version=".$version."&version_type=external" : "";
		
		return $this->_request($path . "/_create" . $ver, $data, "PUT");
		
	}
	
	function count($path, $data = array()) {
		
		return $this->_request($path . "/_count", $data, "GET");
		
	}
	
	function request($path, $data = array()) {
		
		return $this->request($path, $data);
		
	}
	
	function getItemById($path,$id){
	
		$url = "http://".$this->conn->server .':'.$this->conn->port. "/" . $path .'/'. $id;
		
		$ctx = stream_context_create(array('http'=>array('timeout' => 5)));

		
		return json_decode(file_get_contents($url,false,$ctx),true);
	}
	
	function solrRemoveSpecialCharacters($phrase){

		$disalowwed = array('+','-','&&','||','!','(',')','{','}','[',']','^','"','~','*','?',':','\\','/');
    
    	if($this->detectInjection($phrase)){
    		//file_put_contents( 'request.log', $req_dump, FILE_APPEND);
			error_log(serialize($_SERVER), 3, "/home/domains/".MHOST."/logs/injection-atempts".date("Y-m-d").".log");
		}
        
		return str_replace($disalowwed,'',$phrase);
	}
	
	private function detectInjection($phrase){
		
		$disallowedArray = array("burum","script","kkt");
		
		if(strcmp($phrase,str_replace($disallowedArray,'',$phrase)) != 0){
			return true;
		};
		//strcmp("Hello world!","Hello world!");
		
		return false;
	}
	
	//must be implemented as alternative of solrRemoveSpecialCharacters being a native called function when search is triggered
	private function str_replace_json($search, $replace, $subject){ 
     
     	return json_decode(str_replace($search, $replace,  json_encode($subject))); 

	} 
	//EOF must be implemented as alternative of solrRemoveSpecialCharacters being a native called function when search is triggered
	
}