<?php
class ProcessData extends Scraper{
	


public function doProcess($dataArray,$current_settings){
	
	$processedData = array();
	$scraper = new Scraper();
	
	$processedData['_id'] = intval(trim($dataArray[0]));
	$processedData['tip_contract'] = trim($dataArray[1]);
	$processedData['data_intrare_UTC'] = trim(strip_tags($dataArray[2]));
	$processedData['timestamp_intrare'] = strtotime($processedData['data_intrare_UTC']);
	$tmp = $scraper->processRule($dataArray[2], array('span', 'content'));
	$processedData['date'] = trim($tmp);
	$processedData['numarul_procedurii'] = trim($dataArray[3]);
	$processedData['tipul_documentului'] = trim($dataArray[4]);
	$processedData['autoritate_contractanta'] = trim($dataArray[5]);
	$processedData['operator_economic'] = trim($dataArray[6]);
	$processedData['obiectul_achizitiei'] = trim($dataArray[7]);
	$suma = str_replace(array(".",","),array("",","), trim($dataArray[8]));
	$processedData['suma_contractului'] = floatval($suma);
	$processedData['cpv3_doc'] = trim($dataArray[9]);
	$processedData['numar_intrare'] = trim($dataArray[10]);
	$processedData['numar_participanti'] = intval(trim($dataArray[11]));
	$processedData['nr_proc_doc'] = trim($dataArray[12]);
	
	return $processedData;
  }
}
?>