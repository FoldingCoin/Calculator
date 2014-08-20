<?php
//snapshot.php - Ingest weekly FAH snapshot into database

//Copyrght © 2014 FoldingCoin, All Rights Reserved



include('functions.php');
include('db.php');
$db=dbConnect();

$mode='liveDaily';
$ourTeam=226728;

echo "========Beginning $mode mode run for team $ourTeam at ".date('c')."\n";


if($mode=='test'){
	$delayDays=0;
	$delayHours=0;
	$delayMinutes=1;
}elseif($mode=='live'){
	$delayDays=6;
	$delayHours=23;
	$delayMinutes=45;
}elseif($mode=='testDaily'){
	$delayDays=0;
	$delayHours=0;
	$delayMinutes=1;
}elseif($mode=='liveDaily'){
	$delayDays=0;
	$delayHours=23;
	$delayMinutes=45;
}
$delayTime=($delayDays*86400)+($delayHours*3600)+($delayMinutes*60);




//find last $snapshotId
$lastSnapId='';
$lastSnapQuery="SELECT * FROM fahcredits WHERE mode = '$mode' ORDER BY timestamp DESC LIMIT 1";
echo "$lastSnapQuery\n";
if ($lastSnapResults = $db->query($lastSnapQuery)) {
	while( $lastSnapRow = $lastSnapResults->fetch_assoc() ){
		$lastSnapId = $lastSnapRow['snapshotId'];
		$lastSnapTimestamp=$lastSnapRow['timestamp'];
		echo "from db $lastSnapId\n";
	}
}

$snapId=$lastSnapId+1;
echo "calculated $snapId database gave $lastSnapId\n";
$timestamp=time();
$NextRun=$lastSnapTimestamp+$delayTime;
if($NextRun>$timestamp){
	echo "Too soon to run since last time, $NextRun > $timestamp.\n";
	exit();
}

$insertTimestamp=$timestamp;

$statsUrls[]='http://fah-web.stanford.edu/daily_user_summary.txt.bz2';
$statsUrls[]='http://fah-web.stanford.edu/daily_team_summary.txt.bz2';

$snapshotStamp=date('Ymd',$timestamp);

foreach($statsUrls as $statsUrl){
	list($discard,$localFile)=explode('edu/',$statsUrl);
	$localFile=$snapshotStamp.$localFile;
	echo "$localFile\n";
	
	if(!file_exists($localFile)){
		echo "Downloading $localFile...\n";
		if (!copy($statsUrl, $localFile)) {
			echo "failed to copy $statsUrl...\n";
		}else{
			echo "Copied $statsUrl to $localFile.\n";
		}
	}else{
		echo "$localFile exists, not downloading ...\n";
	}
}



echo "extracting file for snapId $snapId...\n";
$localFileBase='daily_user_summary.txt.bz2';
$localFile=$snapshotStamp.$localFileBase;

///////////////////take this out after initial snapshot ingest
//$localFile='fahSnapshots/snap1.txt.bz2';



$bz = bzopen($localFile, "r") or die("Couldn't open $localFile");

$lines='';
$lastFileChunk='';
$fileChunk='';

while (!feof($bz)) {
	//echo "{{{{{{{chunkbreak $lastFileChunk }}}}}}}";
	$fileChunk = $lastFileChunk.bzread($bz, 4096);
	$lines=explode("\n",$fileChunk);
	$lastFileChunk=$lines[count($lines)-1];
	foreach($lines as $line){
		if($line!=$lastFileChunk){
			//echo "Raw File - $line\n";
			if(!preg_match("/ /",$line)){
				//echo "Tab - $line\n";
				list($name,$credits,$workUnits,$team)=explode("\t",$line);
				if($team==$ourTeam){
					//echo "$name has $credits credits.\n";
					$snapInsertQuery = "INSERT INTO fahcredits (snapshotId,timestamp,address,cumulativeCredits,mode) VALUES($snapId,$insertTimestamp,'$name',$credits,'$mode')";
					echo "$snapInsertQuery;\n";
					$db->query($snapInsertQuery);
				}
			}elseif(preg_match("/ PDT /",$line)){
				//echo "No Tab -  $line\n";
				//Mon Jul 21 20:20:01 PDT 2014
				$insertTimestamp=strtotime($line);
				//echo "UNIX Time $insertTimestamp\n";
				//echo "convert back to formatted date ".date("c",$insertTimestamp)."\n";
				
				
				/*list($wday,$monText,$day,$time,$tzone,$year)=explode(" ",$line);
				list($hour,$min,$sec)=explode(":",$time);
				$insertTimestamp=mktime($hour,$min,$sec,$day,$year);*/
			}
		}
	}
}
bzclose($bz);




?>