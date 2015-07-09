<?php
class ProcessData extends Scraper{
	


public function doProcess($dataArray,$current_settings){
	
	$processedData = array();
	$scraper = new Scraper();
	
	$url = $scraper->processRule($dataArray[0], array('a', 'href'));
	
	$processedData['url'] = $current_settings->base_url.$url;
	$processedData['_id'] = md5($processedData['url']);
	$processedData['numar_intrare'] = trim($dataArray[1]);
	$processedData['data_intrare_UTC'] = trim(strip_tags($dataArray[2]));
	$processedData['timestamp_intrare'] = strtotime($processedData['data_intrare_UTC']);
	$tmp = $scraper->processRule($dataArray[2], array('span', 'content'));
	$processedData['date'] = trim($tmp);
	$processedData['numar_iesire'] = trim($dataArray[3]);
	$processedData['ac_doc'] = trim($dataArray[4]);
	$processedData['tip_doc'] = trim($dataArray[5]);
	$processedData['oa_doc'] = trim($dataArray[6]);
	$processedData['nr_proc_doc'] = trim($dataArray[7]);
	$processedData['cpv3_doc'] = trim($dataArray[8]);
	$processedData['decizie_publicare_doc'] = trim($dataArray[9]);
	$processedData['decizie_executare_doc'] = trim($dataArray[10]);
	$processedData['user_doc'] = trim($dataArray[11]);

	return $processedData;
  }
}
?>