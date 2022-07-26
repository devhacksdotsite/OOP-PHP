<?php
class WD {
	public function __construct () {
		$this->req = $_REQUEST;
		$this->safe = $this->clean_data($_REQUEST); 

		$this->server = $this->clean_data($_SERVER);
		$this->ip_address = $_SERVER["REMOTE_ADDR"];
		$this->referer = mysqli_real_escape_string($_SERVER["HTTP_REFERER"]);
		$this->device = $this->device_type();

		// SET COOKIE
		if ($this->req["id"]) {
			setcookie("key", $this->safe["id"], time() + (10 * 365 * 24 * 60 * 60));
			$_SESSION["party_id"] = $this->safe['id']; 
		} else {
			if (isset($_COOKIE["key"])) $_SESSION["party_id"] = $_COOKIE["key"];
		}

		// SESSION setup from URL request vars
		if ($this->req["lang"]) $_SESSION["language"] = $this->safe['lang'];  

		// get url to set relative paths for includes
		$u = explode("/", $_SERVER["REQUEST_URI"]);
		$this->parent_dir = $u[1];
		$this->page = $u[2];

		if (!isset($_SESSION["rsvp_access"])) $_SESSION["rsvp_access"] = 0;
	}

	public function manual_lang_change () {
		$output = array (
			error => 0,
			msg => "success",
		);

		$_SESSION["language"] = $this->safe["picked_lang"];
		$output["lang"] = $_SESSION["language"];

		return $output;
	}

	public function submit_score () {
		$output = array (
			error => 0,
			msg => "success",
			id => $_SESSION["party_id"], 
			ip => $this->ip_address, 
			correctAs => $this->req["correctAnswers"], 
			score => $this->req["score"], 
			correctQs => $this->req["correctQs"]
		);

		$sql = "INSERT INTO quiz_scores SET party_id=UUID_TO_BIN(?), ip=?, correct_count=?, percentage=?, correct_qids=?;";

		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["msg"] = "OOPS! Something went wrong!";

			return $output;
		} 

		$party_id = $_SESSION["party_id"] ? $_SESSION["party_id"] : NULL;
		mysqli_stmt_bind_param($stmt, "sssss", $party_id, $this->ip_address, $this->req["correctAnswers"], $this->req["score"], implode(", ", $this->req["correctQs"]));
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		$output["msg"] = "Success! Score logged.";

