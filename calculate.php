<?php
//calculate.php
//calculate FLDC distribution

//Copyrght © 2014 FoldingCoin, All Rights Reserved


include('functions.php');
include('db.php');
$db=dbConnect();

$mode='liveDaily';
$ourTeam=226728;

$FLDCToDistribute=500000;

$runTimestamp=time();
$runTimeFormatted=date("c",$runTimestamp);
$runTimeCode=date("Ymd",$runTimestamp);

$lastSnapId='';
$lastSnapQuery="SELECT DISTINCT snapshotId, timestamp FROM fahcredits WHERE mode = '$mode' ORDER BY timestamp DESC LIMIT 2";
if ($lastSnapResults = $db->query($lastSnapQuery)) {
	while( $lastSnapRow = $lastSnapResults->fetch_assoc() ){
		$lastSnapId = $lastSnapRow['snapshotId'];
		$lastSnapTimestamp=$lastSnapRow['timestamp'];
		//echo "from db $lastSnapId\n";
		$snapIds[]=$lastSnapId;
	}
}

$todaySnap=$snapIds[0];
$yesterdaySnap=$snapIds[1];

$delta=0;
$todayCredits=0;
$yesterdayCredits=0;
$totalFoldedCredits=0;
$valid='';
$invalidAddresses='';

$todaySnapQuery="SELECT * FROM fahcredits WHERE mode = '$mode' AND snapshotId = $todaySnap";
if ($todaySnapResults = $db->query($todaySnapQuery)) {
	while( $todaySnapRow = $todaySnapResults->fetch_assoc() ){
		$address = $todaySnapRow['address'];
		$todayCredits = $todaySnapRow['cumulativeCredits'];
		$todaySnapTimestamp = $todaySnapRow['timestamp'];

		//checks if valid bitcoin address
		$valid=checkAddress($address);
		if($valid==''){
			$valid=0;
		}
		

		
		if($valid==1){
			//begin last week credits getter
			$yesterdaySnapQuery="SELECT * FROM fahcredits WHERE mode = '$mode' AND snapshotId = $yesterdaySnap AND address = '$address'";
			if ($yesterdaySnapResults = $db->query($yesterdaySnapQuery)) {
				while( $yesterdaySnapRow = $yesterdaySnapResults->fetch_assoc() ){
					$yesterdayCredits = $yesterdaySnapRow['cumulativeCredits'];
				}
			}//end last week credits getter

			$delta=$todayCredits-$yesterdayCredits;
			
			//$delta>=0 makes us ignore negative folders.
			//not sure how you can actually fold negative credits, suspect FAH stats issues
			//Perhaps FAH can re-allocate credits if you change username but use same PassKey???
			if($delta>=0){
				$totalFoldedCredits=$totalFoldedCredits+$delta;
				echo "$address is valid and delta greater than 0, $delta=$todayCredits-$yesterdayCredits $totalFoldedCredits.\n";
				$folders[]="$address,$delta,$valid";
			}
		}elseif($valid==0){
			$invalidAddresses=$invalidAddresses."$address is not a valid Bitcoin address, $delta folding credits will be forfeited.\n";
			echo "$address is not a valid Bitcoin address, $delta folding credits will be forfeited.\n";
		}
		$delta=0;
		$todayCredits=0;
		$yesterdayCredits=0;
		$valid='';
		$invalidAddresses='';

	}
}

echo "\n\nbegin CSV...\n";

$csv='';
$reportLines='';
foreach($folders as $folder){
	//echo "$folder\n";
	list($address,$delta,$valid)=explode(",",$folder);
	$folderCoins=sprintf("%01.8f",($delta/$totalFoldedCredits)*$FLDCToDistribute);
	$folderPct=sprintf("%01.2f",$delta/$totalFoldedCredits*100);

	//we will use $folderCoins>0, so we don't pay folders with 0 folds
	if($folderCoins>0){
		$csv=$csv."$address,$folderCoins\n";
	}
	
	//don't use if $folderCoins>0, so we can report on folders with 0 folds
	$reportLines[]="$address,$folderCoins,$delta,$folderPct";
	
	/* restore this code to go back to ignoring zero folders
	if($folderCoins>0){
		$csv=$csv."$address,$folderCoins\n";
		$reportLines[]="$address,$folderCoins,$delta,$folderPct";
	}
	*/
}

