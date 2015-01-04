<?php

//running command from liquidweb
// /usr/bin/php ./mailsender6.0.php aws-access-token preview 2014/06/12 subject:Email-from-Amazon test

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

//set this to bucket location
$matchup_image_location = "";

define('CURRENT_DIRECTORY', dirname(realpath(__FILE__)) . "/"); // need to add trailing slash
define('DOCUMENT_ROOT', preg_replace("/emails\//i","",CURRENT_DIRECTORY)); // need to add trailing slash

//open stream to standard input
if(!defined('STDIN')){
	define('STDIN',fopen('php://stdin', 'r'));
}

set_error_handler("myErrorHandler");

require_once(DOCUMENT_ROOT . "config.php");
require_once(DOCUMENT_ROOT . "vendor/autoload.php");
require_once(CURRENT_DIRECTORY . "includes/db.php");
require_once(CURRENT_DIRECTORY . "EmailContent.php");

use Aws\Common\Aws;
$aws = Aws::factory((DOCUMENT_ROOT . $env_config['aws_credentials_file']));

$replyemail = "anthony@deluxeluxury.com";
$adminEmails = array("anthony@deluxeluxury.com");

$testing = false;
$sendemail = true;
$testdate = NULL;
$currentemail = NULL;
$preview = false;

// Get the client from the builder by namespace
$client = $aws->get('Ses');

if(defined('STDIN') && isset($argv)){
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

	if(isset($_REQUEST["test"])){
  			$testing = true;	
  		}

  	if(isset($_REQUEST["date"]) &&  preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/i",$_REQUEST["date"])){
		$testdate = $a;
	}

}

//to adjust current time to eastern
$timezone_adjust = 5 * 60 * 60;
$timezone_adjust = 0;

//todays date for games
$curdate = date('y-m-d');


if(isset($testdate)){
	$curdate = $testdate;	
}

echo $curdate . "\n";

$gmtxt="SELECT * FROM prizes WHERE prize_day='" . $curdate . "'";
$myDB->execute($gmtxt);