		return $output;
	}

	public function init_bish_tracker () {
		$output = array (
			error => 0,
			msg => "Missing party_id"
		);

		if ($_SESSION["party_id"]) { //remove me allow for all tracking once the site is live
			$sql = "INSERT INTO bish_tracker SET party_id=UUID_TO_BIN(?), party_name=(SELECT party_name FROM parties WHERE BIN_TO_UUID(party_id)=?), ip=?, path=?;";

			$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
			if (!mysqli_stmt_prepare($stmt, $sql)) {  
				$output["error"] = 1;	
				$output["msg"] = "OOPS! something went wrong.";
			} else {
				mysqli_stmt_bind_param($stmt, "ssss", $_SESSION["party_id"], $_SESSION["party_id"], $this->ip_address, $_SESSION["path"]);
				mysqli_stmt_execute($stmt);

				mysqli_close($GLOBALS["mysqli"]);
				$output["msg"] = "Success! TI active.";
			}
		}
		 
		return $output;
	}

	public function get_party_info_by_id ($id) {
		$output = array(
			error => 0,
			data => array(),
		);

		$sql = "SELECT BIN_TO_UUID(party_id) AS party_id, party_name FROM parties WHERE BIN_TO_UUID(party_id)=?;";
		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {	
			 $output["error"] = 1;
		} else {
			mysqli_stmt_bind_param($stmt,"s", $id);
			mysqli_stmt_execute($stmt);

			mysqli_stmt_bind_result($stmt, $party_id, $party_name);
			mysqli_stmt_fetch($stmt);

			$output["data"]["party_id"] = $party_id;
			$output["data"]["party_name"] = $party_name;

			mysqli_free_result($stmt);
			mysqli_close($GLOBALS["mysqli"]);
		}

		return $output;
	}

	public function get_session () {
		$output = array(
			party_id => $_SESSION["party_id"],
			language => $_SESSION["language"],
			path => $_SESSION["path"],
			user_email => $_SESSION["users_email"],
			is_logged_in => $_SESSION["logged_in"],
			is_admin => 0,
			rsvp_access => $this->get_rsvp_access_status(),  
			party_attending => $this->get_party_attending_status(),  
			attendees => $this->get_attendees_by_id(),  
			party_details => $this->get_party_details_by_id(),  
		);

		$sql = "SELECT tmin, tmax, day_phrase, night_phrase, link FROM daily_weather_report WHERE DATE_FORMAT(tstamp,'%y-%m-%d') = DATE_FORMAT(NOW(),'%y-%m-%d');";
		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {	
			 $output["error"] = 1;
		} else {
			mysqli_stmt_execute($stmt);

			mysqli_stmt_bind_result($stmt, $tmin, $tmax, $day_phrase, $night_phrase, $link);
			mysqli_stmt_fetch($stmt);

			$output["weather_report"] = array (
				tmin => $tmin,
				tmax => $tmax,
				day_phrase => $day_phrase,
				night_phrase => $night_phrase,
				redirect => $link,
			);

			mysqli_free_result($stmt);
			mysqli_close($GLOBALS["mysqli"]);
		}

		return $output;
	}

	public function get_attendees_by_id() {
		$output = array ();
		if (!isset($_SESSION["party_id"])) {
			return $output;  
		}

		$sql = "SELECT id, attending, CONCAT(firstname, ' ', lastname) AS attendee FROM attendees WHERE BIN_TO_UUID(party_id)=?;";

		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			return $output;
		}

		mysqli_stmt_bind_param($stmt, "s", $_SESSION["party_id"]);
		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);
		while ($row = mysqli_fetch_assoc($result)) array_push($output, $row);
		
		mysqli_stmt_close($stmt);
		return $output;
	}

	public function get_party_details_by_id() {
		$output = array ();
		if (!isset($_SESSION["party_id"])) {
			return $output;  
		}

		$sql = "SELECT BIN_TO_UUID(party_id) AS party_id, party_attending_count, party_diet_restriction, party_diet_restriction_description, song_request_name, song_request_artist, party_email, party_plus_one, party_plus_one_name FROM party_details WHERE BIN_TO_UUID(party_id)=?;";

		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			return $output;
		}

		mysqli_stmt_bind_param($stmt, "s", $_SESSION["party_id"]);
		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);
		if ($row = mysqli_fetch_assoc($result)) $output = $row;
		
		mysqli_stmt_close($stmt);
		return $output;
	}

	public function get_rsvp_access_status() {
		if (!isset($_SESSION["party_id"])) {
			return $_SESSION["rsvp_access"];  
		}

		$sql = "SELECT rsvp_access FROM parties WHERE BIN_TO_UUID(party_id)=?";
		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			return $_SESSION["rsvp_access"];
		}

		mysqli_stmt_bind_param($stmt, "s", $_SESSION["party_id"]);
		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);
		if ($row = mysqli_fetch_assoc($result)) {
			$_SESSION["rsvp_access"] = $row["rsvp_access"];
		}
		
		mysqli_stmt_close($stmt);
		return $_SESSION["rsvp_access"];
	}

	public function get_party_attending_status() {
		if (!isset($_SESSION["party_id"])) {
			return;  
		}

		$sql = "SELECT party_attending FROM parties WHERE BIN_TO_UUID(party_id)=?";
		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			return;
		}

		mysqli_stmt_bind_param($stmt, "s", $_SESSION["party_id"]);
		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);
		if ($row = mysqli_fetch_assoc($result)) {
			mysqli_stmt_close($stmt);
			return $row["party_attending"];
		}
		
		mysqli_stmt_close($stmt);
	}

	public function days_left () {
		$now = time();
		$show_time = strtotime("2022-10-02");
		$days = abs(round(($now - $show_time) / (60 * 60 * 24)));

		if ($_SESSION["language"] == "sp") {
			return $days > 1 ? "¡faltan $days dias!" : "¡falta $days dia!";
		} else {
			return $days > 1 ? "$days days to go!" : "$days day to go!";
		}
	}

	public function save_rsvp () {
		$isAttending = $this->req["stat"];
		$output = array (
			error => 0,
		);

		// UPDATE parties GLOBAL attendance status
		$sql = "UPDATE parties SET party_attending=? WHERE BIN_TO_UUID(party_id)=?";

		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["msg"] = "OOPS! STMT error!";
			return $output;
		}

		mysqli_stmt_bind_param($stmt, "ss", $this->safe["stat"], $_SESSION["party_id"]) ;
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		#$output["msg"] = "Success! You are registered!";
		
		if ($isAttending) {
			// UPDATE attendees
			$attendees = $this->req["party_attendees"];
			foreach($attendees as $attendee) {
				$sql = "UPDATE attendees SET attending=? WHERE id=?";
				$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
				if (!mysqli_stmt_prepare($stmt, $sql)) {
					$output["error"] = 1;
					$output["msg"] = "OOPS! STMT error!";
					return $output;
				}
		
				$attendee = json_decode($attendee);
				mysqli_stmt_bind_param($stmt, "is", $attendee->attending, $attendee->id);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
			}
			
			// UPDATE party_details
			$lang = isset($_SESSION["language"]) ? $_SESSION["language"] : "en";
			$sql = "
				INSERT INTO 
					party_details (
						party_id, 
						language, 
						party_attending_count, 
						party_diet_restriction, 
						party_diet_restriction_description, 
						song_request_name, 
						song_request_artist,
						party_email, 
						party_plus_one, 
						party_plus_one_name
				) VALUES (UUID_TO_BIN(?), ?, ?, ?, ?, ?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					language = VALUES(language),
					party_attending_count = VALUES(party_attending_count),
					party_diet_restriction = VALUES(party_diet_restriction),
					party_diet_restriction_description = VALUES(party_diet_restriction_description),
					song_request_name = VALUES(song_request_name),
					song_request_artist = VALUES(song_request_artist),
					party_email = VALUES(party_email),
					party_plus_one = VALUES(party_plus_one),
					party_plus_one_name = VALUES(party_plus_one_name)
			";

			$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
			if (!mysqli_stmt_prepare($stmt, $sql)) {
				$output["error"] = 1;
				$output["msg"] = "OOPS! PARTY DETAILS STMT error!";
				printf("Error: %s.\n", mysqli_stmt_error($stmt));
				return $output;
			}
			
			mysqli_stmt_bind_param (
				$stmt, 
				"ssisssssss", 
				$_SESSION["party_id"], 
				$lang, 
				$this->safe["party_attending_count"], 
				$this->safe["party_diet_restriction"], 
				$this->safe["party_diet_restriction_description"], 
				$this->safe["song_request_name"], 
				$this->safe["song_request_artist"],
				$this->safe["party_email"], 
				$this->safe["party_plus_one"], 
				$this->safe["party_plus_one_name"]
			);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
		}

		return $output;
	} 


	public function say_hello () {
		return "Hello from class";
	} 

	// CLEAN any given array for safe DB insertion
	public function clean_data($data=array()) {
		if (!count($data)) $data = $this->req; // IF $data is not set, then just pull in all the request vars

		foreach($data as $key => $val) {
			$data[$key] = mysqli_real_escape_string($GLOBALS['mysqli'], $val); 
		}

		return $data;
	}

	public function device_type () {
		$isMobile = preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
		return ($isMobile) ? "mobile" : "desktop";
	}
}

