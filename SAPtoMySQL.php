<?php
require_once('../../mypacsci/classes/myFunctions.php');
// SET PACIFIC STANDARD TIME
if (function_exists('date_default_timezone_set')) { date_default_timezone_set('America/Los_Angeles'); }
$this_date = date('Y-m-d'); //date('Y-m-d', strtotime(' -1 day'));

// CONNECTS TO MySQL DATABASE
function jose_connect_sap() {
	$host = 'localhost'; $username = 'root'; $password = 'abc123'; $dbname = 'sap';
	$con = mysql_connect($host, $username, $password); if (!$con){die('Could not connect: ' . mysql_error());} mysql_select_db($dbname, $con);
	return $con;
}
// Converts a TEXT string into an array of elements 
function txt_string_to_array($str,$bool=false,$bool2=false){
	$expr="/\|/";
	$results=preg_split($expr,trim($str));
	if($bool) $results = array_slice($results,0,count($results)-1); // remove the last element of an array (done only if end item is a delimeter)
	if($bool2) $results = array_slice($results,1); // remove the first element of an array (done only if beggining item is a delimeter)
	return preg_replace("/^\"(.*)\"$/","$1",$results);
}
// SAP Qty and ExtAmt text to number
function sap_floatvalue($value) {
	$textTOnum = (stristr($value,'-') !== FALSE) ? "-".floatval(preg_replace("/[^-0-9\.]/","",$value)) : floatval(preg_replace("/[^-0-9\.]/","",$value));
    return $textTOnum;
} 

// DEFINE FOLDER PATH
$folder_path = (isset($_GET["path"])) ? $_GET["path"] : "uploads";
$dir = "./$folder_path/";
$thelist = array(); 							
// Retrieve Documents				
if ($handle = opendir($dir)) { 
	while (false !== ($file = readdir($handle))) { 
		if ($file != "." && $file != ".." && end(explode(".", strtolower($file))) != 'php') { 
			$file = $file; 
			$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
			if ($ext != trim("")) {
				$thelist[] = array('file' => $file, 'filetype' => $ext);
			}
		} 
	}
	closedir($handle);
}