if(isset($token) && $token==$env_config['aws_access_token'] && $myDB->dataRows()>0){

	//used for updating the database in sweepsentries
	$sent_timestamp = mktime(date("H"),date("i"),date("s"),date("n"),date("j"),date("Y")); 
	$day_timestamp = mktime(0,0,0,date("n"),date("j"),date("Y")) - $timezone_adjust;
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

	//create date time stamp with first matchup
	$email_display_time_stamp = strtotime($games_info[0]['prize_day']);	

	//batching to 150 since it takes about 2 seconds to go through process and I want to keep max execution time to 60 for each script
	if($testing){
		$query = "select * from emails where testuser=1";
	}
	else if($preview){
		$query = "select * from emails where testuser=1";
	}
	else{
		$query = "select * from emails left join optout on emails.email=optout.email_optout where ";
		$query .= "emails.last_sent_timestamp<" .  $day_timestamp . " and emails.optout=0 and optout.email_optout is null ";
		$query .= "group by emails.email order by emails.rem_id ASC limit 150";
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
			$lock_ids .= $emails_addresses[$i]["rem_id"] . ((isset($emails_addresses[$i+1]["rem_id"]))?",":"");
		}

		if(!$testing){
			$query = 'update sweepsentries set last_sent_readable="0000-00-00 00:00:00", last_sent_timestamp="' . $day_timestamp .  '" where optout=0 and rem_id in (' . $lock_ids . ')';
			$myDB->execute($query);
		}

		//echo date('y/m/d H:i:s') . " starting<br/>";

		for($i=0,$count=1,$max_send_per_second=1;$i<sizeof($emails_addresses);$i++,$max_send_per_second++,$count++){

			if(isset($emails_addresses[$i]["mvpd"])){
				$mvpd=$emails_addresses[$i]["mvpd"];
			}
			$mvpd = "xfinity";

			if(isset($override_mvpd)){
				$mvpd = $override_mvpd;
			}
			
			switch($mvpd){
				default:
					$mvpd_site_link="http://xfinitytv.comcast.net/ondemand";
				break;

			}

			$mustemail = new EmailContent($env_config,"mail_body_code.html");
			$mustemail->getImagePath(false);
			$mustemail->createEmailBody($games_info);
			$mustemail->addEmailAddress($emails_addresses[$i]["email"]);
			$mustemail->addGooglePixel($mvpd);
			
			$html = $mustemail->getHTMLCode();

			$text="";		

			$eml=$emails_addresses[$i]["email"];
			$currentemail = $eml;

			/*if($testing || $preview){
				$handle = fopen(DOCUMENT_ROOT . "validate.html","w");
    	    	fwrite($handle,$html);
        		fclose($handle);
			}*/

			try{	
				if($sendemail){

					//create a MIME Bpundary
					$random_hash = 'Hallmark_PART_' . md5(date('r', time())); 

					$emailtext = 'Return-Path: <' . $replyemail . '>' . "\r\n";
					$emailtext .= 'Reply-To: <' . $replyemail . '>' . "\r\n";
					$emailtext .= 'From: "Hallmark Countdown to Christmas" <' . $replyemail . '>' . "\r\n";
					$emailtext .= 'To: ' . $emails_addresses[$i]["email"] . "\r\n";
					$emailtext .= 'Subject: ' . ((isset($testsubject))?$testsubject:('Unwrap to Win Sweepstakes - ' . date('F',$email_display_time_stamp) . ' ' . date('j',$email_display_time_stamp) . ', 2014')) . "\r\n";
					$emailtext .= 'Date: ' . date('D, j M Y H:i:s O')  ."\r\n";
					$emailtext .= 'Message-ID: <' . substr(md5(date('r', time())),0,5) . '-' . substr(md5(date('r', time())),0,5) .'-' . substr(md5(date('r', time())),0,5) . '@hallmarkunwraptowin.com>'  ."\r\n";
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

					//file_put_contents("email_code.html",$html);

					//end mime boundary with two dashes
					$emailtext .= '--' . $random_hash  . "--";
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
					
					if(isset($result) && preg_match("/Error/i",$result)){
						$issues .= $result;
					}
					else{
						$email_ids .= $emails_addresses[$i]["rem_id"] . ((isset($emails_addresses[$i+1]["rem_id"]))?",":"");
					}

					echo $emails_addresses[$i]["email"] . "\n\n";
					
					if(isset($result)){
						echo $result->get('MessageId') . "\n";
					}

					//sets email as sent for all matching records with email address
					if(!$testing){
						$query = 'update sweepsentries set last_sent_readable="' . $sent_readable .  '",last_sent_timestamp="' . $day_timestamp .  '",aws_message_id="' . $result->get('MessageId') . '" where optout=0 and email regexp"' . $emails_addresses[$i]["email"] . '"';
						$myDB->execute($query);
					}

//					-----REACTIVATE--------


				}
			}
			catch(Exception $e){
				sendAdminEmail("SES Error" . (mktime(date("H"),date("i"),date("s"),date("n"),date("j"),date("Y")) - 4 * 60 * 60),returnCurrentEmail() . " - " . $e);
			}


			if(preg_match("/\w+/",$issues)){
				sendAdminEmail("SES Issues " . (mktime(date("H"),date("i"),date("s"),date("n"),date("j"),date("Y")) - 4 * 60 * 60),$issues);
			}


			if($max_send_per_second>=5){
				$max_send_per_second = 0;
				sleep(1);
				echo "Restarting-------\n";
			}
		}//end for

		//-REACTIVATE-  
		sendAdminEmail(('Batch Finished at: ' . date('y/m/d H:i:s')),("Batch of " . sizeof($emails_addresses) . " emails finished at " . date('y/m/d H:i:s') . "\n Entries for IDs " . $email_ids));

		if(!$testing && preg_match("/\w/",$email_ids) ){
			//go ahead and updates addresses to be marked as sent today
			//--REACTIVATE-- 
			//final update - only sets last_sent_readable - sort of a confirmation that it is sent
			$query = 'update sweepsentries set last_sent_readable="' . $sent_readable .  '" where optout=0 and rem_id in (' . $email_ids . ')';
			$myDB->execute($query);
			//--REACTIVATE-- 
		}

	}//if($myDB->dataRows()>0)
	else{
		echo "Sorry contacts from results have already been sent an email\n";
	}

}
else{
	if(isset($token) && $token==$env_config['aws_access_token']){
		echo "No Prizes today!";	
	}
	else{
		echo "You need the Amazon AWS_ACCESS_KEY_ID to send this email";
	}
	
}

?>