class Admin extends WD {
	public function __construct () {
		parent::__construct();
	}

	public function is_logged_in () {
		$output = array (
			users_email => $_SESSION["users_email"],
			logged_in => $_SESSION["logged_in"],
		);

		return $output;
	}

	public function user_exists ($email) {
		$output = array (
			error => 0,
			msg => "success",
			exists => 0
		);
		$sql = "SELECT * FROM users WHERE users_email=?;";

		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["msg"] = "OOPS! STMT error!";
			return $output;
		}

		mysqli_stmt_bind_param($stmt, "s", $email);
		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);

		if ($row = mysqli_fetch_assoc($result)) {
			$output["error"] = 1;
			$output["msg"] = "OOPS! Looks like this user already exists!";
			$output["exists"] = 1;
			$output["data"] = $row;
		} else {
			$output["error"] = 0;
		}

		mysqli_stmt_close($stmt);
		return $output;
	}

	public function store_new_user ($creds) {
		$output = array (
			error => 0,
			msg => "Success! Admin user created!",
		);

		// hash password
		$hashed = $this->hash_pwd($creds["pwd"]);
		$output["hashed"] = $hashed;
		$sql = "INSERT INTO users SET users_email=?, users_name=?, users_lastname=?, users_pwd=?;";

		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["msg"] =  "OOPS! STMT error!";
			return $output;
		}

		mysqli_stmt_bind_param($stmt, "ssss", $creds["email"], $creds["first_name"], $creds["last_name"], $hashed["hashed"]);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		return $output;
	}

	public function valid_user ($token) {
		$output = array (
			error => 0,
			msg => "success",
			is_admin => 0
		);	

		$sql = "SELECT is_admin FROM parties WHERE BIN_TO_UUID(party_id)=?;";
		
		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["msg"] = "OOPS! STMT ERROR!";
			return $output;
		}

		mysqli_stmt_bind_param($stmt, "s", $token);
		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);

		if ($row = mysqli_fetch_assoc($result)) {
			$output["is_admin"] = $row["is_admin"];
		}

		mysqli_stmt_close($stmt);
		return $output;
	}

	public function hash_pwd ($pwd) {
		$output = array (
			pwd => $pwd,
		);

		$output["hashed"] = password_hash($pwd, PASSWORD_DEFAULT);

		return $output;
	}


	public function check_pwd ($pwd, $hashed) {
		$output = array (
			hashed => $hashed,
			pwd => $pwd,
		);

		$output["match"] = password_verify($pwd, $hashed);

		return $output;
	}

	public function register_user () {
		$output = array (
			error => 0,
			msg => "",
		);

		// Admin token
		$valid_user = $this->valid_user($this->safe["token"]);
		if (!$valid_user["is_admin"]) {
			$output["error"] = 1;
			$output["msg"] = "You do not have permission to create an admin account.";
		} else {
			//create user
			$creds = array (
				email => $this->safe["email"],
				first_name => $this->safe["firstName"],
				last_name => $this->safe["lastName"],
				pwd => $this->req["password"]
			);

			$exists = $this->user_exists($creds["email"]);

			if ($exists["error"]) {
				$output["error"] = $exists["error"];
				$output["msg"] = $exists["msg"];
			} else {
				$new_user = $this->store_new_user($creds);	

				$output["error"] = $new_user["error"];
				$output["msg"] = $new_user["msg"];
			}
		}

		return $output;
	}

	public function login () {
		$output = array (
			error => 0,
			msg => "Success, you are now being redirected...",	
		);

		$exists = $this->user_exists($this->safe["login_email"]);
		if (!$exists["exists"]) {
			$output["error"] = 1;
			$output["msg"] = "Sorry, email does not match our records.";
		} else {
			$pwd_hashed = $exists["data"]["users_pwd"];
			$output["hashed"] = $pwd_hashed;

			$pwd_input = $_REQUEST["login_pwd"];
			$output["input_pwd"] = $pwd_input;

			$check_pwd = $this->check_pwd($pwd_input, $pwd_hashed);
			if (!$check_pwd["match"]) {
				$output["error"] = 1;
				$output["msg"] = "Password does not match.";
			} else {
				# Set SESSION
				$_SESSION["users_email"] = $exists["data"]["users_email"];
				$_SESSION["logged_in"] = 1;
			}
		}

		return $output;
	}

	public function machine_to_human_time ($time) {
		$time = time() - $time; // to get the time since that moment
		$time = ($time < 1) ? 1 : $time;
		$tokens = array (
			31536000 => 'year',
			2592000 => 'month',
			604800 => 'week',
			86400 => 'day',
			3600 => 'hour',
			60 => 'minute',
			1 => 'second'
		);

		foreach ($tokens as $unit => $text) {
			if ($time < $unit) continue;
			$numberOfUnits = floor($time / $unit);
			return $numberOfUnits.' '.$text.(($numberOfUnits > 1) ? 's': '');
		}
	}

	public function calc_last_visit($key) {
		$output = array (
			error => 0,
			msg => "Success!"
		);

		$sql = "SELECT tstamp FROM bish_tracker WHERE BIN_TO_UUID(party_id) = ? ORDER BY tstamp DESC LIMIT 1;";

		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["msg"] = "OOPS! STMT ERROR!";
			return $output;
		}

		mysqli_stmt_bind_param($stmt, "s", $key);
		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);

		if ($row = mysqli_fetch_assoc($result)) {
			$output["tstamp"] = $row["tstamp"];
		}

		mysqli_stmt_close($stmt);

		$time = strtotime($output["tstamp"]);

		return $this->machine_to_human_time($time).' ago';
	}


	public function fetch_tracker_data ($a, $b) {
		return array (
			error => 0,
			msg => "Success!",
			ta => $this->get_bish_tracker_data($a),
			tb => $this->get_bish_tracker_data($b),
			last_visit_a => $this->calc_last_visit($a),
			last_visit_b => $this->calc_last_visit($b),
			guests => $this->get_guest_list(),
		);
	}

	public function get_guest_list () {
		$output = array (
			error => 0,
			msg => "Success!",
			data => array ()
		);

		$sql = "SELECT BIN_TO_UUID(a.party_id) AS party_id, a.party_name, a.is_admin, b.language, b.guests_attending, b.diet_restrictions FROM parties a LEFT JOIN party_details b ON BIN_TO_UUID(a.party_id) = BIN_TO_UUID(b.party_id);";

		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if(!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["msg"] = "OOPS! STMT ERROR!";
			return $output;
		}

		//mysqli_stmt_bind_param($stmt, "s", $key);
		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);

		while ($row = mysqli_fetch_assoc($result)) array_push($output["data"], $row);

		mysqli_stmt_close($stmt);
		return $output;
	}

	public function get_bish_tracker_data ($key) {
		$output = array (
			error => 0,
			msg => "Success!",
			data => array()
		);

		$sql = "
			SELECT 
			COUNT(id) AS count, 
			DATE_FORMAT(tstamp, '%Y-%m-%d') AS date 
			FROM bish_tracker 
			WHERE BIN_TO_UUID(party_id) = ? 
			AND 
			DATE_FORMAT(tstamp, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') 
			GROUP BY DATE_FORMAT(tstamp, '%Y-%m-%d');
		";

		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if(!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["msg"] = "OOPS! STMT ERROR!";
			return $output;
		}

		mysqli_stmt_bind_param($stmt, "s", $key);
		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);

		while ($row = mysqli_fetch_assoc($result)) array_push($output["data"], $row);

		mysqli_stmt_close($stmt);
		return $output;
	}

	public function get_bish_tracker_data_full ($key) {
		switch ($this->req["key"]) {
			case 'a':
				$key = "key_1";
				break;
			case "b":
				$key = "key_2";
				break;
		}

		$output = array (
			error => 0,
			msg => "Success!",
			data => array()
		);

		$sql = "
			SELECT
			id,
			party_name,
			ip,
			path,
			BIN_TO_UUID(party_id) AS uuid,
			DATE_FORMAT(tstamp, '%Y-%m-%d') AS date 
			FROM bish_tracker 
			WHERE BIN_TO_UUID(party_id) = ?
			ORDER BY tstamp DESC;
			;
		";

		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if(!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["msg"] = "OOPS! STMT ERROR!";
			return $output;
		}

		mysqli_stmt_bind_param($stmt, "s", $key);
		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);

		while ($row = mysqli_fetch_assoc($result)) array_push($output["data"], $row);
		mysqli_stmt_close($stmt);
		return $output;
	}

	public function verify_access_code () {
		$access_code_input = $this->req["access_code"];
		$output = array (
			error => 0,
			msg => "Correct access code",
			is_valid => 1,
		);	

		$sql = "SELECT access_code FROM parties WHERE party_name='ADMIN'";
		
		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["is_valid"] = 0;
			$output["msg"] = "OOPS! STMT ERROR!";
			return $output;
		}

		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);

		if ($row = mysqli_fetch_assoc($result)) {
			if ($access_code_input !== $row["access_code"]) {
				$output["is_valid"] = 0;
				$output["msg"] = "Sorry, invalid access code please contact the Bride/Groom.";
			}
		}

		mysqli_stmt_close($stmt);
		return $output;
	}

	public function retrive_user_info_by_name () {
		$first = $this->safe["firstname"];
		$last = $this->safe["lastname"];
		$output = array (
			error => 0,
			msg => "Sorry, party not found! Please try again.",
			is_valid => 0,
		);	

		$sql = "SELECT BIN_TO_UUID(party_id) AS id FROM attendees WHERE firstname=? AND lastname=?;";
		
		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["is_valid"] = 0;
			$output["msg"] = "OOPS! STMT ERROR!";
			return $output;
		}

		mysqli_stmt_bind_param($stmt, "ss", $first, $last);
		mysqli_stmt_execute($stmt);

		$result = mysqli_stmt_get_result($stmt);

		if ($row = mysqli_fetch_assoc($result)) {
			$id = $row["id"];

			$output["is_valid"] = 1;
			$output["msg"] = "Success, party found. Redirecting now...";
			$output["id"] = $id;

			$this->set_rsvp_access($id);
		}

		mysqli_stmt_close($stmt);
		return $output;
	}

	public function set_rsvp_access($id) {
		$output = array ();
		$sql = "UPDATE parties SET rsvp_access=1 WHERE BIN_TO_UUID(party_id)=?;";

		$stmt = mysqli_stmt_init($GLOBALS["mysqli"]);
		if (!mysqli_stmt_prepare($stmt, $sql)) {
			$output["error"] = 1;
			$output["msg"] = "OOPS! STMT ERORR!";
			return $output;
		}

		mysqli_stmt_bind_param($stmt, "s", $id);
		mysqli_stmt_execute($stmt);
		
		# SET session
		$_SESSION["rsvp_access"] = 1;	
		mysqli_stmt_close($stmt);
	}
}

