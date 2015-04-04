<?php

class TimeZoneHandler{ 

	private $server_timezone;
	private $contest_timezone;
	private $server_date_object;
	private $contest_date_object;
	private $timezone_hours_offset;
	private $server_start_time_adjusted;
	private $configuration;
	private $status;
	private $output;

	public function TimeZoneHandler( $config = NULL){

		if(!isset($config)){
			$this->configuration = array();
		}
		
		$this->configuration = $config;
		$this->output = $this->configuration;

		$this->server_timezone = new DateTimeZone(ini_get('date.timezone'));

		$this->contest_timezone = new DateTimeZone( ((isset($this->configuration['promo_timezone']) )?$this->configuration['promo_timezone']:'America/New_York') );
		
		$this->server_date_object = new DateTime("now", $this->server_timezone);
		$this->contest_date_object = new DateTime("now", $this->contest_timezone);
		$this->timezone_hours_offset = $this->server_timezone->getOffset($this->server_date_object) - $this->contest_timezone->getOffset($this->contest_date_object);
		$this->server_start_time_adjusted = $this->server_date_object->getTimestamp() - $this->timezone_hours_offset;
		$this->status = 200;
		
	}

	public function getTimeOffsetBetween($timezone1,$timezone2){
			$offset = new StdClass();

			$tz1 = new DateTimeZone($timezone1);
			$tz2 = new DateTimeZone($timezone2);
			$tz1_now = new DateTime("now", $tz1);
			$tz1_now = new DateTime("now", $tz1);

			$offset->milliseconds = $tz1->getOffset($tz1_now) - $tz2->getOffset($tz1_now);
			$offset->hours = $offset->milliseconds/(60*60);
			
			return $offset;
	}

	// outputs to json object by default
	public function getServerTimeAdjusted($json=true){
			$output = new StdClass();
			$output->server_actual = new StdClass();
			$output->server_actual->readable = date('m/d/Y H:i:s', $this->server_date_object->getTimestamp());
			$output->server_actual->timestamp = $this->server_date_object->getTimestamp();
			$output->server_actual->timezone = $this->server_timezone->getName();

			$output->server_adjusted = new StdClass();
			$output->server_adjusted->readable =  date('m/d/Y H:i:s', $this->server_start_time_adjusted);
			$output->server_adjusted->timestamp = $this->server_start_time_adjusted;
			$output->server_adjusted->timezone = $this->contest_timezone->getName();

			$output->contest = new StdClass();

			if(isset($this->configuration["promo_start_timestamp"])){
				$output->contest->start = new stdClass();
				$output->contest->start->readable = date('m/d/Y H:i:s', (strtotime($this->configuration["promo_start_timestamp"])));
				$output->contest->start->timestamp = strtotime($this->configuration["promo_start_timestamp"]);
			}

			if(isset($this->configuration["promo_end_timestamp"])){
				$output->contest->end = new stdClass();
				$output->contest->end->readable = date('m/d/Y H:i:s', (strtotime($this->configuration["promo_end_timestamp"])));
				$output->contest->end->timestamp = strtotime($this->configuration["promo_end_timestamp"]);
			}

			if(isset($output->contest->start)){
				//server time between contest start and end and if an end if defined
				if($output->contest->start->timestamp<=$output->server_adjusted->timestamp && isset($output->contest->end) && $output->contest->end->timestamp>=$output->server_adjusted->timestamp){
					$output->contest->status = "open";	
				}
				//server time greater than contest start and no end set
				elseif($output->contest->start->timestamp<=$output->server_adjusted->timestamp && !isset($output->contest->end)){ //evergreen promotion
					$output->contest->status = "open";	
				}
				else{
					$output->contest->status = "closed";	
				}
			}
			else{
					$output->contest->status = "no contest";	
			}

			$output->contest->timezone = $this->contest_timezone->getName();

			$output->timezone_offset = new stdClass();
			$output->timezone_offset->milliseconds = $this->timezone_hours_offset;
			$output->timezone_offset->hours = ($this->timezone_hours_offset/(60 * 60));

			$this->status = 200;

			if($json){
				return json_encode($output);
			}
			else{
				return $output;	
			}
	}

	public function getHTTPStatus(){
		//return the value from $this->status
		//if it's not set, throw an error like "you can't read status until you do something first!"
		
		return $this->status;
	}
	
}

?>