if (count($thelist)>0) { 
	// CALL jose functions
	$con = jose_connect_sap();	
	
	// FIELD NAMES FOR ALL THE TABLES COMPATIBLE WITH THIS APPLICATION
	$fieldNames = array(
	'sd001_backlog'=>"run_date,run_time,load_date,SlsOrder,LineNum,SchLineNum,ShipToNum,ShipToName,DistrictChannel,OrderDate,SOrg,OTyp,HoldCode,Material,MaterialDesc,ProfitCenter,Program,Plant,PurchaseOrder,MRPController,DockDate,ExpediteDate,Division,ShipDate,StockDate,Qty,ExtAmt,CNP",
	'sd002_shipments'=>"run_date,run_time,load_date,SlsOrder,ShipToNum,ShipToName,OrderDate,SOrg,DistrictChannel,OTyp,LineNum,PurchaseOrder,Material,MaterialDesc,MRPController,Program,ProfitCenter,Plant,DockDate,ExpediteDate,AdminName,InvoiceDate,Division,SchLineNum,Qty,ExtAmt,TVC,ShipDate,RCur",
	'sd003_backlog'=>"run_date,run_time,load_date,CustName,Material,MaterialDesc,SlsOrder,LineNum,Qty,QtyReq,DockDate,FirstDueDate,ConfDockDate,Plant,ProfitCenter,MRPController,DistrictChannel,Division,UnitPrice,ExtAmt,OTyp,ProcTy,QOH,CreatedBy,ShipDate,ConfShipDate,MDV,OrderDate,PODate,StockDate,CustReqDate,PH1,PH2,PH3,PH4,PH4DESC,PH5,PH5Descr,PH6,PH6Descr,ProdHier,CustNum,MatlGrp,QtyProc,SUOM,UnitCost,ExtCost,GMamt,GMperc,LC_Curr,CustPOnum,POLine,MRP_Contr,CustMatl,OvSt,ShipToNum,ShipToName,MatlDesc,Status,EndUsrName,EndUsrCtr,SlsMgrNum,LicMissing,VCompFail",
	'sd029_shipments'=>"run_date,run_time,load_date,CustName,CustNum,Material,MaterialDesc,PurchaseOrder,SlsMgrNum,AdminName,DistrictChannel,Division,InvcYR,InvDtFisc,SlsOrder,LineNum,OTyp,Qty,InvcNum,InvoiceDate,CustMatl,ExtAmt,SOrg,Plant,PH1,PH2,PH3,PH4,PH5,PH6,MatlGrp,Suom,LC_UP,ExtCost,LC_UC,LC_GM_amt,GMperc,ProfitCenter,MRPController,MRPContrNm,ProcTyp"
	);
	
	$table_list = array();
	for ($i=0; $i<count($thelist); $i++) {
		$table = "";
		$file = $thelist[$i]['file'];
		$file_path = "$folder_path/$file"; 
		$csvfile = fopen($file_path, 'r');
		// GET REPORT NAME
		$report = fgets($csvfile, 4096);
		
		if (preg_match('/YR0000SD001/',$report) || preg_match('/YR0000SD002/',$report)) { //--> IF BACKLOG OR SHIPMENTS REPORT
			//Set Table and condition
			$table = (preg_match('/YR0000SD001/',$report)) ? "sd001_backlog" : "sd002_shipments";
			$end_of_report = (preg_match('/YR0000SD001/',$report)) ? "Convert Currency to" : "Data Selection Restrictions:"; //--> This notes the end of the report
			
			//Get run_date & run_time
			$run_date = fgets($csvfile, 4096); $run_date = explode("Date:",$run_date); $run_date = date('Y-m-d',strtotime(trim($run_date[1]))); //--> Get report date
			$run_time = fgets($csvfile, 4096); $run_time = explode("Time:",$run_time); $run_time = trim($run_time[1]); //--> Get report time
			
			//--> Checks to see if the report has already been loaded in the database, if yes, it continues with the next file. If not, proceeds to load it in the database.
			$query = "SELECT run_date, run_time FROM $table LIMIT 1";
			$result = mysql_query($query) or die ("Error in query: $query. " . mysql_error()); 
			$pass = (mysql_num_rows($result) > 0) ? false : true; 
			$last_run_date = ''; $last_run_time = '';
			while($row = mysql_fetch_array($result))
			{
				if (($row['run_date'] == $run_date) && ($row['run_time'] == $run_time)) { 
					fclose($csvfile); 	//--> close file
					unlink($file_path); //--> delte file from folder
					echo 'WARNING: File(s) already loaded in database.';
					exit;	//--> End query after it finds one file that might be a duplicate.  
				} else {		
					$last_run_date = date('Ymd',strtotime($row['run_date']));
					$last_run_time = $row['run_time'];
					$pass = true;
				}
			}
			if ($pass) { 
				for ($k=0; $k<5; $k++) { $data = fgets($csvfile, 4096); } //--> removes header junk
				$remove_last_element_in_array = true; //--> This is needed to remove the last "|" at the end of each row, otherwise it creates an extra column
				$remove_first_element_in_array = false; //--> This is needed to remove the first "|" at the beginning of each row, otherwise it creates an extra column
				$divisor = 2; //--> The number of rows the header titles occupy.  This is used for the modulus operator MOD(number,divisor) = 0 or 1 (false or true)
			}
		} else if ((preg_match('/----------/',$report)) || (preg_match('/Open Orders/',$report))) { //--> IF sd003_BACKLOG OR sd029_SHIPMENTS REPORT
			$report = fgets($csvfile, 4096); //--> Read second line to get actual Report Name
			//Set Table and condition
			$table = (preg_match('/Data statistics/',$report)) ? "sd029_shipments" : "sd003_backlog";
			$end_of_report = "XXXXXXXXXXXXXXXXXXXXXXX"; //--> This notes the end of the report
			
			$k_count = ($table == "sd003_backlog") ? 4 : 7;
			for ($k=0; $k<$k_count; $k++) { $data = fgets($csvfile, 4096); } //--> removes header junk
			$last_run_date = ''; $last_run_time = '';
			$run_date = $this_date; $run_time = "00:00:00";
			
			if ($table == "sd003_backlog") {		
				//Get run_date & run_time
				$run_date = fgets($csvfile, 4096); $run_date = explode("Date:",$run_date); $run_date = date('Y-m-d',strtotime(trim($run_date[1]))); //--> Get report date
				$run_time = fgets($csvfile, 4096); $run_time = explode("Time:",$run_time); $run_time = trim($run_time[1]); //--> Get report time	
				for ($k=1; $k<$k_count; $k++) { $data = fgets($csvfile, 4096); } //--> removes header junk
			}
			
			$remove_last_element_in_array = true; //--> This is needed to remove the last "|" at the end of each row, otherwise it creates an extra column
			$remove_first_element_in_array = true; //--> This is needed to remove the first "|" at the beginning of each row, otherwise it creates an extra column
			$divisor = 1; //--> The number of rows the header titles occupy.  This is used for the modulus operator MOD(number,divisor) = 0 or 1 (false or true)
		}
		
		// IF A TABLE WAS IDENTIFY THEN LOAD THE DATA, ELSE DON'T DO ANYTHING
		if ($table != "") { 
			if (($last_run_date != '') && ($last_run_time != '')) {
				mysql_query("DROP TABLE IF EXISTS ".$last_run_date."_".$table);
				mysql_query("CREATE TABLE ".$last_run_date."_".$table." SELECT * FROM ".$table);
			}
			mysql_query("TRUNCATE TABLE $table"); //--> Empty table
			
			$t = 1; $csv_data="";
			while (!feof($csvfile)) { $query=""; 
				$csv_data = fgets($csvfile, 4096); //--> read row as a strip
				//if ((preg_match("/$end_of_report/",trim($csv_data)))) {break;} //--> break only if report has reached the ending
				$csv_array = txt_string_to_array($csv_data,$remove_last_element_in_array,$remove_first_element_in_array); //--> converts string into array based on the delimeter		
				if (count($csv_array) == 0) continue; //--> if(preg_match('/----------/',$csv_array[0])) 
			
				if(($divisor == 1) || (($t % $divisor) == 1)) { //--> If the result is 1 then is true else false
					$query .= "INSERT INTO $table (".$fieldNames[$table].") VALUES('".$run_date."','".$run_time."','".$this_date."'";
				}
				for ($j=0; $j<count($csv_array); $j++) { 
					$csv_array[$j] = mysql_real_escape_string(trim(preg_replace('/[^a-zA-Z0-9\s\'\,\&\#\(\)\si𧧶IS݇Ȗ\.\/\<>_-]/', '', $csv_array[$j]))); 
					// skip records that should not be added to the database
	if((preg_match('/----------/',$csv_array[0])) || (preg_match('/Cust name/',$csv_array[0])) || (preg_match('/Sold to name/',$csv_array[0]))) continue 2;
					$query .= ",'".$csv_array[$j]."'";
				}
				if(!($t % $divisor)) { //--> If the result is zero then make the statement true
					$query .= ")"; 
					$result = mysql_query($query) or die ("Error in query: $query. " . mysql_error()); 
				}
				$t++;
			}
			$table_list[$i] = $table;
			fclose($csvfile);
			unlink($file_path);
			unset($csv_array);
		} else {
			// IF FILE IS NOT APPROPIATE SIMPLY DELETE IT
			fclose($csvfile);
			unlink($file_path);
		}
	}		
	
	// CHECK TO SEE IF DATA WAS LOADED, IF NOT, SEND WARNING MESSAGE
	if (count($table_list)>0) {
		$div_array = array('10'=>'Civil Aerospace', '20'=>'Military Aerospace', '30'=>'Military Non-Aerospace', '40'=>'Energy', '50'=>'Other');
		for ($i=0; $i<count($table_list); $i++) {
			if (($table_list[$i] == "sd001_backlog") || ($table_list[$i] == "sd002_shipments")) {
				$result=mysql_query("SELECT * FROM ".$table_list[$i]);  
				while($row = mysql_fetch_array($result))
				{		
					$id = $row['id'];
					$rowID = $row['SlsOrder'].''.$row['LineNum'].''.$row['SchLineNum']; //--> Uniq ID
					$ti = (stristr($row['ShipToName'],'meggitt') !== FALSE) ? "ICO" : "TRADE"; //--> TRADE_ICO
					$segment = $div_array[$row['Division']]; //--> Segment category
					//--> Set Market category (affects margins)
					if (stristr($row['Material'],'NRE') !== FALSE) { $market = 'NRE'; } 
					else if (trim($row['OTyp']) == 'YRE') { $market = 'CustomerReturn'; }
					else if (trim($row['DistrictChannel']) == '30') { $market = 'MRO'; }
					else if (trim($row['DistrictChannel']) == '10') { $market = 'OEM'; }
					else { $market = 'SPARES'; }
					//--> Get ship date in 'YYYY-mm-dd' format, then get the financial year and month
					$shipDate = date('Y-m-d',strtotime($row['ShipDate'])); 	
					$MshipYr = date('Y',strtotime($shipDate)); 				//--> financial year
					$MshipMo = getFinancial_month($MshipYr,$shipDate);		//--> financial month
					//--> Convert Qty and ExtAmt to integers and other dates to excel readable dates
					$qty = sap_floatvalue($row['Qty']);
					$amt = sap_floatvalue($row['ExtAmt']);
					$dDate = (trim($row['DockDate']) != '0') ? date('Y-m-d',strtotime($row['DockDate'])) : '';
					$eDate = (trim($row['ExpediteDate']) != '0') ? date('Y-m-d',strtotime($row['ExpediteDate'])) : '';
					if (($dDate != '') && ($eDate !='') && ($dDate != $eDate)) { $eCode = ($dDate>$eDate) ? 'E' : 'D'; } else { $eCode = ''; }
					
					//-->  Update table
					$query = "UPDATE ".$table_list[$i]." SET DockDate='$dDate', ExpediteDate='$eDate', Qty='$qty', ExtAmt='$amt', rowID='$rowID', ExpediteCode='$eCode', TRADE_ICO='$ti', Segment='$segment', Market='$market', MshipYr='$MshipYr', MshipMo='$MshipMo' WHERE id='$id'";
					$rup = mysql_query($query) or die ("Error in query: $query. " . mysql_error()); 
				}
				mysql_free_result($result);
			}
			else if (($table_list[$i] == "sd003_backlog") || ($table_list[$i] == "sd029_shipments")) {
				$result=mysql_query("SELECT * FROM ".$table_list[$i]);  
				while($row = mysql_fetch_array($result))
				{		
					$id = $row['id'];
					$rowID = $row['SlsOrder'].''.$row['LineNum']; //--> Uniq ID
					$ti = (stristr($row['CustName'],'meggitt') !== FALSE) ? "ICO" : "TRADE"; //--> TRADE_ICO
					$segment = $div_array[$row['Division']]; //--> Segment category
					//--> Set Market category (affects margins)
					if (stristr($row['Material'],'NRE') !== FALSE) { $market = 'NRE'; } 
					else if (trim($row['OTyp']) == 'YRE') { $market = 'CustomerReturn'; }
					else if (trim($row['DistrictChannel']) == '30') { $market = 'MRO'; }
					else if (trim($row['DistrictChannel']) == '10') { $market = 'OEM'; }
					else { $market = 'SPARES'; }
					//--> Get ship date in 'YYYY-mm-dd' format, then get the financial year and month
					$xship = ($table_list[$i] == "sd003_backlog") ? $row['ShipDate'] : $row['InvoiceDate']; 
					$shipDate = date('Y-m-d',strtotime($xship)); 	
					$MshipYr = date('Y',strtotime($shipDate)); 				//--> financial year
					$MshipMo = getFinancial_month($MshipYr,$shipDate);		//--> financial month
					//--> Convert Qty and ExtAmt to integers and other dates to excel readable dates
					$qty = sap_floatvalue($row['Qty']);
					$amt = sap_floatvalue($row['ExtAmt']);
					$cost = sap_floatvalue($row['ExtCost']);
					//$dDate = (trim($row['DockDate']) != '0') ? date('Y-m-d',strtotime($row['DockDate'])) : '';
					//$eDate = (trim($row['ExpediteDate']) != '0') ? date('Y-m-d',strtotime($row['ExpediteDate'])) : '';
					//if (($dDate != '') && ($eDate !='') && ($dDate != $eDate)) { $eCode = ($dDate>$eDate) ? 'E' : 'D'; } else { $eCode = ''; }
					
					//-->  Update table
					$query = "UPDATE ".$table_list[$i]." SET Qty='$qty', ExtAmt='$amt', ExtCost='$cost', rowID='$rowID', TRADE_ICO='$ti', Segment='$segment', Market='$market', MshipYr='$MshipYr', MshipMo='$MshipMo' WHERE id='$id'";
					$rup = mysql_query($query) or die ("Error in query: $query. " . mysql_error()); 
				}
				mysql_free_result($result);
			}
		}
		$diff = count($thelist) - count($table_list);
		if ($diff == 0) { $output = 'success'; }
		else if ($diff == 1) { $output = "WARNING: One of your files was not loaded in the database!"; }
		else { $output = "WARNING: $diff of your files were not loaded in the database!"; }
		echo $output;
	} else { 		
		echo "WARNING: Your files were not loaded in the database!"; 
	}
	mysql_close($con);	
	unset($thelist);
} else { //--> No files found
	echo 'No Files Found';
	exit;	
}
?>