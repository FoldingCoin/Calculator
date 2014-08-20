<?php
//db.php - contains db configs




function dbConnect(){
	$db_host='myhost';
	$db_user='myuser';
	$db_pass='mypass';
	$db_scehma='myschema_distFLDC';

	/* Connect to a MySQL server */
	$db = new mysqli($db_host, $db_user, $db_pass, $db_scehma);

	if (mysqli_connect_errno()) {
		printf("Can't connect to MySQL Server. Errorcode: %s\n", mysqli_connect_error());
		exit;
	}
	
	return $db;
}












?>