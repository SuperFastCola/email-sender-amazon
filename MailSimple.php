<?php

//header('Content-type: text/plain');

//written with same parameters in mind as php mail
//mail($eml,$subject1,$umcontent,$headers1);
class MailSimple 
{

	public $sender;
	public $sender_name;
	public $recipient;
	public $subject;
	public $admin;
	public $config;
	public $current_directory;
	public $document_root;
	public $adjust_timezone;
	public $awsclient;
	public $sesclient;
	public $aws_message_id;
	public $html_email_code;
	public $send_bcc;
	public $email_content_class_location;
	public $timezone_handler_class_location;
	public $aws_class_location;
	public $config_location;
	public $testing;

	public function __construct(){

		$passed_in_object = func_get_args(); 
		$number_of_args = func_num_args();

		$this->recipient = $passed_in_object[0];
		$this->subject = (isset($passed_in_object[1]))?$passed_in_object[1]:"Generic Email";
		$this->html_email_code = (isset($passed_in_object[2]))?$passed_in_object[2]:NULL;
		$this->send_bcc = (isset($passed_in_object[3]))?$passed_in_object[3]:NULL;
		$this->testing = (isset($passed_in_object[4]))?true:NULL;

		$this->sender = (isset($passed_in_object[5]))?$passed_in_object[5]:"customersupport@nbcusupersolution.com";
		$this->sender_name = (isset($passed_in_object[6]))?$passed_in_object[6]:"YOUR SUPER SOLUTION Promotion Headquarters";	


		$this->emails_sent = 0;
		$this->max_send_per_second = 5;

		$this->admin = array("abaker@hothouseinc.com");
		$this->current_directory = dirname(realpath(__FILE__)) . "/";
		$this->document_root = preg_replace("/api\/lib\//i","",$this->current_directory);

		//get location of files
		$this->config_location = $this->document_root . "config.php";
		$this->aws_class_location = $this->current_directory . "vendor/autoload.php";
		$this->email_content_class_location = $this->current_directory . "EmailContent.php";
		$this->timezone_handler_class_location =  $this->current_directory . "TimeZoneHandler.php";

		//check if config file exists
		if(file_exists($this->config_location)){
			require($this->config_location);
			$this->config = $env_config;
		}
		else{
			die("No config file found at: " . $this->config_location);
		}
		
		//check if aws class file exists
		if(file_exists($this->aws_class_location)){
			require($this->aws_class_location);
			$this->awsclient = Aws\Common\Aws::factory($this->document_root  . $env_config['aws_credentials_file']);
			$this->sesclient = $this->awsclient->get('Ses');
		}
		else{
			die("No AWS class found at: " . $this->aws_class_location);
		}

		$this->mail($this->subject,$this->recipient,$this->html_email_code);
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

	public function buildHTMLCode($htmlsource){
		if(file_exists($this->email_content_class_location)){
			require_once($this->email_content_class_location);
			$this->html_email_code = new EmailContent($this->config,$htmlsource);	
		}
		else{
			die("Emil class found at: " . $this->email_content_class_location);
		}
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
	    		fwrite($handle,$this->html_email_code->getHTMLCode());
	    	}
	        fclose($handle);
	}

