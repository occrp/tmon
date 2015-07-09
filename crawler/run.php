<?php 
//*************************************************
//Start Configs
set_time_limit(0);

error_reporting(E_ALL); 
ini_set("display_errors", 1); 

require_once(realpath(__DIR__) . '/class/Scraper.class.php');
require_once(realpath(__DIR__) . '/class/ItemHandler.class.php');
require_once(realpath(__DIR__) . '/class/CliColors.class.php');
require_once(realpath(__DIR__) . '/class/CliParser.php');

pcntl_signal(SIGTERM, "signal_handler");
pcntl_signal(SIGINT, "signal_handler");


	$GLOBALS['allow_debug'] = true;
	$GLOBALS['timestamp'] = time();
	$GLOBALS['global_counter'] = 0;
    $GLOBALS['global_success_counter'] = 0;
    $GLOBALS['global_warning_counter'] = 0;
    $GLOBALS['global_error_counter'] = 0;
    $GLOBALS['scrapped_item_limits'] = -1;
    $GLOBALS['current'] = 0;
    $GLOBALS['total_items_from_page'] = 0;
    $GLOBALS['source_domain'] = "";
    $GLOBALS['index_src'] = "";
    $GLOBALS['scraper_conf_path'] = "";

if(version_compare(PHP_VERSION, "5.3.0", '<')){
    // tick use required as of PHP 4.3.0
    declare(ticks = 1);
}
	
	$source = isset($_GET['source']) ? strtolower(preg_replace('/[^A-Za-z0-9\-]/','', $_GET['source'])) : isset($argv[1]) ? strtolower(preg_replace('/[^A-Za-z0-9\-]/','', $argv[1])) : NULL;
	$GLOBALS['source_domain'] = isset($_GET['source']) ? $_GET['source'] : isset($argv[1]) ? $argv[1] : NULL;
	$action = isset($_GET['action']) ? $_GET['action'] : isset($argv[2]) ? $argv[2] : NULL;
	$GLOBALS['allow_debug'] = isset($_GET['origin']) ? $_GET['origin'] : (isset($argv[3]) && ($argv[3] == 'cron')) ? false : true;
	
	if (!isset($source) || !isset($action) ||($action != "new" && $action != "old" )) 
		{	
		 	xprint("Error: Enter valid site name; Action type can take only 'new' or 'old' value! ","red");
			xprint("Example: php ".__FILE__." domain.com new".PHP_EOL);
			return false;
		}
	
	//determine what index collection to choose
	$index_src = ($action == "old") ? "old" : "new"; 
	$GLOBALS['index_src'] = $index_src;
    $GLOBALS['scraper_conf_path'] = realpath(__DIR__)."/scraper_config/".$source.'.json';
	
	$current_settings = json_decode(file_get_contents($GLOBALS['scraper_conf_path']));
	
	if(isset($current_settings))
		$GLOBALS['scrapped_item_limits'] = $current_settings->scrapped_item_limits;

//Eof Configs        
//*************************************************
	

///////////////////////////////////////
////////////Business Logic/////////////
///////////////////////////////////////	

	$lst = time();//Execution timer  
			
	if($current_settings->state && $current_settings->state == "stopped"){
		     
			    
		$classFileName = realpath(realpath(__DIR__))."/scraper_class/".$source.".class.php";    
			 			    
			    $class_name = ucfirst($source);
			    
			    if(file_exists($classFileName)) {
			        
			        require_once($classFileName);
			        $scraperObj = new $class_name;
			        
			        xprint("+++++++ Starting ".$GLOBALS['source_domain']." crawler ++++++++".PHP_EOL);
			        xprint("Last crawled time: ");
			        xprint(date("r",$current_settings->last_run_time).PHP_EOL,"red"); 
			        		        
			        if($action == "old") 
			        {
			        	xprint("Page number: ".$current_settings->curr_page.PHP_EOL,"yellow");
		            }
			      	
			        $status = $scraperObj->process($current_settings);
			    }
	
		 
		print_results();
	
	}else{
	 	 xprint(date("r")." - ERROR: ", "red");
	 	 xprint("This process is already running, please wait 10 minutes before trying again...".PHP_EOL);  
    }

	$lst = time();//Execution timer
			
///////////////////////////////////////
//////////Helpfull Functions///////////
///////////////////////////////////////  

function print_results(){
		
		$current_settings = new stdClass();
		$current_settings = json_decode(file_get_contents($GLOBALS['scraper_conf_path']));
		$current_settings->state = "stopped";
		$current_settings->last_run_time = time();
		
		file_put_contents($GLOBALS['scraper_conf_path'], json_encode($current_settings));
		

		xprint(PHP_EOL."+++++++++++++++++++++++++++ ".$GLOBALS['source_domain']." +++++++++++++++++++++++++++".PHP_EOL, "cyan");
		xprint("TOTAL: ".$GLOBALS['global_counter'].PHP_EOL); 
		xprint("SUCCESS: ".$GLOBALS['global_success_counter'].PHP_EOL, "green");
		xprint("WARNING: ".$GLOBALS['global_warning_counter'].PHP_EOL, "yellow");
		xprint("ERROR: ".$GLOBALS['global_error_counter'].PHP_EOL, "red");
		xprint("SPENT TIME: ". GetTimeDiff($GLOBALS['timestamp']));
		xprint(PHP_EOL."+++++++++++++++++++++++++++ ".$GLOBALS['source_domain']." +++++++++++++++++++++++++++".PHP_EOL, "cyan");
		
}  

function signal_handler($signo){
  
    switch ($signo) {
        case SIGTERM:
        	{//	$GLOBALS['global_counter'].$GLOBALS['global_success_counter'].$GLOBALS['global_error_counter']

            	// handle shutdown tasks
             	print_results(); 
             	xprint(PHP_EOL."The process was terminated by user".PHP_EOL,"red");
             	//if(file_exists($GLOBALS['tmp_downloaded_flv'])) unlink($file);
             	exit();
             	break;
            }
        case SIGINT:
        	{	// handle shutdown tasks
        	 	print_results();		    
        		xprint(PHP_EOL."The process was terminated by user".PHP_EOL,"red");
        		//if(file_exists($GLOBALS['tmp_downloaded_flv'])) unlink($GLOBALS['tmp_downloaded_flv']);
        		exit();
        		break;
        	} 
        default: exit();	           
    }
}

function xprint($s = null, $color = null, $bg = null) {
	
	//if third crawler's parameter will be cron, then nothing will be outputed
	if($GLOBALS['allow_debug']) 
	{
		$cc = & new CliColors();
		echo $cc->getColoredString($s, $color, $bg);
	}		
}			

function GetTimeDiff($timestamp) {
	    $how_log_ago = '';
	    $seconds = time() - $timestamp; 
	    $minutes = (int)($seconds / 60);
	    $hours = (int)($minutes / 60);
	    $days = (int)($hours / 24);
	    if ($days >= 1) {
	      $how_log_ago = $days . ' day' . ($days != 1 ? 's' : '');
	    } else if ($hours >= 1) {
	      $how_log_ago = $hours . ' hour' . ($hours != 1 ? 's' : '');
	    } else if ($minutes >= 1) {
	      $how_log_ago = $minutes . ' minute' . ($minutes != 1 ? 's' : '');
	    } else {
	      $how_log_ago = $seconds . ' second' . ($seconds != 1 ? 's' : '');
	    }
	    return $how_log_ago;
}
	
///////////////////////////////////////
//////Eof Helpfull Functions///////////
///////////////////////////////////////						
?>