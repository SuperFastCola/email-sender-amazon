<?php
class EmailContent 
{
	public $config;
	public $data;
	public $image_path;

	public $email_body_code;
	public $email_games_row_code;

	public $current_directory;
	public $document_root;

	public function __construct() {

		$passed_in_connection_object = func_get_args(); 
		$number_of_args = func_num_args();

		//get config settings
		$this->config = $passed_in_connection_object[0];

		$this->current_directory = dirname(realpath(__FILE__)) . "/";
		$this->document_root = preg_replace("/emails\//i","",CURRENT_DIRECTORY);

		//open html code files
		$this->email_body_code = file_get_contents($this->current_directory . $passed_in_connection_object[1]);

		if(isset($passed_in_connection_object[2])){
			$this->email_games_row_code = file_get_contents($this->current_directory . $passed_in_connection_object[2]);
		}
	}

	public function getImagePath($localtest = false,$subdirectory="content/email/"){
		//create image path
		if(isset($this->config['CDNRootPath']) && !$localtest){
			$this->image_path = $this->config['CDNRootPath'] . $subdirectory;
		}
		else{
			$this->image_path = $subdirectory;
		}
	}

	public function createEmailBody($games_data){
		$this->email_body_code = preg_replace("/\|\|image_directory\|\|/i",$this->image_path,$this->email_body_code);

		//replace dollar sign
		$games_data[0]["day_copy"] = preg_replace("/\\$/i","&#36;",$games_data[0]["day_copy"]);

		$this->email_body_code = preg_replace("/\|\|day_copy\|\|/i",$games_data[0]["day_copy"],$this->email_body_code);
		$this->email_body_code = preg_replace("/\|\|daily_id\|\|/i",$games_data[0]["daily_id"],$this->email_body_code);
		$this->email_body_code = preg_replace("/\|\|sent_date\|\|/i",date('m-d-Y',time()),$this->email_body_code);
		
		//$this->email_body_code = preg_replace("/\|\|day_date\|\|/i",date('l F j, Y',strtotime($games_data[0]["game_time"])),$this->email_body_code);
/*
		$row_height = 50;
		$final_row_height = 0;

		$all_rows_html = "";
		$row_html = "";

		foreach($games_data as $g){
			$row_html = $this->email_games_row_code;

			$row_html = preg_replace("/\|\|image_directory\|\|/i",$this->image_path,$row_html);

			$row_html = preg_replace("/\|\|game_link\|\|/i",$g["watchespn_link"],$row_html);			
			$row_html = preg_replace("/\|\|matchup_time\|\|/i",$g["kickoff"],$row_html);
			$row_html = preg_replace("/\|\|team1_name\|\|/i",$g["team_away"],$row_html);
			$row_html = preg_replace("/\|\|team1_icon\|\|/i",$g["team_away_icon_name"],$row_html);

			$row_html = preg_replace("/\|\|team2_name\|\|/i",$g["team_home"],$row_html);
			$row_html = preg_replace("/\|\|team2_icon\|\|/i",$g["team_home_icon_name"],$row_html);

			$all_rows_html .= $row_html;

			$final_row_height+=$row_height;

		}

		$this->email_body_code = preg_replace("/\|\|games_area_height\|\|/i",$final_row_height,$this->email_body_code);
		$this->email_body_code = preg_replace("/\|\|game_rows\|\|/i",$all_rows_html,$this->email_body_code);*/
	}

	public function addMVPDLogo($mvpd,$mvpd_link){
		$this->email_body_code = preg_replace("/\|\|mvpd\|\|/i",$mvpd,$this->email_body_code);
		$this->email_body_code = preg_replace("/\|\|mvpd_link\|\|/i",$mvpd_link,$this->email_body_code);
	}

	public function addEmailAddress($email_address){
		$this->email_body_code = preg_replace("/\|\|user_email\|\|/i",$email_address,$this->email_body_code);
	}

	public function addGooglePixel($mvpd){
		$pixel_tracking = "<img src='http://www.google-analytics.com/collect?v=1&tid=UA-49932329-42&t=event&ec=email&ea=open-" . $mvpd . "&el=" . date("mdY",time()) . "&cm=email&cn=" . $mvpd . "&cid=" . time() . "' alt='Google Analytics' />";
		$this->email_body_code = preg_replace("/\|\|google_tracking\|\|/i",$pixel_tracking,$this->email_body_code);
		
	}

	public function getHTMLCode(){
		return $this->email_body_code;	
	}
}



?>