	public function mail($subject,$recipient,$html){
		
		if(file_exists($this->timezone_handler_class_location)){
			if(!class_exists('TimeZoneHandler')){
				require($this->timezone_handler_class_location);
			}
		}
		else{
			die("No Timezone Handler Found At: " . $this->timezone_handler_class_location);
		}

		$tz = new TimeZoneHandler();
		$server_time = $tz->getServerTimeAdjusted(false);


		//todays date 
		$curdate = date('y-m-d');

		if(isset($this->sesclient)){

			$issues = "";
			$text="";	

			if(isset($subject) && isset($recipient)){

				try{	

						//email destinations
						$destinations = array();
						$destinations[] = $recipient;

						//create a MIME Bpundary
						$random_hash = 'Hhothouse_PART_' . md5(date('r', $server_time->server_adjusted->timestamp)); 

						$emailtext = 'Return-Path: <' . $this->sender . '>' . "\r\n";
						$emailtext .= 'Reply-To: <' . $this->sender . '>' . "\r\n";
						$emailtext .= 'From: "' . $this->sender_name .  '" <' . $this->sender . '>' . "\r\n";
						$emailtext .= 'To: ' . $recipient . "\r\n";
						$emailtext .= 'Date: ' . date('D, j M Y H:i:s O',$server_time->server_adjusted->timestamp)  ."\r\n";
						$emailtext .= 'Subject: ' . preg_replace("/\n|\r/i","",$subject) . "\r\n";

						//add additional headers
    					if(isset($this->send_bcc)){

    						//add bcc header
    						$emailtext .= 'Bcc: ' . $this->send_bcc . "\r\n";

    						$bcc_split_string = explode(",", $this->send_bcc);

	    					foreach($bcc_split_string as $bemail){
								$destinations[] = trim($bemail);
	    					}

    					}
						$emailtext .= 'Message-ID: <' . substr(md5(date('r', $server_time->server_adjusted->timestamp)),0,5) . '-' . substr(md5(date('r', $server_time->server_adjusted->timestamp)),0,5) .'-' . substr(md5(date('r', $server_time->server_adjusted->timestamp)),0,5) . '@hothouseinc.com>'  ."\r\n";
    					$emailtext .= "X-Priority: 1\r\n";
    					$emailtext .= "X-MSMail-Priority: High\r\n";

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
						$quoted_printable_content = quoted_printable_encode($html);

						if(preg_match("/=09/m",$quoted_printable_content)){
							$emailtext .= quoted_printable_encode($html);	
						}
						else{
							$emailtext .= $html;		
						}

						//end mime boundary with two dashes
						$emailtext .= '--' . $random_hash  . "--";


						if(!isset($this->testing)){

			    			$result = $this->sesclient->sendRawEmail(array(
							    'Source' => $this->sender,
							    'Destinations' => $destinations,
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
						else{
							print_r($emailtext);
						}

				}
				catch(Exception $e){
					if(!isset($this->testing)){
						$this->sendAdminEmail("SES Error" . $server_time->server_adjusted->timestamp,$this->recipient . " - " . $e);
					}
					else{
						print_r($e);
					}
				}
			}
			else{
				die("Recipient and Subject Required");
			}

			if(preg_match("/\w+/",$issues)){
				
				if(!isset($this->testing)){
					$this->sendAdminEmail("SES Issues " . $server_time->server_adjusted->timestamp,$issues);
				}
				else{
					print_r($issues);
				}
			}

			if(isset($result)){
				return $this->aws_message_id = $result->get('MessageId');
			}

		}//end if

	}
}

// //create email object
//$mailer = new MailSimple("abaker@deluxeleuxury.com","Test Email Subject","Fake Code TEST","anthony@deluxeluxury.com",NULL);

// //do these once
// //opens the html file
// $mail-adjust_timezone = true;
// $mail->buildHTMLCode("mail_body_code.html");
// $mail->html_email_code->getImagePath(false,"/");
// $mail->html_email_code->replaceKeyWith("image_directory",$mail->config["CDNRootPath"]);

// $html_template = $mail->html_email_code->getHTMLCode();


// $emails = array();
// $emails[] = "granmontreal@yahoo.com";


// //do your for each loop starting here
// //--------------------------------------------------------

// $i = 0;
// foreach($emails as $e){
// 	//replace custom message
// 	$html = $html_template;

// 	$html = $mail->html_email_code->replaceKeyWith("salutation",("Joe Smith" . $i), $html);
// 	$html = $mail->html_email_code->replaceKeyWith("gift_name","A gift Goes Here" . $i, $html);
// 	$html = $mail->html_email_code->replaceKeyWith("shipping_address","621 North Ave. NE<br/>Suite A-100<br/>Atlanta, GA 30308<br/>" . $i, $html);
// 	$html = $mail->html_email_code->addGooglePixel("UA-49932329-43",$e,$html);
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
