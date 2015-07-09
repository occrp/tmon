<?php
require_once(realpath(__DIR__ . '/..') . '/class/Scraper.class.php');
require_once(realpath(__DIR__ . '/..') . '/class/ItemHandler.class.php');

class Tendergovmd extends ItemHandler{

	private $url;
    private $agent;
    private $referer;
    private $use_proxies;
    private $proxies_list;
    private $use_proxy_auth;
    private $preffered_proxy_auth;
    private $proxy_userpwd;
    private $method;
    private $attempts;
    private $agents_rand;
    private $agents_list;
	
 	function __construct() {
    
    }
 	
	public function process($current_settings)
	{	
		
		$_res = "";
	
	 	$scraper = new Scraper();
	 	$cookiesJar = new Zend_Http_CookieJar();
		$contents = $scraper->downloadPage($current_settings->last_url, $cookiesJar);
        
        
        $nextPageAllowed = 1;
      	
        while($nextPageAllowed) {

            $dataBlocks = $scraper->processRule($contents, array('tr', 'html-elements'), false);
       
			$GLOBALS['total_items_from_page'] = count($dataBlocks, true);
       		$broken = 0;
       	
			foreach($dataBlocks as $dataBlock) {
            	
             	$GLOBALS['global_counter'] ++;
             	$GLOBALS['current'] ++;
            	
            	
				$dataArray = $scraper->processRule($dataBlock, array('td', 'html-elements'),false);
				
				if(isset($dataArray[0])){
				
					
					$urlParams = parse_url($current_settings->last_url);
					$meth = explode("/",$urlParams['path']);
					$meth = end($meth);
					$saveDataTo = strtolower(preg_replace('/[^A-Za-z0-9\-]/','',$GLOBALS['source_domain']))."/".$meth;
					
					require_once(realpath(__DIR__)."/".$meth.".rules.php");
					$processedData = array();
					
					$processor = new ProcessData();
					$processedData = $processor->doProcess($dataArray,$current_settings);					
					
					if(!empty($processedData['_id'])){
	                 			 	
			        	 //Save Scrapped Data to database 
			           
			            if (!$this->saveScrapedDataToElastic($processedData,$saveDataTo)) {
		                    $broken = 1;
		                    $nextPageAllowed = 0; //we reached the oldest item
		                }
		                	              
			        }else{
				    	$GLOBALS['global_error_counter'] ++;
				    }
					
				}else{
					$GLOBALS['global_error_counter'] ++;
				}
				
				////////asdfasdfasdf
				if($GLOBALS['index_src'] == 'old'){
			    	
			    	
			    	$nextPageUrl = trim($scraper->processRule($contents, array('li.pager-next a', 'href')));
			        $index_url = $current_settings->base_url.$nextPageUrl;
			    	
			    	$curr_pag = trim($scraper->processRule($contents, array('li.pager-current', 'html-elements')));
			    	
			    	
			    	if ((!empty($nextPageUrl)) && ($GLOBALS['current'] == $GLOBALS['total_items_from_page'])) {
		               $contents  = $scraper->downloadPage($index_url, $cookiesJar);
		               $nextPageAllowed = 1;
					   	$np_pag = trim($scraper->processRule($contents, array('li.pager-current', 'html-elements')));
			    	
			             xprint("Page number: ".$np_pag.PHP_EOL,"yellow");
		            }
		            
		            // save last page crawled
		            $current_settings = new stdClass();
					$current_settings = json_decode(file_get_contents($GLOBALS['scraper_conf_path']));
		
		           $current_settings->last_run_time = time();
		           $current_settings->last_url = $index_url;
		           $current_settings->total_parsed_items = $GLOBALS['global_counter'];
		    	   $current_settings->succes_parsed_items = $GLOBALS['global_success_counter'];
		    	   $current_settings->warning_parsed_items = $GLOBALS['global_warning_counter'];
		    	   $current_settings->error_parsed_items = $GLOBALS['global_error_counter'];
		    	   $current_settings->state = "running";
		    	   $current_settings->curr_page = $curr_pag;
		    	   
		    	 
				   file_put_contents($GLOBALS['scraper_conf_path'], json_encode($current_settings));
		        	
		        	
		    	}else{
		    	   //update in indexes when was accessed crawler last time and wich was the last stopped page
		    	   
		    	   $current_settings = new stdClass();
				   $current_settings = json_decode(file_get_contents($GLOBALS['scraper_conf_path']));
		    	   
		    	   $current_settings->last_run_time = time();
		    	   $current_settings->total_parsed_items = $GLOBALS['global_counter'];
		    	   $current_settings->succes_parsed_items = $GLOBALS['global_success_counter'];
		    	   $current_settings->warning_parsed_items = $GLOBALS['global_warning_counter'];
		    	   $current_settings->error_parsed_items = $GLOBALS['global_error_counter'];
		    	   $current_settings->state = "running";
		    	 
				   file_put_contents($GLOBALS['scraper_conf_path'], json_encode($current_settings));
		    	   
		    	   $nextPageAllowed = 0;
		    	}
				//////////asdfasdfsadf
				
				
				//Verify if was reached the maximum allowed number of successful scrapped data
		    	if($GLOBALS['scrapped_item_limits'] == $GLOBALS['global_success_counter'])
		    	{
		    		xprint("THE SCRAPPING PROCESS HAS BEEN STOPPED - ","red");
			    	xprint("The maximum number of ".$GLOBALS['scrapped_item_limits']." allowed successful scrapped items was reached".PHP_EOL);
			    	$nextPageAllowed = 0; 
			    	break;
		    	}
							
			}
			
			$GLOBALS['current'] = 0;
		}
		
        
	 	//		print_r($htmlContents);
	 			
	 		
	 	//$_res = strip_tags($scraper->processRule($ret, array('span[id="result_box"]', 'html-elements')));
	 			
	       
	        
        return $_res;    
	}
	
