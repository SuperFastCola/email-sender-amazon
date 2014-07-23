<?php

//running command from liquidweb
// /usr/bin/php ./mailsend/mailsender5.0.php AMAZONUSERTOKEN preview 2014/06/12 subject:Email-from-Amazon test

date_default_timezone_set('America/New_York');

function output($msg){
	echo $msg . "\n";
}

//http://www.php.net/manual/en/function.set-error-handler.php
function myErrorHandler($errno, $errstr, $errfile, $errline)
{

	echo $errno .  "  " . $errstr .  "  " . $errfile .  "  " . $errline . "\n\n";
    /* Don't execute PHP internal error handler */
    return true;
}

function returnCurrentEmail(){
	global $currentemail;
	return $currentemail;
}

function sendAdminEmail($subject,$body){

	global $client,$replyemail,$adminEmails;

	$client->sendEmail(array(
	    // Source is required
	    'Source' => $replyemail,
	    // Destination is required
	    'Destination' => array(
	        'ToAddresses' => $adminEmails
	    ),
	    // Message is required
	    'Message' => array(
	        // Subject is required
	        'Subject' => array(
	            // Data is required
	            'Data' => $subject,
	            'Charset' => 'UTF-8',
	        ),
	        // Body is required
	        'Body' => array(
	            'Text' => array(
	                // Data is required
	                'Data' => $body,
	                'Charset' => 'UTF-8',
	            )
	        ),
	    ),
	    'ReplyToAddresses' => array($replyemail),
	    'ReturnPath' => $replyemail
	));
}

$matchup_image_location = "http://s3.amazonaws.com/images.yoururl.com/";

define('DOCUMENT_ROOT', dirname(realpath(__FILE__)) . "/"); // need to add trailing slash
define('DOCUMENT_ROOT_CREDS', preg_replace("/(public_html|mailsend)/i","",dirname(realpath(__FILE__)))); // need to add trailing slash

//open stream to standard input
if(!defined('STDIN')){
	define('STDIN',fopen('php://stdin', 'r'));
}

set_error_handler("myErrorHandler");

require_once(DOCUMENT_ROOT_CREDS . "vendor/autoload.php");
require_once(DOCUMENT_ROOT_CREDS . "connection2.0.php");

$replyemail = "watchthematch@espnworldcupcentral.com";
$adminEmails = array("anthony@deluxeluxury.com");

use Aws\Common\Aws;

$aws = Aws::factory(DOCUMENT_ROOT_CREDS . "credentials.php");

$testing = false;
$sendemail = true;
$testdate = NULL;
$currentemail = NULL;
$preview = false;

// Get the client from the builder by namespace
$client = $aws->get('Ses');

if(defined('STDIN')){
  	$token = (isset($argv[1]))?$argv[1]:NULL;

  	foreach($argv as $a){
  		if(preg_match("/^test$/i",$a)){
  			$testing = true;	
  		}

  		if(preg_match("/^noemail$/i",$a)){
  			$sendemail = false;
  		}

  		if(preg_match("/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}$/i",$a)){
  			$testdate = $a;
  		}

  		if(preg_match("/subject:/i",$a)){
  			$testsubject = preg_replace("/subject:/i","",$a);
  		}


  		if(preg_match("/preview/i",$a)){
  			$preview = true;
  		}

  		if(preg_match("/mvpd:/i",$a)){
  			$override_mvpd = preg_replace("/mvpd:/i","",$a);
  		}
  	}

}
else {
	$token = (isset($_REQUEST["token"]))?$_REQUEST["token"]:NULL;
}

//to adjust current time to eastern
$timezone_adjust = 4 * 60 * 60;

//todays date for games
if(isset($_REQUEST['dy'])){
	  $dy=strtotime("January 1st +".($_REQUEST['dy']-1)." days");
	  $curdate2 = date('y/m/d',$dy);
}else{
 	$curdate = date('y/m/d');
}

echo $curdate . "\n";

