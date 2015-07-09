<?php 
require_once(realpath(__DIR__) . '/Elasticsearch.class.php');
require_once(realpath(__DIR__) . '/generalHelper.class.php');

class ItemHandler extends GeneralHelper
{
   
	function __construct()
	{
	
	}
    
    public function saveScrapedDataToElastic($data,$path){
	    
	    $elasticsearch = new Elasticsearch();
	    
	    //sleep(mt_rand(1,5));
	    /*
	    $cq = get_headers("http://".$elasticsearch->conn->server.":".$elasticsearch->conn->port."/".$path."/".$data['_id'],1);
       
        if ($cq[0] != "HTTP/1.0 404 Not Found") {
	     	      
          xprint("Saving items ".$GLOBALS['current']." from ".$GLOBALS['total_items_from_page']."(","cyan");          
          xprint("tot:".$GLOBALS['global_counter']." ");
          xprint("succ:".$GLOBALS['global_success_counter']." ","green");
		  xprint("war:".$GLOBALS['global_warning_counter']." ","yellow");
		  xprint("err:".$GLOBALS['global_error_counter'],"red");
		  xprint("):".$data['url'].PHP_EOL,"cyan");	

       	   
           xprint("NOT SAVED - ALREADY EXISTS".PHP_EOL);
           $GLOBALS['global_warning_counter'] ++;
	       return false;
        }
	    */
	    
	    
	    $es_res = $elasticsearch->insert($path . "/" . $data['_id'],$data);
	    
	    if(isset($es_res['created'])&&$es_res['created']){
			
			xprint("SAVED SUCCESSFUL".PHP_EOL,'green');
			
		    xprint("Saving item ".$GLOBALS['current']." from ".$GLOBALS['total_items_from_page']."(","cyan");          
            xprint("tot:".$GLOBALS['global_counter']." ");
            xprint("succ:".$GLOBALS['global_success_counter']." ","green");
		    xprint("war:".$GLOBALS['global_warning_counter']." ","yellow");
		    xprint("err:".$GLOBALS['global_error_counter'].PHP_EOL,"red");
		    
			$GLOBALS['global_success_counter'] ++;
			return true;
	    }
	    print_r($es_res);
	    xprint("NOT SAVED - ERROR:", "red");
		xprint(" - PROBLEM WITH PUTTING DATA INTO THE DATABASE - Elasticsearch fails!".PHP_EOL);
		$GLOBALS['global_error_counter'] ++;
		return false;
    }
    public function saveScrapedDataToMongo($data, $skip = 0) // skip = 1 will skip the db checking
    {
    	return false;	 
    }
    
    public function getAbsoluteURL($url = NULL) 
    {
        if (is_null($url) || $url == '') {
            return '';
        }

        $matches = array();
        if (preg_match("/((http(s?)):\/\/(.+))?[\/]?(.+)([\/]?)/", $url, $matches) && empty($matches[2])) {
            $url = $this->base_url . '/' . $matches[0];
        }

        list($urlPart1, $urlPart2) = explode('://', $url, 2);
        $urlPart2 = str_replace('../', '/', $urlPart2);
        
        return $urlPart1 . '://' . preg_replace('#//+#', '/', $urlPart2);        
    }     
       
} //EO Class

?>