echo "$csv\n";

if(preg_match("/live/",$mode)){
	$toEmail = "foldingcoin.net@gmail.com,jsewell@wcgwave.ca";
}elseif(preg_match("/test/",$mode)){
	$toEmail = "jsewell@wcgwave.ca";
}


$subject = "FoldingCoin Daily Distribution ".$runTimeFormatted;
$fromEmail = "jsewell@foldingcoin.net";
$body = "<html><body><p>FoldingCoin Payouts for ".$runTimeFormatted."</p>\n<p>Valid Payouts</p>\n<pre>".$csv."</pre>\n<p>List of Invalid Addresses</p>\n<pre>".$invalidAddresses."</pre>\n</body></html>";

$headers = 'From: ' . $fromEmail . "\r\n" . 'X-Mailer: PHP/' . phpversion();
$headers .= 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

$mailResult = mail($toEmail,$subject,$body,$headers);
echo "mailresult $mailResult\n";


//write the HTML for the folder credits/coins summary report

$reportHtml='';
$reportHtml=$reportHtml."<html><head><title>FoldingCoin :: Distribution Summary</title></head>\n";
$reportHtml=$reportHtml."<body><h2>FoldingCoin :: Distribution Summary</h2>\n";
$reportHtml=$reportHtml."<img src=http://www.foldingcoin.net/files/1414/0565/1269/logo.png>\n";
$reportHtml=$reportHtml."<p>Distribution Snapshot dated ".date("c",$todaySnapTimestamp)."</p>\n<p>Previous Snapshot dated ".date("c",$lastSnapTimestamp)."</p>\n";
$reportHtml=$reportHtml."<p>Valid Payouts:</p>\n<table border = 1><tr><th>Folder Address</th><th>Credits Folded This Period</th><th>Percentage</th><th>FLDC Paid</th></tr>\n";

$reportTotalCredits='';
$reportTotalFLDC='';
$reportTotalPct='';
foreach($reportLines as $reportLine){
	list($address,$folderCoins,$delta,$folderPct)=explode(",",$reportLine);
	$reportTotalFLDC=$reportTotalFLDC+$folderCoins;
	$reportTotalCredits=$reportTotalCredits+$delta;
	$reportTotalPct=$reportTotalPct+$folderPct;
	$reportHtml=$reportHtml."<tr><td>$address</td><td>$delta</td><td>$folderPct%</td><td>$folderCoins</td></tr>\n";
}
$reportHtml=$reportHtml."<tr><td>Totals</td><td>$reportTotalCredits</td><td>$reportTotalPct</td><td>".sprintf("%01.0f",$reportTotalFLDC)."</td>\n";
$reportHtml=$reportHtml."</table>\n";
$reportHtml=$reportHtml."<p>List of Invalid Addresses</p>\n<pre>".$invalidAddresses."</pre>\n";
$reportHtml=$reportHtml."<p>This report generated $runTimeFormatted</p>\n";
$reportHtml=$reportHtml."<p><a href=http://foldingcoin.net>Back to FoldingCoin web site</a></p>\n";
$reportHtml=$reportHtml."</body></html>\n";

$summaryHtmlPath="../public_html/FLDC-Daily-Payout-Summaries";
$summaryHtmlFileName="$summaryHtmlPath/$runTimeCode.html";

$summaryHtmlFileHandle=fopen($summaryHtmlFileName,"w");
fwrite($summaryHtmlFileHandle,$reportHtml);
fclose($summaryHtmlFileHandle);

?>