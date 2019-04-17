<?php
	include 'class-do-volume-backup.php';

	$params = [
		'secret' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
		'vol_name' => 'volume-nyc1-01',
		'vol_region' => 'nyc1'
	];

	$snapshot = new DO_Volume_Backup($params);

	/* Next we can do anything with results.
	 *
	 * I will send short report to my email.
	 * */

	$date = date( 'n/j/Y h:i:s' );
	$headers = 'From: autoreports@mydomain.com';
	$to = 'xod174@gmail.com';

	if ( $snapshot->success ) :

		$subject = 'Digital Ocean Volume snapshot - success';
		$message = 'A new snapshot for volume ' . $params[ 'vol_name' ] . 'has been successfully created at ' . $date;
	else :

		$subject = 'Digital Ocean Volume snapshot - failure';
		$message = 'A new snapshot for volume ' . $params[ 'vol_name' ] . 'has been failed at ' . $date
		          .' with error message: ' . $snapshot->error;
	endif;

	mail( $to, $subject, $message, $headers );