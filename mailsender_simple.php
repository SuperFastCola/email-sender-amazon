<?php

date_default_timezone_set('America/New_York');

define('CURRENT_DIRECTORY', dirname(realpath(__FILE__)) . "/"); // need to add trailing slash
define('DOCUMENT_ROOT', preg_replace("/emails\//i","",CURRENT_DIRECTORY)); // need to add trailing slash

use Aws\Common\Aws;

require_once(DOCUMENT_ROOT . "vendor/autoload.php");

$aws = Aws::factory(DOCUMENT_ROOT . $env_config['aws_credentials_file']);

//running command from liquidweb
// /usr/bin/php ./mailsender6.0.php aws-access-token preview 2014/06/12 subject:Email-from-Amazon test
class HothouseMail 
{

	public $sender;
	public $sender_name;
	public $recepient;
	public $admin;
	public $config;
	public $current_directory;
	public $document_root;
	public $adjust_timezone;
	public $awsclient;
	public $sesclient;
	public $aws_message_id;
	public $html_email_coder;

	public function __construct(){

		$passed_in_object = func_get_args(); 
		$number_of_args = func_num_args();

		//get config settings
		$this->config = $passed_in_object[0];
		$this->awsclient = $passed_in_object[1];
		$this->sender = $passed_in_object[2];
		$this->sender_name = (isset($passed_in_object[3]))?$passed_in_object[3]:"Hothouse Inc.";

		$this->emails_sent = 0;
		$this->max_send_per_second = 5;

		//$this->admin = array("abaker@hothouseinc.com");
		$this->admin = array($this->sender);
		$this->sesclient = $this->awsclient->get('Ses');
		$this->current_directory = dirname(realpath(__FILE__)) . "/";
		$this->document_root = preg_replace("/emails\//i","",$this->current_directory);

	}

	public function returnCurrentEmail(){
		global $currentemail;
		return $currentemail;
	}

