<?php
/***************************************************************************
 *                           usercp_sendpasswd.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id$
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *
 ***************************************************************************/

if ( !defined('IN_PHPBB') )
{
	die("Hacking attempt");
	exit;
}

if ( isset($HTTP_POST_VARS['submit']) )
{
	$username = ( !empty($HTTP_POST_VARS['username']) ) ? trim(strip_tags($HTTP_POST_VARS['username'])) : "";
	$email = ( !empty($HTTP_POST_VARS['email']) ) ? trim(strip_tags(htmlspecialchars($HTTP_POST_VARS['email']))) : "";

	$sql = "SELECT user_id, username, user_email, user_active, user_lang 
		FROM " . USERS_TABLE . " 
		WHERE user_email = '" . str_replace("\'", "''", $email) . "' 
			AND username = '" . str_replace("\'", "''", $username) . "'";
	if ( $result = $db->sql_query($sql) )
	{
		if ( $row = $db->sql_fetchrow($result) )
		{
			if ( $row['user_active'] == 0 )
			{
				message_die(GENERAL_MESSAGE, $lang['No_send_account_inactive']);
			}

			$username = $row['username'];

			$user_actkey = gen_rand_string(true);
			$user_password = gen_rand_string(false);
			
			$sql = "UPDATE " . USERS_TABLE . " 
				SET user_newpasswd = '" .md5($user_password) . "', user_actkey = '$user_actkey' 
				WHERE user_id = " . $row['user_id'];
			if ( !$result = $db->sql_query($sql) )
			{
				message_die(GENERAL_ERROR, "Couldn't update new password information", "", __LINE__, __FILE__, $sql);
			}

			include($phpbb_root_path . 'includes/emailer.'.$phpEx);
			$emailer = new emailer($board_config['smtp_delivery']);

			$email_headers = "From: " . $board_config['board_email'] . "\nReturn-Path: " . $board_config['board_email'] . "\r\n";

			$emailer->use_template("user_activate_passwd", $row['user_lang']);
			$emailer->email_address($row['user_email']);
			$emailer->set_subject();//$lang['New_password_activation']
			$emailer->extra_headers($email_headers);

			$emailer->assign_vars(array(
				"SITENAME" => $board_config['sitename'], 
				"USERNAME" => $username,
				"PASSWORD" => $user_password,
				"EMAIL_SIG" => str_replace("<br />", "\n", "-- \n" . $board_config['board_email_sig']), 

				"U_ACTIVATE" => $server_url . "?mode=activate&act_key=$user_actkey")
			);
			$emailer->send();
			$emailer->reset();

			$template->assign_vars(array(
				"META" => '<meta http-equiv="refresh" content="15;url=' . append_sid("index.$phpEx") . '">')
			);

			$message = $lang['Password_updated'] . "<br /><br />" . sprintf($lang['Click_return_index'],  "<a href=\"" . append_sid("index.$phpEx") . "\">", "</a>");

			message_die(GENERAL_MESSAGE, $message);
		}
		else
		{
			message_die(GENERAL_MESSAGE, $lang['No_email_match']);
		}
	}
	else
	{
		message_die(GENERAL_ERROR, "Couldn't obtain user information for sendpassword", "", __LINE__, __FILE__, $sql);
	}
}
else
{
	$username = "";
	$email = "";
}

//
// Output basic page
//
include($phpbb_root_path . 'includes/page_header.'.$phpEx);

$template->set_filenames(array(
	"body" => "profile_send_pass.tpl",
	"jumpbox" => "jumpbox.tpl")
);

$jumpbox = make_jumpbox();
$template->assign_vars(array(
	"L_GO" => $lang['Go'],
	"L_JUMP_TO" => $lang['Jump_to'],
	"L_SELECT_FORUM" => $lang['Select_forum'],

	"S_JUMPBOX_LIST" => $jumpbox,
	"S_JUMPBOX_ACTION" => append_sid("viewforum.$phpEx"))
);
$template->assign_var_from_handle("JUMPBOX", "jumpbox");

$template->assign_vars(array(
	"USERNAME" => $username,
	"EMAIL" => $email,

	"L_SEND_PASSWORD" => $lang['Send_password'], 
	"L_ITEMS_REQUIRED" => $lang['Items_required'],
	"L_EMAIL_ADDRESS" => $lang['Email_address'],
	"L_SUBMIT" => $lang['Submit'],
	"L_RESET" => $lang['Reset'])
);

$template->pparse("body");

include($phpbb_root_path . 'includes/page_tail.'.$phpEx);
?>
