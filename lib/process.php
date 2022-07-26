<?php
	include_once ("config.php");
	$wd = new WD();
	$admin = new Admin();

	switch ($_REQUEST["action"]) {
		case "submit-score":
			print json_encode($wd->submit_score($params));
			break;
		case "manual-lang-change":
			print json_encode($wd->manual_lang_change());
		case "init-bish-tracker":
			print json_encode($wd->init_bish_tracker());
			break;
		case "get-session":
			print json_encode($wd->get_session());	
			break;
		case "save-rsvp": 
			print json_encode($wd->save_rsvp()); 
			break;
		case "register":
			print json_encode($admin->register_user());
			break;
		case "login":
			print json_encode($admin->login());
			break;
		case "get_portal_data":
			print json_encode($admin->fetch_tracker_data());
			break;	
		case "get_portal_data_full":
			print json_encode($admin->get_bish_tracker_data_full());
			break;	
		case "verify_access_code":
			print json_encode($admin->verify_access_code());
			break;
		case "retrive_user_info_by_name":
			print json_encode($admin->retrive_user_info_by_name());
			break;
	}
?>