	private function getShortTranslation(){
        $addr = $this->url.'?text='.urlencode($this->text)."&sl=".$this->sl."&tl=".$this->tl;
        $str = file_get_contents($addr);
        return $str;    		  
	}
		
	private function getTranslation(){
		
		$post_data = "&text=".$this->text;
		$post_data .= "&sl=".$this->sl;
		$post_data .= "&tl=".$this->tl;
		$post_data .= "&js=n";
		$post_data .= "&prev=_t";
		$post_data .= "&hl=".$this->tl;
		$post_data .= "&ie=UTF-8";
		$post_data .= "&oe=UTF-8";
				
		//mobile useragent
		//$agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 5_0 like Mac OS X) AppleWebKit/534.46 (KHTML, like Gecko) Version/5.1 Mobile/9A334 Safari/7534.48.3';
		
		//latest chrome useragent 16.08.13
		$agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.95 Safari/537.36';
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$this->url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		$result=curl_exec($ch);
		curl_close($ch);
		
	    return $result;
	}
	
	 private function getAdvancedTranslation(){
            
        $post_data = "&text=".urlencode($this->text);
		$post_data .= "&sl=".$this->sl;
		$post_data .= "&tl=".$this->tl;
		$post_data .= "&js=n";
		$post_data .= "&prev=_t";
		$post_data .= "&hl=".$this->tl;
		$post_data .= "&ie=UTF-8";
		$post_data .= "&oe=UTF-8";
          
          while ($this->attempts > 0) {
              
              $this->attempts--;
              
              //curl_unit
              $ch = curl_init();
              curl_setopt($ch, CURLOPT_URL, $this->url);
              curl_setopt($ch, CURLOPT_POST, 1);
              curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
              
              //proxy_settings
              
              if($this->use_proxies == true){
                    
                    $proxies = json_decode(file_get_contents($this->proxies_list),true);
                    $proxy_id = mt_rand(0,count($proxies)-1);
                    $proxy =  $proxies[$proxy_id];
                
                 if(!empty($proxy)){
                        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 0);
                        curl_setopt($ch, CURLOPT_PROXY, $proxy);
                   /*
                    if($this->use_proxy_auth == true){
                        //curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC||CURLAUTH_DIGEST||CURLAUTH_GSSNEGOTIATE||CURLAUTH_NTLM||CURLAUTH_ANY||CURLAUTH_ANYSAFE);
                        curl_setopt($ch, !empty($this->preffered_proxy_auth)? $this->preffered_proxy_auth : "CURLOPT_HTTPAUTH");
                        curl_setopt ($ch, CURLOPT_PROXYUSERPWD, $this->proxy_userpwd); 
                    }
                   */
                }
               
              }
              //EOF proxy_settings
    
              curl_setopt($ch, CURLOPT_REFERER, $this->referer);
              curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
              curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
              curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
              curl_setopt($ch, CURLOPT_TIMEOUT, 30);
              
              //UserAgent settings
              //if($this->agents_rand == true && $this->use_proxies != true){
              if($this->agents_rand == true){
              	$agents = json_decode(file_get_contents($this->agents_list),true);
                    $agent_id = mt_rand(0,count($agents)-1);
                    $this->agent = $agents[$agent_id];
              }
              curl_setopt($ch, CURLOPT_USERAGENT, $this->agent);
              //EOF UserAgent settings
              
              $data = curl_exec($ch);
              
              // Check if any error occurred
              if(!curl_errno($ch))
              {
               //$info = curl_getinfo($ch);
               //echo 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url'];
               break;
              }else{
                //echo 'Curl error: ' . curl_error($ch);
              }
              curl_close($ch);
          }
        //return requested data
        return !empty($data)? $data : false;
    }
			
}

?>
