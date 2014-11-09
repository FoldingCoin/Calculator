<?php
//snapshot.php - Ingest daily FAH snapshot into database
//incorporate getter, with file exists check
//v10 allow folders to append wallet to user name - with bug fixes
//Copyrght © 2014 FoldingCoin, All Rights Reserved



include('functions.php');
include('db.php');
$db=dbConnect();

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
$lastSnapQuery="SELECT * FROM fahcredits WHERE mode = '$mode' ORDER BY id DESC LIMIT 1";
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

//take this out when done testing
//$localFile='20141015daily_user_summary.txt.bz2';


$bz = bzopen($localFile, "r") or die("Couldn't open $localFile");

$lines='';
$lastFileChunk='';
$fileChunk='';

while (!feof($bz)) {
	$fileChunk = $lastFileChunk.bzread($bz, 4096);
	$lines=explode("\n",$fileChunk);
	$lastFileChunk=$lines[count($lines)-1];
	foreach($lines as $line){
		if($line!=$lastFileChunk){
			if(!preg_match("/ /",$line)){
				list($name,$credits,$workUnits,$team)=explode("\t",$line);
				if(preg_match("/\_FLDC\_/",$name)){
					echo "about to explode -$name-\n";
					list($username,$wallet)=explode("_FLDC_",$name);
					if(checkAddress($wallet)==1){
						insertCredits($wallet,$credits,$snapId,$insertTimestamp,$mode,$db,$username);
					}
				}elseif($team==$ourTeam){
					//echo "$name has $credits credits.\n";
					$username=$ourTeam;
					insertCredits($name,$credits,$snapId,$insertTimestamp,$mode,$db,$username);
				}
			}elseif(preg_match("/ PDT /",$line)){
				$insertTimestamp=strtotime($line);
			}
		}
	}
}
bzclose($bz);

function insertCredits($name,$credits,$snapId,$insertTimestamp,$mode,$db,$username){

	if ($stmt = $db->prepare("INSERT INTO fahcredits (snapshotId,timestamp,address,cumulativeCredits,mode,friendlyName) VALUES(?,?,?,?,?,?)")) {
		echo "insert $snapId,$insertTimestamp,$name,$credits,$mode,$username\n";
		/* bind parameters for markers */
		$stmt->bind_param("iisiss", $snapId,$insertTimestamp,$name,$credits,$mode,$username);

		/* execute query */
		$stmt->execute();

		/* close statement */
		$stmt->close();
	}


}


?>