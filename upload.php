<?php
// Define folder path
$folder = (isset($_GET["folder"])) ? $_GET["folder"] : "";
switch ($folder) {
	case ('SAP'): $folder_path = 'uploads/SAP_FILES'; break;
	case ('Forecast'): $folder_path = 'uploads/Forecast_FILES'; break;
	default: $folder_path = 'uploads'; break;
}

// A list of permitted file extensions
$allowed = ($folder == 'SAP') ? array('txt') : array("txt","csv","htm","html","xml","css","doc","dot","docx","xls","xlsx","rtf","ppt","pdf","swf","flv","avi","wmv","mov","jpg","jpeg","gif","png","msg","db","msi");

if(isset($_FILES['filename']) && $_FILES['filename']['error'] == 0){

	$extension = pathinfo($_FILES['filename']['name'], PATHINFO_EXTENSION);

	if(!in_array(strtolower($extension), $allowed)){
		echo '{"status":"error","fName":"'.$_FILES['filename']['name'].'"}';
		exit;
	}

	if(move_uploaded_file($_FILES['filename']['tmp_name'], $folder_path.'/'.$_FILES['filename']['name'])){
		echo '{"status":"success","folder":"'.$folder.'","path":"'.$folder_path.'"}';
		exit;
	}
}
echo '{"status":"error","fName":"error"}';
exit;