<?php
	$folder = (isset($_GET["folder"])) ? "?folder=".trim($_GET["folder"]) : "";
?>
<!DOCTYPE html>
<html>

	<head>
		<meta charset="utf-8"/>
		<title>Upload Files</title>
		<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>	
		<script src="assets/js/dropzone.js"></script>
		<link rel="stylesheet" href="assets/css/dropzone.css">		
		<link rel="stylesheet" href="assets/css/style.css">
	</head>

	<body>
		<!-- GLOBAL MESSAGES TO THE FORMS ON THIS PAGE --> 
		<div id="sendingMessage" class="statusMessage"><p>Uploading the file(s) to the database. Please wait...</p></div>
		<div id="successMessage" class="statusMessage"><p>The files were successfully loaded in the database!</p></div>
		<div id="failureMessage" class="statusMessage"><p>There was a problem loading the files. Please try again.</p></div>
		
		<div id="upload_container">
			<div id="output_handler"></div>
			<div id="output_handler2"><a class="jUpload_btn"></a></div>
			<div id="dropzone">
			<form class="dropzone" id="upload" method="post" action="upload.php<?php echo $folder; ?>" enctype="multipart/form-data">
			
				<div class="dz-message">
					Drop files here or click to upload.<br />
					<div class="panel panel-default">
						<div class="panel-heading">
							<h3 class="panel-title"></h3>
						</div>
						<div class="panel-body">
							<ul>
								<li>The maximum file size you can upload is <strong>200 MB</strong>.</li>
								<?php 
								if ((isset($_GET["folder"])) && ($_GET["folder"] == 'SAP')) {
									echo '<li>Only (<strong> .txt </strong>) file formats are allowed.</li>';
									echo '<li>Supports SAP backlog and shipment files only.</li>';
								}
								?>
							</ul>
						</div>
					</div>
				</div>
			</form>
			</div>
		</div>
		
	</body>
</html>