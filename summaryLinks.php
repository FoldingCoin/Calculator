<?php
//summaryLinks.php - Builds links page for FLDC Summaries
//This doesn't build the summaries themselves, these come from calculate.php
//This just provides links to the pre-built summary so a user can click

$summaryURLBase="http://www.foldingcoin.net/FLDC-Daily-Payout-Summaries";
//$summaryURLBase="http://192.168.0.120/FLDC-Daily-Payout-Summaries";

$runTime=time();

//last 7 days
generateLinks(7,"last7days.html",$runTime);

//all 2000 days of FLDC
generateLinks(2000,"completeHistory.html",$runTime);






function generateLinks($days,$linksFileName,$runTime){
	$linksFileHandle=fopen($linksFileName,"w");
	fwrite($linksFileHandle,"<html><head><title>FoldingCoin :: Distribution History</title></head>\n");
	fwrite($linksFileHandle,"<body><h2>FoldingCoin :: Distribution Summary</h2>\n");
	fwrite($linksFileHandle,"<img src=http://www.foldingcoin.net/files/1414/0565/1269/logo.png>\n");

	for($i=0;$i<$days;$i++){
		$summaryStamp=$runTime-($i*86400);
		//echo "$i days back is $summaryStamp\n";
		$summaryFileName=date("Ymd",$summaryStamp).".html";
		$friendlyDate=date("F d, Y",$summaryStamp);
		//echo "$summaryFileName\n";
		if(file_exists($summaryFileName)){
			fwrite($linksFileHandle,"<p><a href=$summaryFileName>Payouts $friendlyDate</a></p>\n");
		}
	}

	fwrite($linksFileHandle,"<p></p>\n");
 	fwrite($linksFileHandle,"<p><a href=completeHistory.html>Complete Payout History (Automated)</a></p>\n");
	fwrite($linksFileHandle,"</body></html>\n");
	fclose($linksFileHandle);

}







?>