	public function sendAdminEmail($subject,$body){

		$this->sesclient->sendEmail(array(
		    // Source is required
		    'Source' => $this->sender,
		    // Destination is required
		    'Destination' => array(
		        'ToAddresses' => $this->admin
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
		    'ReplyToAddresses' => array($this->sender),
		    'ReturnPath' => $this->sender
		));
	}

	public function buildHTMLCode($htmlfilename){
		require_once($this->current_directory . "EmailContent.php");
		$this->html_email_coder = new EmailContent($this->config,$htmlfilename);
	}

	public function pushHTMLCodeToFile($filename,$html_code=NULL){
			if(!isset($filename)){
				$filename = "validate.txt";
			}
			$handle = fopen($this->current_directory . $filename,"w");

			if(isset($html_code)){
	    		fwrite($handle,$html_code);
	    	}
	    	else{
	    		fwrite($handle,$this->html_email_coder->getHTMLCode());
	    	}
	        fclose($handle);
	}

	public function mail($subject,$recipient,$html){
		
		
		if(isset($this->adjust_timezone)){
			$timezone_adjust = 5 * 60 * 60;
		}
		else{
			$timezone_adjust = 0;
		}

		//todays date 
		$curdate = date('y-m-d');

		if(isset($this->sesclient)){

			//used for updating the database in eh_dailyrems
			$sent_timestamp = mktime(date("H"),date("i"),date("s"),date("n"),date("j"),date("Y")); 
			$day_timestamp = mktime(0,0,0,date("n"),date("j"),date("Y")) - $timezone_adjust;
			$sent_readable = date('Y-m-d H:i:s',$sent_timestamp);
			$cache_buster = "?t=" . time();

			/*echo $day_timestamp . "\n";
			echo date('Y-m-d H:i:s',$day_timestamp) . "\n";*/
			//echo "\n\n" . $sent_readable . "\n";

			$issues = "";
			$text="";	

			if(isset($subject) && isset($recipient)){

				try{	
						//create a MIME Bpundary
						$random_hash = 'Hhothouse_PART_' . md5(date('r', time())); 

						$emailtext = 'Return-Path: <' . $this->sender . '>' . "\r\n";
						$emailtext .= 'Reply-To: <' . $this->sender . '>' . "\r\n";
						$emailtext .= 'From: "' . $this->sender_name .  '" <' . $this->sender . '>' . "\r\n";
						$emailtext .= 'To: ' . $recipient . "\r\n";
						$emailtext .= 'Subject: ' . $subject . "\r\n";
						$emailtext .= 'Date: ' . date('D, j M Y H:i:s O')  ."\r\n";
						$emailtext .= 'Message-ID: <' . substr(md5(date('r', time())),0,5) . '-' . substr(md5(date('r', time())),0,5) .'-' . substr(md5(date('r', time())),0,5) . '@hothouseinc.com>'  ."\r\n";
						$emailtext .= 'Content-Type: multipart/alternative; ' . "\r\n";
						$emailtext .= "\t" . 'boundary="' . $random_hash . '"' . "\r\n";
						$emailtext .= 'MIME-Version: 1.0' ."\r\n\r\n";

						//need two dashes at start of each boundary
						//I have not implemented the text version
						$emailtext .= '--' . $random_hash . "\r\n";
						$emailtext .= 'Content-Type: text/plain; charset=us-ascii' . "\r\n";
						$emailtext .= 'Content-Transfer-Encoding: 7bit' . "\r\n\r\n\r\n";

						//$emailtext .= 'This is some Email Text' . "\r\n";

						$emailtext .= '--' . $random_hash . "\r\n";
						$emailtext .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
						$emailtext .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n\r\n";

						$html = preg_replace("#(?<!\r)\n#si", "\r\n", $html) . "\r\n"; 

						//use quoted printable content
						$emailtext .= quoted_printable_encode($html);

						//end mime boundary with two dashes
						$emailtext .= '--' . $random_hash  . "--";

		    			$result = $this->sesclient->sendRawEmail(array(
						    'Source' => $this->sender,
						    'Destinations' => array($recipient),
						    // RawMessage is required
						    'RawMessage' => array(
						        // Data is required
						        'Data' => base64_encode($emailtext) //you ABSOLUTELY have to base 64 encode your email data //http://techblog.simoncpu.com/2014/02/awssessesclientsendrawemail-needs-base64.html
						    )
						));

						if(isset($result) && preg_match("/Error/i",$result)){
							$issues .= $result;
						}

				}
				catch(Exception $e){
					$this->sendAdminEmail("SES Error" . (mktime(date("H"),date("i"),date("s"),date("n"),date("j"),date("Y")) - 4 * 60 * 60),$recipient . " - " . $e);
				}
			}
			else{
				//echo "Recipient and Subject Required\n";
			}

			if(preg_match("/\w+/",$issues)){
				$this->sendAdminEmail("SES Issues " . (mktime(date("H"),date("i"),date("s"),date("n"),date("j"),date("Y")) - 4 * 60 * 60),$issues);
			}

			if(isset($result)){
				return $this->aws_message_id = $result->get('MessageId');
			}

		}//end if

/*		if(isset($this->aws_message_id)){
			$this->sendAdminEmail(('Message Sent: ' . date('y/m/d H:i:s')),$recipient . " " . $result->get('MessageId'));
		}*/
	}
}

$mail = new HothouseMail($env_config,$aws,"help@hothouseholiday.com","HotHouse Holiday");
//$this->entry["email"] = "abaker@hothouseinc.com";
$mail->adjust_timezone = true;
$mail->buildHTMLCode("mail_body_code.html");
$mail->html_email_coder->getImagePath(false,"/");
$mail->html_email_coder->replaceKeyWith("image_directory",$mail->config["CDNRootPath"]);

$html = $mail->html_email_coder->getHTMLCode();
$html = $mail->html_email_coder->replaceKeyWith("salutation",$this->entry["contact_name"], $html);
$html = $mail->html_email_coder->replaceKeyWith("gift_name",($gift["title"] . ((isset($this->entry["memo"]))?' - ' . $this->entry["memo"]:'')), $html);
$html = $mail->html_email_coder->replaceKeyWith("shipping_address",$this->entry["address"] ."<br/>" . $this->entry["city"] . ", " . $this->entry["state"] . " " . $this->entry["zip"], $html);
$html = $mail->html_email_coder->addGooglePixel("UA-49932329-43",$this->entry["email"],$html);

//push to file to validate code

//send to recipient with email address
//insert aws_id into DB to track users
//return html code to function for individual user tracking
$aws_id = $mail->mail("Confirmation of your gift",$this->entry["email"],$html);

$mail->sendAdminEmail(('Confirmation Sent: ' . date('y/m/d H:i:s')),"User: " .$this->entry["contact_name"] . " @ " . $this->entry["email"] . " for " . $gift["title"] . ((isset($this->entry["memo"]) )?' - ' . $this->entry["memo"]:'') );


// //create email object
// $mail = new HothouseMail($env_config,$aws,"help@hothouseholiday.com","HotHouse Holiday");

// //do these once
// //opens the html file
// $mail-adjust_timezone = true;
// $mail->buildHTMLCode("mail_body_code.html");
// $mail->html_email_coder->getImagePath(false,"/");
// $mail->html_email_coder->replaceKeyWith("image_directory",$mail->config["CDNRootPath"]);

// $html_template = $mail->html_email_coder->getHTMLCode();


// $emails = array();
// /*$emails[] = "email@email.com";
// $emails[] = "email@email.com";
// $emails[] = "email@email.com";*/
// $emails[] = "email@email.com";
// $emails[] = "email@email.com";

// //do your for each loop starting here
// //--------------------------------------------------------

// $i = 0;
// foreach($emails as $e){
// 	//replace custom message
// 	$html = $html_template;

// 	$html = $mail->html_email_coder->replaceKeyWith("salutation",("Joe Smith" . $i), $html);
// 	$html = $mail->html_email_coder->replaceKeyWith("gift_name","A gift Goes Here" . $i, $html);
// 	$html = $mail->html_email_coder->replaceKeyWith("shipping_address","621 North Ave. NE<br/>Suite A-100<br/>Atlanta, GA 30308<br/>" . $i, $html);
// 	$html = $mail->html_email_coder->addGooglePixel("UA-49932329-43",$e,$html);
// 	$i++;

// 	//push to file to validate code
// 	//$mail->pushHTMLCodeToFile("test.html",$html);

// 	//send to recipient with email address
// 	//insert aws_id into DB to track users
// 	//return html code to function for individual user tracking
// 	echo $aws_id = $mail->mail("TEST - Your Holiday Confirmation",$e,$html); //no spaces on second parameter);
// }
// //--------------------------------------------------------
// // END do foreach loop

?>