if(isset($testdate)){
	$curdate = $testdate;	
}

$gmtxt="SELECT * FROM tbl_games WHERE air_date='" . $curdate . "' order by notify ASC";
$myDB->execute($gmtxt);

if(isset($token) && $token=="AMAZONUSERTOKEN" && $myDB->dataRows()>0){

	//used for updating the database in reminder_emails
	$sent_timestamp = mktime(date("H"),date("i"),date("s"),date("n"),date("j"),date("Y")); 
	$day_timestamp = mktime(0,0,0,date("n"),date("j"),date("Y")) - 4 * 60 * 60;
	$sent_readable = date('Y-m-d H:i:s',$sent_timestamp);
	$cache_buster = "?t=" . time();

	echo $day_timestamp . "\n";
	echo date('Y-m-d H:i:s',$day_timestamp) . "\n";

	$issues = "";
	$email_ids = "";
	
	$games=$myDB->fetchArray();

	if(!isset($games[1])){
	    	$games_info[] = $games;
	}
	else{
			$games_info = $games;
	}

	$game_html_code = "";

	for($i=0;$i<sizeof($games_info);$i++){

		$emailcpy=$games_info[$i]["eml_copy"];

		$game_html_code .= "<tr><td class='tblairs' style='font-family: Arial, Helvetica, sans-serif; width:98px; font-size: 14px; height: 102px; font-weight:700; color: #ffffff !important; text-align:center; background-color:#4d4d4d; border-bottom: 2px solid #000000;'><a href='#' class='email_matchtime' style='color: #ffffff; text-decoration: none;'>".$games_info[$i]['air_start']." ET</a></td><td class='tblimg' style='border-bottom: 2px solid #000000;'>";

		//this will be used in body of email on WATCHESPN
		$gamelink = "http://links.espnworldcupcentral.com/?gameid=".$games_info[$i]['game_id']."&amp;src=email&amp;ua=MVPDQA";

	  	$game_html_code .="<a href='". $gamelink ."'>";
	  	$game_html_code .="<img src='"  .  $matchup_image_location  . "game_".$games_info[$i]['game_id'].".jpg" . $cache_buster . "' alt='".$games_info[$i]['team1']." vs. ".$games_info[$i]['team2']."' ";
	  	$game_html_code .="width='367' height='102' border='0' style='display:block; padding:0px; margin:0px;' /></a></td></tr>";
	}

	//create date time stamp with first matchup
	$email_display_time_stamp = strtotime($games_info[0]['day_cpy'] . ", 2014 " . $games_info[0]['air_start']);
	

	//batching to 150 since it takes about 2 seconds to go through process and I want to keep max execution time to 60 for each script
	if($testing){
		$query = "select * from reminder_email where testuser=1";
	}
	else if($preview){
		$query = "select * from reminder_email where testuser=1";
	}
	else{
		$query = "select * from reminder_email left join optout on reminder_email.email=optout.email_optout where reminder_email.last_sent_timestamp<" .  $day_timestamp . " and reminder_email.optout=0 and optout.email_optout is null group by reminder_email.email order by reminder_email.entry_id ASC limit 150";	
	}

	$myDB->execute($query);

	if($myDB->dataRows()>0){

		$emails = $myDB->fetchArray();

		if(!isset($emails[1])){
	    	$emails_addresses[] = $emails;
		}
		else{
			$emails_addresses = $emails;
		}

		//set last_sent_timestamp for records being used in this batch so another process doesn't double-send
		$lock_ids = "";
		for($i=0;$i<sizeof($emails_addresses);$i++){
			$lock_ids .= $emails_addresses[$i]["entry_id"] . ((isset($emails_addresses[$i+1]["entry_id"]))?",":"");
		}
		$query = 'update reminder_email set last_sent_readable="0000-00-00 00:00:00", last_sent_timestamp="' . $day_timestamp .  '" where optout=0 and entry_id in (' . $lock_ids . ')';
		$myDB->execute($query);

		//echo date('y/m/d H:i:s') . " starting<br/>";

		for($i=0,$count=1,$max_send_per_second=1;$i<sizeof($emails_addresses);$i++,$max_send_per_second++,$count++){

			$mvpd=$emails_addresses[$i]["mvpd"];

			if(isset($override_mvpd)){
				$mvpd = $override_mvpd;
			}

			switch($mvpd){
				case 'mvpd1':
					$mvpd_qa='7';
				break;

				case 'mvpd2':
					$mvpd_qa='8';
				break;

				case 'mvpd3':
					$mvpd_qa='10';
				break;
				
				case 'mvpd4':
					$mvpd_qa='12';
				break;

				case 'mvpd5':
					$mvpd_qa='16';
				break;

			}

			if(isset($mvpd_qa)){
				$todaygames=preg_replace("/MVPDQA/m", $mvpd_qa, $game_html_code);
				$gamelink=preg_replace("/MVPDQA/m", $mvpd_qa, $gamelink);
			}

			$text="";		

			$eml=$emails_addresses[$i]["email"];
			$currentemail = $eml;

			//get essential email display code
			include(DOCUMENT_ROOT . "match_rem3.0.php");

			/*if($testing || $preview){
				$handle = fopen(DOCUMENT_ROOT . "validate.html","w");
    	    	fwrite($handle,$html);
        		fclose($handle);
			}*/

			try{	
				if($sendemail){

					//send amazon boiler plate email
					/*$result = $client->sendEmail(array(
					    // Source is required
					    'Source' => $replyemail,
					    // Destination is required
					    'Destination' => array(
					        'ToAddresses' => array($emails_addresses[$i]["email"])
					    ),
					    // Message is required
					    'Message' => array(
					    	'From' => 'ESPN World Cup Central',
					        'Subject' => array(
					            // Data is required
					            'Data' => (isset($testsubject))?$testsubject:('Watch The Match - ' . date('F') . ' ' . date('j') . ', 2014'),
					            'Charset' => 'UTF-8',
					        ),
					        // Body is required
					        'Body' => array(
					            'Text' => array(
					                // Data is required
					                'Data' => $text,
					                'Charset' => 'UTF-8',
					            ),
					            'Html' => array(
					                // Data is required
					                'Data' => $html,
					                'Charset' => 'UTF-8',
					            )
					        ),
					    ),
					    'ReplyToAddresses' => array($replyemail),
					    'ReturnPath' => $replyemail
					));*/

					//create a MIME Bpundary
					$random_hash = 'Hothouse_PART_' . md5(date('r', time())); 

					$emailtext = 'Return-Path: <' . $replyemail . '>' . "\r\n";
					$emailtext .= 'Reply-To: <' . $replyemail . '>' . "\r\n";
					$emailtext .= 'From: "ESPN World Cup Central" <' . $replyemail . '>' . "\r\n";
					$emailtext .= 'To: ' . $emails_addresses[$i]["email"] . "\r\n";
					$emailtext .= 'Subject: ' . ((isset($testsubject))?$testsubject:('Watch The Match - ' . date('F',$email_display_time_stamp) . ' ' . date('j',$email_display_time_stamp) . ', 2014')) . "\r\n";
					$emailtext .= 'Date: ' . date('D, j M Y H:i:s O')  ."\r\n";
					$emailtext .= 'Message-ID: <' . substr(md5(date('r', time())),0,5) . '-' . substr(md5(date('r', time())),0,5) .'-' . substr(md5(date('r', time())),0,5) . '@hothouseincmail.com>'  ."\r\n";
					$emailtext .= 'Content-Type: multipart/alternative; ' . "\r\n";
					$emailtext .= "\t" . 'boundary="' . $random_hash . '"' . "\r\n";
					$emailtext .= 'MIME-Version: 1.0' ."\r\n\r\n";

					//need two dashes at start of each boundary
					//I have not implemented the text version
					$emailtext .= '--' . $random_hash . "\r\n";
					$emailtext .= 'Content-Type: text/plain; charset=us-ascii' . "\r\n";
					$emailtext .= 'Content-Transfer-Encoding: 7bit' . "\r\n\r\n\r\n";

					$emailtext .= '--' . $random_hash . "\r\n";
					$emailtext .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
					$emailtext .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n\r\n";

					$html = preg_replace("#(?<!\r)\n#si", "\r\n", $html) . "\r\n"; 

					//use quoted printable content
					$emailtext .= quoted_printable_encode($html);

					//end mime boundary with two dashes
					$emailtext .= '--' . $random_hash  . "--";
/*
					if($testing || $preview){
						$handle = fopen(DOCUMENT_ROOT . "validate.txt","w");
	    	    		fwrite($handle,$emailtext);
	        			fclose($handle);
        			}
*/
//        			---REACTIVATE----------

        			$result = $client->sendRawEmail(array(
					    'Source' => $replyemail,
					    'Destinations' => array($emails_addresses[$i]["email"]),
					    // RawMessage is required
					    'RawMessage' => array(
					        // Data is required
					        'Data' => base64_encode($emailtext) //you ABSOLUTELY have to base 64 encode your email data //http://techblog.simoncpu.com/2014/02/awssessesclientsendrawemail-needs-base64.html
					    )
					));
					
					if(preg_match("/Error/i",$result)){
						$issues .= $result;
					}
					else{
						$email_ids .= $emails_addresses[$i]["entry_id"] . ((isset($emails_addresses[$i+1]["entry_id"]))?",":"");
					}

					echo $emails_addresses[$i]["email"] . "\n\n";
					
					print_r($result) . "\n\n";

//					-----REACTIVATE--------


				}
			}
			catch(Exception $e){
				sendAdminEmail("SES Error" . (mktime(date("H"),date("i"),date("s"),date("n"),date("j"),date("Y")) - 4 * 60 * 60),returnCurrentEmail() . " - " . $e);
			}


			if(preg_match("/\w+/",$issues)){
				sendAdminEmail("SES Issues " . (mktime(date("H"),date("i"),date("s"),date("n"),date("j"),date("Y")) - 4 * 60 * 60),$issues);
			}


			//if(isset($emails_addresses[($i + 1)]["email"]) ){
			if($max_send_per_second>=5){
			//	echo date('y/m/d H:i:s') . " sleeping<br/>";
				$max_send_per_second = 0;
				sleep(1);
				echo "Restarting-------\n";
			}
		}//end for

		//-REACTIVATE-  
		sendAdminEmail(('Batch Finished at: ' . date('y/m/d H:i:s')),("Batch of " . sizeof($emails_addresses) . " emails finished at " . date('y/m/d H:i:s') . "\n Entries for IDs " . $email_ids));

		if(!$preview){
			//go ahead and updates addresses to be marked as sent today
			//--REACTIVATE-- 
			//final update - only sets last_sent_readable - sort of a confirmation that it is sent
			$query = 'update reminder_email set last_sent_readable="' . $sent_readable .  '" where optout=0 and entry_id in (' . $email_ids . ')';
			//--REACTIVATE-- 
			$myDB->execute($query);
		}

	}//if($myDB->dataRows()>0)
	else{

		echo "Sorry contacts from results have already been sent an email\n";

		$cronjob = "*/3 * * * * /usr/bin/php " . DOCUMENT_ROOT . "get_game_data.php" . "\n";
		$cronfile = '/home/ec2-user/crontab_mailsend.txt';
		file_put_contents($cronfile, $cronjob);
		exec('crontab ' . $cronfile);
	}

}
else{

	if(isset($token) && $token=="AMAZONUSERTOKEN"){
		echo "No games today!";	
	}
	else{
		echo "You need the Amazon AWS_ACCESS_KEY_ID to send this email";
	}
	
}

?>
