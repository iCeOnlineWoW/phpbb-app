<?php
/***************************************************************************
 *                            index.php [ admin/ ]
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id$
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

define('IN_PHPBB', 1);

// Define some vars
$pane = ( isset($_GET['pane']) ) ? $_GET['pane'] : '';
$update = ( $pane == 'right' ) ? true : false;

// Include files
$phpbb_root_path = '../';
require($phpbb_root_path . 'extension.inc');
require('pagestart.' . $phpEx);

// Do we have any admin permissions at all?
if ( !$auth->acl_get('a_') )
{
	trigger_error('No_admin');
}

// Generate relevant output
if ( isset($_GET['pane']) && $_GET['pane'] == 'top' )
{
	page_header('', '', false);

?>

<table width="100%" cellspacing="0" cellpadding="0" border="0">
	<tr>
		<td><a href="../index.<?php echo $phpEx . $SID; ?>" target="_top"><img src="images/header_left.jpg" width="200" height="60" alt="phpBB Logo" title="phpBB Logo" border="0"/></a></td>
		<td width="100%" background="images/header_bg.jpg" height="60" align="right" nowrap="nowrap"><span class="maintitle"><?php echo $user->lang['Admin_title']; ?></span> &nbsp; &nbsp; &nbsp;</td>
	</tr>
</table>

<?php

	page_footer(false);

}
else if ( isset($_GET['pane']) && $_GET['pane'] == 'left' )
{
	// Cheat and use the meta tag to change some stylesheet info
	page_header('', '<style type="text/css">body {background-color: #98AAB1}</style>', false);

	// Grab module information using Bart's "neat-o-module" system (tm)
	$dir = @opendir('.');

	$setmodules = 1;
	while ( $file = @readdir($dir) )
	{
		if ( preg_match('/^admin_(.*?)\.' . $phpEx . '$/', $file) )
		{
			include($file);
		}
	}

	@closedir($dir);

	unset($setmodules);

?>

<table width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<td width="100%"><table width="100%" cellpadding="4" cellspacing="1" border="0">
			<tr>
				<th class="menu" height="25">&#0187; <?php echo $user->lang['Return_to']; ?></th>
			</tr>
			<tr>
				<td class="row1"><a class="genmed" href="index.<?php echo $phpEx . $SID; ?>&amp;pane=right" target="main"><?php echo $user->lang['Admin_Index']; ?></a></td>
			</tr>
			<tr>
				<td class="row2"><a class="genmed" href="../index.<?php echo $phpEx . $SID; ?>" target="_top"><?php echo $user->lang['Forum_index']; ?></a></td>
			</tr>
<?php

	if ( is_array($module) )
	{
		@ksort($module);
		foreach ( $module as $cat => $action_ary )
		{
			$cat = ( !empty($user->lang[$cat . '_cat']) ) ? $user->lang[$cat . '_cat'] : preg_replace('/_/', ' ', $cat);

?>
			<tr>
				<th class="menu" height="25">&#0187; <?php echo $cat; ?></th>
			</tr>
<?php

			@ksort($action_ary);

			foreach ( $action_ary as $action => $file )
			{
				$action = ( !empty($user->lang[$action]) ) ? $user->lang[$action] : preg_replace('/_/', ' ', $action);

				$cell_bg = ( $cell_bg == 'row1' ) ? 'row2' : 'row1';
?>
			<tr>
				<td class="<?php echo $cell_bg; ?>"><a class="genmed" href="<?php echo $file; ?>" target="main"><?php echo $action; ?></a></td>
			</tr>
<?php

			}
		}
	}

?>
		</table></td>
	</tr>
</table>
</body>
</html>
<?php

	// Output footer but don't include copyright info
	page_footer(false);

}
elseif ( isset($_GET['pane']) && $_GET['pane'] == 'right' )
{
	if ( ( isset($_POST['activate']) || isset($_POST['delete']) ) && !empty($_POST['mark']) )
	{
		if ( is_array($_POST['mark']) )
		{
			$in_sql = '';
			foreach( $_POST['mark'] as $user_id )
			{
				$in_sql .= ( ( $in_sql != '' ) ? ', ' : '' ) . $user_id;
			}

			if ( $in_sql != '' )
			{
				$sql = ( isset($_POST['activate']) ) ? "UPDATE " . USERS_TABLE . " SET user_active = 1 WHERE user_id IN ($in_sql)" : "DELETE FROM " . USERS_TABLE . " WHERE user_id IN ($in_sql)";
				$db->sql_query($sql);

				if ( isset($_POST['delete']) )
				{
					$sql = "UPDATE " . CONFIG_TABLE . "
						SET config_value = config_value - " . sizeof($_POST['mark']) . "
						WHERE config_name = 'num_users'";
					$db->sql_query($sql);
				}

				$log_action = ( isset($_POST['activate']) ) ? 'log_index_activate' : 'log_index_delete';
				add_admin_log($log_action, sizeof($_POST['mark']));
			}
		}
	}
	else if ( isset($_POST['remind']) )
	{

	}
	else if ( isset($_POST['resetonline']) )
	{

	}

	// Get forum statistics
	$total_posts = get_db_stat('postcount');
	$total_topics = get_db_stat('topiccount');
	$total_users = $board_config['num_users'];

	$start_date = $user->format_date($board_config['board_startdate']);

	$boarddays = ( time() - $board_config['board_startdate'] ) / 86400;

	$posts_per_day = sprintf('%.2f', $total_posts / $boarddays);
	$topics_per_day = sprintf('%.2f', $total_topics / $boarddays);
	$users_per_day = sprintf('%.2f', $total_users / $boarddays);

	$avatar_dir_size = 0;

	if ( $avatar_dir = @opendir($phpbb_root_path . $board_config['avatar_path']) )
	{
		while ( $file = @readdir($avatar_dir) )
		{
			if ( $file != '.' && $file != '..' )
			{
				$avatar_dir_size += @filesize($phpbb_root_path . $board_config['avatar_path'] . '/' . $file);
			}
		}
		@closedir($avatar_dir);

		// This bit of code translates the avatar directory size into human readable format
		// Borrowed the code from the PHP.net annoted manual, origanally written by:
		// Jesse (jesse@jess.on.ca)
		if ( $avatar_dir_size >= 1048576 )
		{
			$avatar_dir_size = round($avatar_dir_size / 1048576 * 100) / 100 . ' MB';
		}
		else if ( $avatar_dir_size >= 1024 )
		{
			$avatar_dir_size = round($avatar_dir_size / 1024 * 100) / 100 . ' KB';
		}
		else
		{
			$avatar_dir_size = $avatar_dir_size . ' Bytes';
		}

	}
	else
	{
		// Couldn't open Avatar dir.
		$avatar_dir_size = $user->lang['Not_available'];
	}

	if ( $posts_per_day > $total_posts )
	{
		$posts_per_day = $total_posts;
	}

	if ( $topics_per_day > $total_topics )
	{
		$topics_per_day = $total_topics;
	}

	if ( $users_per_day > $total_users )
	{
		$users_per_day = $total_users;
	}

	// DB size ... MySQL only
	// This code is heavily influenced by a similar routine
	// in phpMyAdmin 2.2.0
	if ( preg_match('/^mysql/', SQL_LAYER) )
	{
		$result = $db->sql_query('SELECT VERSION() AS mysql_version');

		if ( $row = $db->sql_fetchrow($result) )
		{
			$version = $row['mysql_version'];

			if ( preg_match('/^(3\.23|4\.)/', $version) )
			{
				$db_name = ( preg_match('/^(3\.23\.[6-9])|(3\.23\.[1-9][1-9])|(4\.)/', $version) ) ? "`$dbname`" : $dbname;

				$sql = "SHOW TABLE STATUS
					FROM " . $db_name;
				$result = $db->sql_query($sql);

				$dbsize = 0;
				while ( $row = $db->sql_fetchrow($result) )
				{
					if ( $row['Type'] != 'MRG_MyISAM' )
					{
						if ( $table_prefix != '' )
						{
							if ( strstr($row['Name'], $table_prefix) )
							{
								$dbsize += $row['Data_length'] + $row['Index_length'];
							}
						}
						else
						{
							$dbsize += $row['Data_length'] + $row['Index_length'];
						}
					}
				}
			}
			else
			{
				$dbsize = $user->lang['Not_available'];
			}
		}
		else
		{
			$dbsize = $user->lang['Not_available'];
		}
	}
	else if ( preg_match('/^mssql/', SQL_LAYER) )
	{
		$sql = "SELECT ((SUM(size) * 8.0) * 1024.0) as dbsize
			FROM sysfiles";
		$result = $db->sql_query($sql);

		$dbsize = ( $row = $db->sql_fetchrow($result) ) ? intval($row['dbsize']) : $user->lang['Not_available'];
	}
	else
	{
		$dbsize = $user->lang['Not_available'];
	}

	if ( is_int($dbsize) )
	{
		$dbsize = ( $dbsize >= 1048576 ) ? sprintf('%.2f MB', ( $dbsize / 1048576 )) : ( ( $dbsize >= 1024 ) ? sprintf('%.2f KB', ( $dbsize / 1024 )) : sprintf('%.2f Bytes', $dbsize) );
	}

	page_header($user->lang['Admin_Index']);

?>

<script language="Javascript" type="text/javascript">
<!--
	function marklist(status)
	{
		for (i = 0; i < document.inactive.length; i++)
		{
			document.inactive.elements[i].checked = status;
		}
	}
//-->
</script>

<h1><?php echo $user->lang['Welcome_phpBB']; ?></h1>

<p><?php echo $user->lang['Admin_intro']; ?></p>

<h1><?php echo $user->lang['Forum_stats']; ?></h1>

<table class="bg" width="100%" cellpadding="4" cellspacing="1" border="0">
	<tr>
		<th width="25%" nowrap="nowrap" height="25"><?php echo $user->lang['Statistic']; ?></th>
		<th width="25%"><?php echo $user->lang['Value']; ?></th>
		<th width="25%" nowrap="nowrap"><?php echo $user->lang['Statistic']; ?></th>
		<th width="25%"><?php echo $user->lang['Value']; ?></th>
	</tr>
	<tr>
		<td class="row1" nowrap="nowrap"><?php echo $user->lang['Number_posts']; ?>:</td>
		<td class="row2"><b><?php echo $total_posts; ?></b></td>
		<td class="row1" nowrap="nowrap"><?php echo $user->lang['Posts_per_day']; ?>:</td>
		<td class="row2"><b><?php echo $posts_per_day; ?></b></td>
	</tr>
	<tr>
		<td class="row1" nowrap="nowrap"><?php echo $user->lang['Number_topics']; ?>:</td>
		<td class="row2"><b><?php echo $total_topics; ?></b></td>
		<td class="row1" nowrap="nowrap"><?php echo $user->lang['Topics_per_day']; ?>:</td>
		<td class="row2"><b><?php echo $topics_per_day; ?></b></td>
	</tr>
	<tr>
		<td class="row1" nowrap="nowrap"><?php echo $user->lang['Number_users']; ?>:</td>
		<td class="row2"><b><?php echo $total_users; ?></b></td>
		<td class="row1" nowrap="nowrap"><?php echo $user->lang['Users_per_day']; ?>:</td>
		<td class="row2"><b><?php echo $users_per_day; ?></b></td>
	</tr>
	<tr>
		<td class="row1" nowrap="nowrap"><?php echo $user->lang['Board_started']; ?>:</td>
		<td class="row2"><b><?php echo $start_date; ?></b></td>
		<td class="row1" nowrap="nowrap"><?php echo $user->lang['Avatar_dir_size']; ?>:</td>
		<td class="row2"><b><?php echo $avatar_dir_size; ?></b></td>
	</tr>
	<tr>
		<td class="row1" nowrap="nowrap"><?php echo $user->lang['Database_size']; ?>:</td>
		<td class="row2"><b><?php echo $dbsize; ?></b></td>
		<td class="row1" nowrap="nowrap"><?php echo $user->lang['Gzip_compression']; ?>:</td>
		<td class="row2"><b><?php echo ( $board_config['gzip_compress'] ) ? $user->lang['ON'] : $user->lang['OFF']; ?></b></td>
	</tr>
	<!-- tr>
		<td class="row1" colspan="4"><?php echo sprintf($user->lang['Record_online_users'], $board_config['record_online_users'], $user->format_date($board_config['record_online_date'])); ?></td>
	</tr -->
</table>

<h1><?php echo $user->lang['Admin_log']; ?></h1>

<p><?php echo $user->lang['Admin_log_index_explain']; ?></p>

<table class="bg" width="100%" cellpadding="4" cellspacing="1" border="0">
	<tr>
		<th width="15%" height="25" nowrap="nowrap"><?php echo $user->lang['Username']; ?></th>
		<th width="15%"><?php echo $user->lang['IP']; ?></th>
		<th width="20%"><?php echo $user->lang['Time']; ?></th>
		<th width="45%" nowrap="nowrap"><?php echo $user->lang['Action']; ?></th>
	</tr>
<?php

	view_log('admin', $log_data, $log_count, 5);

	for($i = 0; $i < sizeof($log_data); $i++)
	{
		$row_class = ( $row_class == 'row1' ) ? 'row2' : 'row1';

?>
	<tr>
		<td class="<?php echo $row_class; ?>"><?php echo $log_data[$i]['username']; ?></td>
		<td class="<?php echo $row_class; ?>" align="center"><?php echo $log_data[$i]['ip']; ?></td>
		<td class="<?php echo $row_class; ?>" align="center"><?php echo $user->format_date($log_data[$i]['time']); ?></td>
		<td class="<?php echo $row_class; ?>"><?php echo $log_data[$i]['action']; ?></td>
	</tr>
<?php

	}

?>
</table>

<h1><?php echo $user->lang['Inactive_users']; ?></h1>

<p><?php echo $user->lang['Inactive_users_explain']; ?></p>

<form method="post" name="inactive" action="<?php echo "index.$phpEx$SID&amp;pane=right"; ?>"><table class="bg" width="100%" cellpadding="4" cellspacing="1" border="0">
	<tr>
		<th width="45%" height="25" nowrap="nowrap"><?php echo $user->lang['Username']; ?></th>
		<th width="45%"><?php echo $user->lang['Joined']; ?></th>
		<th width="5%" nowrap="nowrap"><?php echo $user->lang['Mark']; ?></th>
	</tr>
<?php

	$sql = "SELECT user_id, username, user_regdate
		FROM " . USERS_TABLE . "
		WHERE user_active = 0
			AND user_id <> " . ANONYMOUS . "
		ORDER BY user_regdate ASC";
	$result = $db->sql_query($sql);

	if ( $row = $db->sql_fetchrow($result) )
	{
		do
		{
			$row_class = ( $row_class == 'row1' ) ? 'row2' : 'row1';

?>
	<tr>
		<td class="<?php echo $row_class; ?>"><a href="<?php echo 'admin_users.' . $phpEx . $SID . '&amp;u=' . $row['user_id']; ?>"><?php echo $row['username']; ?></a></td>
		<td class="<?php echo $row_class; ?>"><?php echo $user->format_date($row['user_regdate']); ?></td>
		<td class="<?php echo $row_class; ?>">&nbsp;<input type="checkbox" name="mark[]" value="<?php echo $row['user_id']; ?>" />&nbsp;</td>
	</tr>
<?php

		}
		while ( $row = $db->sql_fetchrow($result) );

?>
	<tr>
		<td class="cat" colspan="3" height="28" align="right"><input class="liteoption" type="submit" name="activate" value="Activate" />&nbsp; <input class="liteoption" type="submit" name="remind" value="Remind" />&nbsp; <input class="liteoption" type="submit" name="delete" value="Delete" />&nbsp;</td>
	</tr>
<?php

	}
	else
	{

?>
	<tr>
		<td class="row1" colspan="3" align="center"><?php echo $user->lang['No_inactive_users']; ?></td>
	</tr>
<?php

	}

?>
</table>

<table width="100%" cellspacing="1" cellpadding="4" border="0" align="center">
	<tr>
		<td align="right" valign="top" nowrap="nowrap"><b><span class="gensmall"><a href="javascript:marklist(true);" class="gensmall"><?php echo $user->lang['Mark_all']; ?></a> :: <a href="javascript:marklist(false);" class="gensmall"><?php echo $user->lang['Unmark_all']; ?></a></span></b></td>
	</tr>
</table></form>

<?php

	page_footer();

}
else
{
	//
	// Output the frameset ...
	//
	header("Expires: " . gmdate("D, d M Y H:i:s", time()) . " GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Content-type: text/html; charset=" . $user->lang['ENCODING']);

?>
<html>
<head>
<title><?php echo $user->lang['Admin_title']; ?></title>
</head>

<frameset rows="60, *" border="0" framespacing="0" frameborder="NO">
	<frame src="<?php echo "index.$phpEx$SID&amp;pane=top"; ?>" name="title" noresize marginwidth="0" marginheight="0" scrolling="NO">
	<frameset cols="155,*" rows="*" border="2" framespacing="0" frameborder="yes">
		<frame src="<?php echo "index.$phpEx$SID&amp;pane=left"; ?>" name="nav" marginwidth="3" marginheight="3" scrolling="yes">
		<frame src="<?php echo "index.$phpEx$SID&amp;pane=right"; ?>" name="main" marginwidth="0" marginheight="0" scrolling="auto">
	</frameset>
</frameset>

<noframes>
	<body bgcolor="white" text="#000000">
		<p><?php echo $user->lang['No_frames']; ?></p>
	</body>
</noframes>
</html>
<?php

	exit;
}

?>