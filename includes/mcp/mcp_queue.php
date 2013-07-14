<?php
/**
*
* @package mcp
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* mcp_queue
* Handling the moderation queue
* @package mcp
*/
class mcp_queue
{
	var $p_master;
	var $u_action;

	public function mcp_queue(&$p_master)
	{
		$this->p_master = &$p_master;
	}

	public function main($id, $mode)
	{
		global $auth, $db, $user, $template, $cache, $request;
		global $config, $phpbb_root_path, $phpEx, $action, $phpbb_container;

		include_once($phpbb_root_path . 'includes/functions_posting.' . $phpEx);

		$forum_id = request_var('f', 0);
		$start = request_var('start', 0);

		$this->page_title = 'MCP_QUEUE';

		switch ($action)
		{
			case 'approve':
			case 'restore':
				include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);

				$post_id_list = $request->variable('post_id_list', array(0));
				$topic_id_list = $request->variable('topic_id_list', array(0));

				if (!empty($post_id_list))
				{
					self::approve_posts($action, $post_id_list, 'queue', $mode);
				}
				else if (!empty($topic_id_list))
				{
					self::approve_topics($action, $topic_id_list, 'queue', $mode);
				}
				else
				{
					trigger_error('NO_POST_SELECTED');
				}
			break;

			case 'delete':
				$post_id_list = $request->variable('post_id_list', array(0));
				$topic_id_list = $request->variable('topic_id_list', array(0));

				if (!empty($post_id_list))
				{
					if (!function_exists('mcp_delete_post'))
					{
						global $phpbb_root_path, $phpEx;
						include($phpbb_root_path . 'includes/mcp/mcp_main.' . $phpEx);
					}
					mcp_delete_post($post_id_list, false, '', $action);
				}
				else if (!empty($topic_id_list))
				{
					if (!function_exists('mcp_delete_topic'))
					{
						global $phpbb_root_path, $phpEx;
						include($phpbb_root_path . 'includes/mcp/mcp_main.' . $phpEx);
					}
					mcp_delete_topic($topic_id_list, false, '', $action);
				}
				else
				{
					trigger_error('NO_POST_SELECTED');
				}
			break;

			case 'disapprove':
				$post_id_list = $request->variable('post_id_list', array(0));
				$topic_id_list = $request->variable('topic_id_list', array(0));

				if (!empty($topic_id_list) && $mode == 'deleted_topics')
				{
					if (!function_exists('mcp_delete_topics'))
					{
						global $phpbb_root_path, $phpEx;
						include($phpbb_root_path . 'includes/mcp/mcp_main.' . $phpEx);
					}
					mcp_delete_topic($topic_id_list, false, '', 'disapprove');
					return;
				}

				if (!class_exists('messenger'))
				{
					include($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
				}

				if (!empty($topic_id_list))
				{
					$post_visibility = ($mode == 'deleted_topics') ? ITEM_DELETED : ITEM_UNAPPROVED;
					$sql = 'SELECT post_id
						FROM ' . POSTS_TABLE . '
						WHERE post_visibility = ' . $post_visibility . '
							AND ' . $db->sql_in_set('topic_id', $topic_id_list);
					$result = $db->sql_query($sql);

					$post_id_list = array();
					while ($row = $db->sql_fetchrow($result))
					{
						$post_id_list[] = (int) $row['post_id'];
					}
					$db->sql_freeresult($result);
				}

				if (!empty($post_id_list))
				{
					self::disapprove_posts($post_id_list, 'queue', $mode);
				}
				else
				{
					trigger_error('NO_POST_SELECTED');
				}
			break;
		}

		switch ($mode)
		{
			case 'approve_details':

				$this->tpl_name = 'mcp_post';

				$user->add_lang(array('posting', 'viewtopic'));

				$post_id = request_var('p', 0);
				$topic_id = request_var('t', 0);

				$phpbb_notifications = $phpbb_container->get('notification_manager');

				if ($topic_id)
				{
					$topic_info = get_topic_data(array($topic_id), 'm_approve');
					if (isset($topic_info[$topic_id]['topic_first_post_id']))
					{
						$post_id = (int) $topic_info[$topic_id]['topic_first_post_id'];

						$phpbb_notifications->mark_notifications_read('topic_in_queue', $topic_id, $user->data['user_id']);
					}
					else
					{
						$topic_id = 0;
					}
				}

				$phpbb_notifications->mark_notifications_read('post_in_queue', $post_id, $user->data['user_id']);

				$post_info = get_post_data(array($post_id), 'm_approve', true);

				if (!sizeof($post_info))
				{
					trigger_error('NO_POST_SELECTED');
				}

				$post_info = $post_info[$post_id];

				if ($post_info['topic_first_post_id'] != $post_id && topic_review($post_info['topic_id'], $post_info['forum_id'], 'topic_review', 0, false))
				{
					$template->assign_vars(array(
						'S_TOPIC_REVIEW'	=> true,
						'S_BBCODE_ALLOWED'	=> $post_info['enable_bbcode'],
						'TOPIC_TITLE'		=> $post_info['topic_title'],
					));
				}

				$extensions = $attachments = $topic_tracking_info = array();

				// Get topic tracking info
				if ($config['load_db_lastread'])
				{
					$tmp_topic_data = array($post_info['topic_id'] => $post_info);
					$topic_tracking_info = get_topic_tracking($post_info['forum_id'], $post_info['topic_id'], $tmp_topic_data, array($post_info['forum_id'] => $post_info['forum_mark_time']));
					unset($tmp_topic_data);
				}
				else
				{
					$topic_tracking_info = get_complete_topic_tracking($post_info['forum_id'], $post_info['topic_id']);
				}

				$post_unread = (isset($topic_tracking_info[$post_info['topic_id']]) && $post_info['post_time'] > $topic_tracking_info[$post_info['topic_id']]) ? true : false;

				// Process message, leave it uncensored
				$parse_flags = ($post_info['bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0) | OPTION_FLAG_SMILIES;
				$message = generate_text_for_display($post_info['post_text'], $post_info['bbcode_uid'], $post_info['bbcode_bitfield'], $parse_flags, false);

				if ($post_info['post_attachment'] && $auth->acl_get('u_download') && $auth->acl_get('f_download', $post_info['forum_id']))
				{
					$extensions = $cache->obtain_attach_extensions($post_info['forum_id']);

					$sql = 'SELECT *
						FROM ' . ATTACHMENTS_TABLE . '
						WHERE post_msg_id = ' . $post_id . '
							AND in_message = 0
						ORDER BY filetime DESC, post_msg_id ASC';
					$result = $db->sql_query($sql);

					while ($row = $db->sql_fetchrow($result))
					{
						$attachments[] = $row;
					}
					$db->sql_freeresult($result);

					if (sizeof($attachments))
					{
						$update_count = array();
						parse_attachments($post_info['forum_id'], $message, $attachments, $update_count);
					}

					// Display not already displayed Attachments for this post, we already parsed them. ;)
					if (!empty($attachments))
					{
						$template->assign_var('S_HAS_ATTACHMENTS', true);

						foreach ($attachments as $attachment)
						{
							$template->assign_block_vars('attachment', array(
								'DISPLAY_ATTACHMENT'	=> $attachment,
							));
						}
					}
				}

				// Deleting information
				if ($post_info['post_visibility'] == ITEM_DELETED && $post_info['post_delete_user'])
				{
					// User having deleted the post also being the post author?
					if (!$post_info['post_delete_user'] || $post_info['post_delete_user'] == $post_info['poster_id'])
					{
						$display_username = get_username_string('full', $post_info['poster_id'], $post_info['username'], $post_info['user_colour'], $post_info['post_username']);
					}
					else
					{
						$sql = 'SELECT u.user_id, u.username, u.user_colour
							FROM ' . POSTS_TABLE . ' p, ' . USERS_TABLE . ' u
							WHERE p.post_id =  ' . $post_info['post_id'] . '
								AND p.post_delete_user = u.user_id';
						$result = $db->sql_query($sql);
						$post_delete_userinfo = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);
						$display_username = get_username_string('full', $post_info['post_delete_user'], $post_delete_userinfo['username'], $post_delete_userinfo['user_colour']);
					}

					$l_deleted_by = $user->lang('DELETED_INFORMATION', $display_username, $user->format_date($post_info['post_delete_time'], false, true));
				}
				else
				{
					$l_deleted_by = '';
				}

				$post_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $post_info['forum_id'] . '&amp;p=' . $post_info['post_id'] . '#p' . $post_info['post_id']);
				$topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $post_info['forum_id'] . '&amp;t=' . $post_info['topic_id']);

				$template->assign_vars(array(
					'S_MCP_QUEUE'			=> true,
					'U_APPROVE_ACTION'		=> append_sid("{$phpbb_root_path}mcp.$phpEx", "i=queue&amp;p=$post_id&amp;f=$forum_id"),
					'S_CAN_VIEWIP'			=> $auth->acl_get('m_info', $post_info['forum_id']),
					'S_POST_REPORTED'		=> $post_info['post_reported'],
					'S_POST_UNAPPROVED'		=> ($post_info['post_visibility'] == ITEM_UNAPPROVED),
					'S_POST_LOCKED'			=> $post_info['post_edit_locked'],
					'S_USER_NOTES'			=> true,
					'S_POST_DELETED'		=> ($post_info['post_visibility'] == ITEM_DELETED),
					'DELETED_MESSAGE'		=> $l_deleted_by,
					'DELETE_REASON'			=> $post_info['post_delete_reason'],

					'U_EDIT'				=> ($auth->acl_get('m_edit', $post_info['forum_id'])) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=edit&amp;f={$post_info['forum_id']}&amp;p={$post_info['post_id']}") : '',
					'U_MCP_APPROVE'			=> append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue&amp;mode=approve_details&amp;f=' . $post_info['forum_id'] . '&amp;p=' . $post_id),
					'U_MCP_REPORT'			=> append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=reports&amp;mode=report_details&amp;f=' . $post_info['forum_id'] . '&amp;p=' . $post_id),
					'U_MCP_USER_NOTES'		=> append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=notes&amp;mode=user_notes&amp;u=' . $post_info['user_id']),
					'U_MCP_WARN_USER'		=> ($auth->acl_get('m_warn')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=warn&amp;mode=warn_user&amp;u=' . $post_info['user_id']) : '',
					'U_VIEW_POST'			=> $post_url,
					'U_VIEW_TOPIC'			=> $topic_url,

					'MINI_POST_IMG'			=> ($post_unread) ? $user->img('icon_post_target_unread', 'UNREAD_POST') : $user->img('icon_post_target', 'POST'),


					'RETURN_QUEUE'			=> sprintf($user->lang['RETURN_QUEUE'], '<a href="' . append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue' . (($topic_id) ? '&amp;mode=unapproved_topics' : '&amp;mode=unapproved_posts')) . '&amp;start=' . $start . '">', '</a>'),
					'RETURN_POST'			=> sprintf($user->lang['RETURN_POST'], '<a href="' . $post_url . '">', '</a>'),
					'RETURN_TOPIC_SIMPLE'	=> sprintf($user->lang['RETURN_TOPIC_SIMPLE'], '<a href="' . $topic_url . '">', '</a>'),
					'REPORTED_IMG'			=> $user->img('icon_topic_reported', $user->lang['POST_REPORTED']),
					'UNAPPROVED_IMG'		=> $user->img('icon_topic_unapproved', $user->lang['POST_UNAPPROVED']),
					'EDIT_IMG'				=> $user->img('icon_post_edit', $user->lang['EDIT_POST']),

					'POST_AUTHOR_FULL'		=> get_username_string('full', $post_info['user_id'], $post_info['username'], $post_info['user_colour'], $post_info['post_username']),
					'POST_AUTHOR_COLOUR'	=> get_username_string('colour', $post_info['user_id'], $post_info['username'], $post_info['user_colour'], $post_info['post_username']),
					'POST_AUTHOR'			=> get_username_string('username', $post_info['user_id'], $post_info['username'], $post_info['user_colour'], $post_info['post_username']),
					'U_POST_AUTHOR'			=> get_username_string('profile', $post_info['user_id'], $post_info['username'], $post_info['user_colour'], $post_info['post_username']),

					'POST_PREVIEW'			=> $message,
					'POST_SUBJECT'			=> $post_info['post_subject'],
					'POST_DATE'				=> $user->format_date($post_info['post_time']),
					'POST_IP'				=> $post_info['poster_ip'],
					'POST_IPADDR'			=> ($auth->acl_get('m_info', $post_info['forum_id']) && request_var('lookup', '')) ? @gethostbyaddr($post_info['poster_ip']) : '',
					'POST_ID'				=> $post_info['post_id'],
					'S_FIRST_POST'			=> ($post_info['topic_first_post_id'] == $post_id),

					'U_LOOKUP_IP'			=> ($auth->acl_get('m_info', $post_info['forum_id'])) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue&amp;mode=approve_details&amp;f=' . $post_info['forum_id'] . '&amp;p=' . $post_id . '&amp;lookup=' . $post_info['poster_ip']) . '#ip' : '',
				));

			break;

			case 'unapproved_topics':
			case 'unapproved_posts':
			case 'deleted_topics':
			case 'deleted_posts':
				$m_perm = 'm_approve';
				$is_topics = ($mode == 'unapproved_topics' || $mode == 'deleted_topics') ? true : false;
				$is_restore = ($mode == 'deleted_posts' || $mode == 'deleted_topics') ? true : false;
				$visibility_const = (!$is_restore) ? ITEM_UNAPPROVED : ITEM_DELETED;

				$user->add_lang(array('viewtopic', 'viewforum'));

				$topic_id = $request->variable('t', 0);
				$forum_info = array();
				$pagination = $phpbb_container->get('pagination');

				if ($topic_id)
				{
					$topic_info = get_topic_data(array($topic_id));

					if (!sizeof($topic_info))
					{
						trigger_error('TOPIC_NOT_EXIST');
					}

					$topic_info = $topic_info[$topic_id];
					$forum_id = $topic_info['forum_id'];
				}

				$forum_list_approve = get_forum_list($m_perm, false, true);
				$forum_list_read = array_flip(get_forum_list('f_read', true, true)); // Flipped so we can isset() the forum IDs

				// Remove forums we cannot read
				foreach ($forum_list_approve as $k => $forum_data)
				{
					if (!isset($forum_list_read[$forum_data['forum_id']]))
					{
						unset($forum_list_approve[$k]);
					}
				}
				unset($forum_list_read);

				if (!$forum_id)
				{
					$forum_list = array();
					foreach ($forum_list_approve as $row)
					{
						$forum_list[] = $row['forum_id'];
					}

					if (!sizeof($forum_list))
					{
						trigger_error('NOT_MODERATOR');
					}

					$sql = 'SELECT SUM(forum_topics_approved) as sum_forum_topics
						FROM ' . FORUMS_TABLE . '
						WHERE ' . $db->sql_in_set('forum_id', $forum_list);
					$result = $db->sql_query($sql);
					$forum_info['forum_topics_approved'] = (int) $db->sql_fetchfield('sum_forum_topics');
					$db->sql_freeresult($result);
				}
				else
				{
					$forum_info = get_forum_data(array($forum_id), $m_perm);

					if (!sizeof($forum_info))
					{
						trigger_error('NOT_MODERATOR');
					}

					$forum_info = $forum_info[$forum_id];
					$forum_list = $forum_id;
				}

				$forum_options = '<option value="0"' . (($forum_id == 0) ? ' selected="selected"' : '') . '>' . $user->lang['ALL_FORUMS'] . '</option>';
				foreach ($forum_list_approve as $row)
				{
					$forum_options .= '<option value="' . $row['forum_id'] . '"' . (($forum_id == $row['forum_id']) ? ' selected="selected"' : '') . '>' . str_repeat('&nbsp; &nbsp;', $row['padding']) . $row['forum_name'] . '</option>';
				}

				$sort_days = $total = 0;
				$sort_key = $sort_dir = '';
				$sort_by_sql = $sort_order_sql = array();
				mcp_sorting($mode, $sort_days, $sort_key, $sort_dir, $sort_by_sql, $sort_order_sql, $total, $forum_id, $topic_id);

				$forum_topics = ($total == -1) ? $forum_info['forum_topics_approved'] : $total;
				$limit_time_sql = ($sort_days) ? 'AND t.topic_last_post_time >= ' . (time() - ($sort_days * 86400)) : '';

				$forum_names = array();

				if (!$is_topics)
				{
					$sql = 'SELECT p.post_id
						FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t' . (($sort_order_sql[0] == 'u') ? ', ' . USERS_TABLE . ' u' : '') . '
						WHERE ' . $db->sql_in_set('p.forum_id', $forum_list) . '
							AND p.post_visibility = ' . $visibility_const . '
							' . (($sort_order_sql[0] == 'u') ? 'AND u.user_id = p.poster_id' : '') . '
							' . (($topic_id) ? 'AND p.topic_id = ' . $topic_id : '') . "
							AND t.topic_id = p.topic_id
							AND (t.topic_visibility <> p.post_visibility
								OR t.topic_delete_user = 0)
							$limit_time_sql
						ORDER BY $sort_order_sql";
					$result = $db->sql_query_limit($sql, $config['topics_per_page'], $start);

					$i = 0;
					$post_ids = array();
					while ($row = $db->sql_fetchrow($result))
					{
						$post_ids[] = $row['post_id'];
						$row_num[$row['post_id']] = $i++;
					}
					$db->sql_freeresult($result);

					if (sizeof($post_ids))
					{
						$sql = 'SELECT t.topic_id, t.topic_title, t.forum_id, p.post_id, p.post_subject, p.post_username, p.poster_id, p.post_time, p.post_attachment, u.username, u.username_clean, u.user_colour
							FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . USERS_TABLE . ' u
							WHERE ' . $db->sql_in_set('p.post_id', $post_ids) . '
								AND t.topic_id = p.topic_id
								AND u.user_id = p.poster_id
							ORDER BY ' . $sort_order_sql;
						$result = $db->sql_query($sql);

						$post_data = $rowset = array();
						while ($row = $db->sql_fetchrow($result))
						{
							$forum_names[] = $row['forum_id'];
							$post_data[$row['post_id']] = $row;
						}
						$db->sql_freeresult($result);

						foreach ($post_ids as $post_id)
						{
							$rowset[] = $post_data[$post_id];
						}
						unset($post_data, $post_ids);
					}
					else
					{
						$rowset = array();
					}
				}
				else
				{
					$sql = 'SELECT t.forum_id, t.topic_id, t.topic_title, t.topic_title AS post_subject, t.topic_time AS post_time, t.topic_poster AS poster_id, t.topic_first_post_id AS post_id, t.topic_attachment AS post_attachment, t.topic_first_poster_name AS username, t.topic_first_poster_colour AS user_colour
						FROM ' . TOPICS_TABLE . ' t
						WHERE ' . $db->sql_in_set('forum_id', $forum_list) . '
							AND topic_visibility = ' . $visibility_const . "
							AND topic_delete_user <> 0
							$limit_time_sql
						ORDER BY $sort_order_sql";
					$result = $db->sql_query_limit($sql, $config['topics_per_page'], $start);

					$rowset = array();
					while ($row = $db->sql_fetchrow($result))
					{
						$forum_names[] = $row['forum_id'];
						$rowset[] = $row;
					}
					$db->sql_freeresult($result);
				}

				if (sizeof($forum_names))
				{
					// Select the names for the forum_ids
					$sql = 'SELECT forum_id, forum_name
						FROM ' . FORUMS_TABLE . '
						WHERE ' . $db->sql_in_set('forum_id', $forum_names);
					$result = $db->sql_query($sql, 3600);

					$forum_names = array();
					while ($row = $db->sql_fetchrow($result))
					{
						$forum_names[$row['forum_id']] = $row['forum_name'];
					}
					$db->sql_freeresult($result);
				}

				foreach ($rowset as $row)
				{
					if (empty($row['post_username']))
					{
						$row['post_username'] = $row['username'] ?: $user->lang['GUEST'];
					}

					$template->assign_block_vars('postrow', array(
						'U_TOPIC'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $row['forum_id'] . '&amp;t=' . $row['topic_id']),
						'U_VIEWFORUM'		=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $row['forum_id']),
						'U_VIEWPOST'		=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $row['forum_id'] . '&amp;p=' . $row['post_id']) . (($mode == 'unapproved_posts') ? '#p' . $row['post_id'] : ''),
						'U_VIEW_DETAILS'	=> append_sid("{$phpbb_root_path}mcp.$phpEx", "i=queue&amp;start=$start&amp;mode=approve_details&amp;f={$row['forum_id']}&amp;p={$row['post_id']}" . (($mode == 'unapproved_topics') ? "&amp;t={$row['topic_id']}" : '')),

						'POST_AUTHOR_FULL'		=> get_username_string('full', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),
						'POST_AUTHOR_COLOUR'	=> get_username_string('colour', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),
						'POST_AUTHOR'			=> get_username_string('username', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),
						'U_POST_AUTHOR'			=> get_username_string('profile', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),

						'POST_ID'		=> $row['post_id'],
						'TOPIC_ID'		=> $row['topic_id'],
						'FORUM_NAME'	=> $forum_names[$row['forum_id']],
						'POST_SUBJECT'	=> ($row['post_subject'] != '') ? $row['post_subject'] : $user->lang['NO_SUBJECT'],
						'TOPIC_TITLE'	=> $row['topic_title'],
						'POST_TIME'		=> $user->format_date($row['post_time']),
						'ATTACH_ICON_IMG'	=> ($auth->acl_get('u_download') && $auth->acl_get('f_download', $row['forum_id']) && $row['post_attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
					));
				}
				unset($rowset, $forum_names);

				$base_url = $this->u_action . "&amp;f=$forum_id&amp;st=$sort_days&amp;sk=$sort_key&amp;sd=$sort_dir";
				$pagination->generate_template_pagination($base_url, 'pagination', 'start', $total, $config['topics_per_page'], $start);

				// Now display the page
				$template->assign_vars(array(
					'L_DISPLAY_ITEMS'		=> (!$is_topics) ? $user->lang['DISPLAY_POSTS'] : $user->lang['DISPLAY_TOPICS'],
					'L_EXPLAIN'				=> $user->lang['MCP_QUEUE_' . strtoupper($mode) . '_EXPLAIN'],
					'L_TITLE'				=> $user->lang['MCP_QUEUE_' . strtoupper($mode)],
					'L_ONLY_TOPIC'			=> ($topic_id) ? sprintf($user->lang['ONLY_TOPIC'], $topic_info['topic_title']) : '',

					'S_FORUM_OPTIONS'		=> $forum_options,
					'S_MCP_ACTION'			=> build_url(array('t', 'f', 'sd', 'st', 'sk')),
					'S_TOPICS'				=> $is_topics,
					'S_RESTORE'				=> $is_restore,

					'TOPIC_ID'				=> $topic_id,
					'TOTAL'					=> $user->lang(((!$is_topics) ? 'VIEW_TOPIC_POSTS' : 'VIEW_FORUM_TOPICS'), (int) $total),
				));

				$this->tpl_name = 'mcp_queue';
			break;
		}
	}

	/**
	* Approve/Restore posts
	*
	* @param $action		string	Action we perform on the posts ('approve' or 'restore')
	* @param $post_id_list	array	IDs of the posts to approve/restore
	* @param $id			mixed	Category of the current active module
	* @param $mode			string	Active module
	* @return null
	*/
	static public function approve_posts($action, $post_id_list, $id, $mode)
	{
		global $db, $template, $user, $config, $request, $phpbb_container;
		global $phpEx, $phpbb_root_path;

		if (!check_ids($post_id_list, POSTS_TABLE, 'post_id', array('m_approve')))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$redirect = $request->variable('redirect', build_url(array('quickmod')));
		$redirect = reapply_sid($redirect);
		$success_msg = $post_url = '';
		$approve_log = array();

		$s_hidden_fields = build_hidden_fields(array(
			'i'				=> $id,
			'mode'			=> $mode,
			'post_id_list'	=> $post_id_list,
			'action'		=> $action,
			'redirect'		=> $redirect,
		));

		$post_info = get_post_data($post_id_list, 'm_approve');

		if (confirm_box(true))
		{
			$notify_poster = ($action == 'approve' && isset($_REQUEST['notify_poster']));

			$topic_info = array();

			// Group the posts by topic_id
			foreach ($post_info as $post_id => $post_data)
			{
				if ($post_data['post_visibility'] == ITEM_APPROVED)
				{
					continue;
				}
				$topic_id = (int) $post_data['topic_id'];

				$topic_info[$topic_id]['posts'][] = (int) $post_id;
				$topic_info[$topic_id]['forum_id'] = (int) $post_data['forum_id'];

				// Refresh the first post, if the time or id is older then the current one
				if ($post_id <= $post_data['topic_first_post_id'] || $post_data['post_time'] <= $post_data['topic_time'])
				{
					$topic_info[$topic_id]['first_post'] = true;
				}

				// Refresh the last post, if the time or id is newer then the current one
				if ($post_id >= $post_data['topic_last_post_id'] || $post_data['post_time'] >= $post_data['topic_last_post_time'])
				{
					$topic_info[$topic_id]['last_post'] = true;
				}

				$post_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f={$post_data['forum_id']}&amp;t={$post_data['topic_id']}&amp;p={$post_data['post_id']}") . '#p' . $post_data['post_id'];

				$approve_log[] = array(
					'forum_id'		=> $post_data['forum_id'],
					'topic_id'		=> $post_data['topic_id'],
					'post_subject'	=> $post_data['post_subject'],
				);
			}

			$phpbb_content_visibility = $phpbb_container->get('content.visibility');
			foreach ($topic_info as $topic_id => $topic_data)
			{
				$phpbb_content_visibility->set_post_visibility(ITEM_APPROVED, $topic_data['posts'], $topic_id, $topic_data['forum_id'], $user->data['user_id'], time(), '', isset($topic_data['first_post']), isset($topic_data['last_post']));
			}

			if (sizeof($post_info) >= 1)
			{
				$success_msg = (sizeof($post_info) == 1) ? 'POST_' . strtoupper($action) . 'D_SUCCESS' : 'POSTS_' . strtoupper($action) . 'D_SUCCESS';
			}

			foreach ($approve_log as $log_data)
			{
				add_log('mod', $log_data['forum_id'], $log_data['topic_id'], 'LOG_POST_' . strtoupper($action) . 'D', $log_data['post_subject']);
			}

			// Only send out the mails, when the posts are being approved
			if ($action == 'approve')
			{
				$phpbb_notifications = $phpbb_container->get('notification_manager');

				// Handle notifications
				foreach ($post_info as $post_id => $post_data)
				{
					// A single topic approval may also happen here, so handle deleting the respective notification.
					if (!$post_data['topic_posts_approved'])
					{
						$phpbb_notifications->delete_notifications('topic_in_queue', $post_data['topic_id']);
					}
					$phpbb_notifications->delete_notifications('post_in_queue', $post_id);

					$phpbb_notifications->add_notifications(array(
						'quote',
						'bookmark',
						'post',
					), $post_data);

					$phpbb_notifications->mark_notifications_read(array(
						'quote',
						'bookmark',
						'post',
					), $post_data['post_id'], $user->data['user_id']);

					// Notify Poster?
					if ($notify_poster)
					{
						if ($post_data['poster_id'] == ANONYMOUS)
						{
							continue;
						}

						$phpbb_notifications->add_notifications('approve_post', $post_data);
					}
				}
			}

			meta_refresh(3, $redirect);
			$message = $user->lang[$success_msg];

			if ($request->is_ajax())
			{
				$json_response = new \phpbb\json_response;
				$json_response->send(array(
					'MESSAGE_TITLE'		=> $user->lang['INFORMATION'],
					'MESSAGE_TEXT'		=> $message,
					'REFRESH_DATA'		=> null,
					'visible'			=> true,
				));
			}
			$message .= '<br /><br />' . $user->lang('RETURN_PAGE', '<a href="' . $redirect . '">', '</a>');

			// If approving one post, also give links back to post...
			if (sizeof($post_info) == 1 && $post_url)
			{
				$message .= '<br /><br />' . $user->lang('RETURN_POST', '<a href="' . $post_url . '">', '</a>');
			}
			trigger_error($message);
		}
		else
		{
			$show_notify = false;

			if ($action == 'approve')
			{
				foreach ($post_info as $post_data)
				{
					if ($post_data['poster_id'] == ANONYMOUS)
					{
						continue;
					}
					else
					{
						$show_notify = true;
						break;
					}
				}
			}

			$template->assign_vars(array(
				'S_NOTIFY_POSTER'			=> $show_notify,
				'S_' . strtoupper($action)	=> true,
			));

			confirm_box(false, strtoupper($action) . '_POST' . ((sizeof($post_id_list) == 1) ? '' : 'S'), $s_hidden_fields, 'mcp_approve.html');
		}

		redirect($redirect);
	}

	/**
	* Approve/Restore topics
	*
	* @param $action		string	Action we perform on the posts ('approve' or 'restore')
	* @param $topic_id_list	array	IDs of the topics to approve/restore
	* @param $id			mixed	Category of the current active module
	* @param $mode			string	Active module
	* @return null
	*/
	static public function approve_topics($action, $topic_id_list, $id, $mode)
	{
		global $db, $template, $user, $config;
		global $phpEx, $phpbb_root_path, $request, $phpbb_container;

		if (!check_ids($topic_id_list, TOPICS_TABLE, 'topic_id', array('m_approve')))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$redirect = $request->variable('redirect', build_url(array('quickmod')));
		$redirect = reapply_sid($redirect);
		$success_msg = $topic_url = '';
		$approve_log = array();

		$s_hidden_fields = build_hidden_fields(array(
			'i'				=> $id,
			'mode'			=> $mode,
			'topic_id_list'	=> $topic_id_list,
			'action'		=> $action,
			'redirect'		=> $redirect,
		));

		$topic_info = get_topic_data($topic_id_list, 'm_approve');

		if (confirm_box(true))
		{
			$notify_poster = ($action == 'approve' && isset($_REQUEST['notify_poster'])) ? true : false;

			$phpbb_content_visibility = $phpbb_container->get('content.visibility');
			$first_post_ids = array();

			foreach ($topic_info as $topic_id => $topic_data)
			{
				$phpbb_content_visibility->set_topic_visibility(ITEM_APPROVED, $topic_id, $topic_data['forum_id'], $user->data['user_id'], time(), '');
				$first_post_ids[$topic_id] = (int) $topic_data['topic_first_post_id'];

				$topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f={$topic_data['forum_id']}&amp;t={$topic_id}");

				$approve_log[] = array(
					'forum_id'		=> $topic_data['forum_id'],
					'topic_id'		=> $topic_data['topic_id'],
					'topic_title'	=> $topic_data['topic_title'],
				);
			}

			if (sizeof($topic_info) >= 1)
			{
				$success_msg = (sizeof($topic_info) == 1) ? 'TOPIC_' . strtoupper($action) . 'D_SUCCESS' : 'TOPICS_' . strtoupper($action) . 'D_SUCCESS';
			}

			foreach ($approve_log as $log_data)
			{
				add_log('mod', $log_data['forum_id'], $log_data['topic_id'], 'LOG_TOPIC_' . strtoupper($action) . 'D', $log_data['topic_title']);
			}

			// Only send out the mails, when the posts are being approved
			if ($action == 'approve')
			{
				// Grab the first post text as it's needed for the quote notification.
				$sql = 'SELECT topic_id, post_text
					FROM ' . POSTS_TABLE . '
					WHERE ' . $db->sql_in_set('post_id', $first_post_ids);
				$result = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result))
				{
					$topic_info[$row['topic_id']]['post_text'] = $row['post_text'];
				}
				$db->sql_freeresult($result);

				// Handle notifications
				$phpbb_notifications = $phpbb_container->get('notification_manager');

				foreach ($topic_info as $topic_id => $topic_data)
				{
					$topic_data = array_merge($topic_data, array(
						'post_id'		=> $topic_data['topic_first_post_id'],
						'post_subject'	=> $topic_data['topic_title'],
						'post_time'		=> $topic_data['topic_time'],
						'poster_id'		=> $topic_data['topic_poster'],
						'username'		=> $topic_data['topic_first_poster_name'],
					));

					$phpbb_notifications->delete_notifications('topic_in_queue', $topic_id);
					$phpbb_notifications->add_notifications(array(
						'quote',
						'topic',
					), $topic_data);

					$phpbb_notifications->mark_notifications_read('quote', $topic_data['post_id'], $user->data['user_id']);
					$phpbb_notifications->mark_notifications_read('topic', $topic_id, $user->data['user_id']);

					if ($notify_poster)
					{
						$phpbb_notifications->add_notifications('approve_topic', $topic_data);
					}
				}
			}

			meta_refresh(3, $redirect);
			$message = $user->lang[$success_msg];

			if ($request->is_ajax())
			{
				$json_response = new \phpbb\json_response;
				$json_response->send(array(
					'MESSAGE_TITLE'		=> $user->lang['INFORMATION'],
					'MESSAGE_TEXT'		=> $message,
					'REFRESH_DATA'		=> null,
					'visible'			=> true,
				));
			}
			$message .= '<br /><br />' . $user->lang('RETURN_PAGE', '<a href="' . $redirect . '">', '</a>');

			// If approving one topic, also give links back to topic...
			if (sizeof($topic_info) == 1 && $topic_url)
			{
				$message .= '<br /><br />' . $user->lang('RETURN_TOPIC', '<a href="' . $topic_url . '">', '</a>');
			}
			trigger_error($message);
		}
		else
		{
			$show_notify = false;

			if ($action == 'approve')
			{
				foreach ($topic_info as $topic_data)
				{
					if ($topic_data['topic_poster'] == ANONYMOUS)
					{
						continue;
					}
					else
					{
						$show_notify = true;
						break;
					}
				}
			}

			$template->assign_vars(array(
				'S_NOTIFY_POSTER'			=> $show_notify,
				'S_' . strtoupper($action)	=> true,
			));

			confirm_box(false, strtoupper($action) . '_TOPIC' . ((sizeof($topic_id_list) == 1) ? '' : 'S'), $s_hidden_fields, 'mcp_approve.html');
		}

		redirect($redirect);
	}

	/**
	* Disapprove Post
	*
	* @param $post_id_list	array	IDs of the posts to disapprove/delete
	* @param $id			mixed	Category of the current active module
	* @param $mode			string	Active module
	* @return null
	*/
	static public function disapprove_posts($post_id_list, $id, $mode)
	{
		global $db, $template, $user, $config, $phpbb_container;
		global $phpEx, $phpbb_root_path, $request;

		if (!check_ids($post_id_list, POSTS_TABLE, 'post_id', array('m_approve')))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$redirect = $request->variable('redirect', build_url(array('t', 'mode', 'quickmod')) . "&amp;mode=$mode");
		$redirect = reapply_sid($redirect);
		$reason = $request->variable('reason', '', true);
		$reason_id = $request->variable('reason_id', 0);
		$success_msg = $additional_msg = '';

		$s_hidden_fields = build_hidden_fields(array(
			'i'				=> $id,
			'mode'			=> $mode,
			'post_id_list'	=> $post_id_list,
			'action'		=> 'disapprove',
			'redirect'		=> $redirect,
		));

		$notify_poster = $request->is_set('notify_poster');
		$disapprove_reason = '';

		if ($reason_id)
		{
			$sql = 'SELECT reason_title, reason_description
				FROM ' . REPORTS_REASONS_TABLE . "
				WHERE reason_id = $reason_id";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$row || (!$reason && strtolower($row['reason_title']) == 'other'))
			{
				$additional_msg = $user->lang['NO_REASON_DISAPPROVAL'];

				$request->overwrite('confirm', null, \phpbb\request\request_interface::POST);
				$request->overwrite('confirm_key', null, \phpbb\request\request_interface::POST);
				$request->overwrite('confirm_key', null, \phpbb\request\request_interface::REQUEST);
			}
			else
			{
				// If the reason is defined within the language file, we will use the localized version, else just use the database entry...
				$disapprove_reason = (strtolower($row['reason_title']) != 'other') ? ((isset($user->lang['report_reasons']['DESCRIPTION'][strtoupper($row['reason_title'])])) ? $user->lang['report_reasons']['DESCRIPTION'][strtoupper($row['reason_title'])] : $row['reason_description']) : '';
				$disapprove_reason .= ($reason) ? "\n\n" . $reason : '';

				if (isset($user->lang['report_reasons']['DESCRIPTION'][strtoupper($row['reason_title'])]))
				{
					$disapprove_reason_lang = strtoupper($row['reason_title']);
				}
			}
		}

		$post_info = get_post_data($post_id_list, 'm_approve');

		$is_disapproving = false;
		foreach ($post_info as $post_id => $post_data)
		{
			if ($post_data['post_visibility'] == ITEM_DELETED)
			{
				continue;
			}

			$is_disapproving = true;
		}

		if (confirm_box(true))
		{
			$disapprove_log = $disapprove_log_topics = $disapprove_log_posts = array();
			$topic_posts_unapproved = $post_disapprove_list = $topic_information = array();

			// Build a list of posts to be disapproved and get the related topics real replies count
			foreach ($post_info as $post_id => $post_data)
			{
				$post_disapprove_list[$post_id] = $post_data['topic_id'];
				if (!isset($topic_posts_unapproved[$post_data['topic_id']]))
				{
					$topic_information[$post_data['topic_id']] = $post_data;
					$topic_posts_unapproved[$post_data['topic_id']] = 0;
				}
				$topic_posts_unapproved[$post_data['topic_id']]++;
			}

			// Now we build the log array
			foreach ($post_disapprove_list as $post_id => $topic_id)
			{
				// If the count of disapproved posts for the topic is equal
				// to the number of unapproved posts in the topic, and there are no different
				// posts, we disapprove the hole topic
				if ($topic_information[$topic_id]['topic_posts_approved'] == 0 &&
					$topic_information[$topic_id]['topic_posts_softdeleted'] == 0 &&
					$topic_information[$topic_id]['topic_posts_unapproved'] == $topic_posts_unapproved[$topic_id])
				{
					// Don't write the log more than once for every topic
					if (!isset($disapprove_log_topics[$topic_id]))
					{
						// Build disapproved topics log
						$disapprove_log_topics[$topic_id] = array(
							'type'			=> 'topic',
							'post_subject'	=> $post_info[$post_id]['topic_title'],
							'forum_id'		=> $post_info[$post_id]['forum_id'],
							'topic_id'		=> 0, // useless to log a topic id, as it will be deleted
							'post_username'	=> ($post_info[$post_id]['poster_id'] == ANONYMOUS && !empty($post_info[$post_id]['post_username'])) ? $post_info[$post_id]['post_username'] : $post_info[$post_id]['username'],
						);
					}
				}
				else
				{
					// Build disapproved posts log
					$disapprove_log_posts[] = array(
						'type'			=> 'post',
						'post_subject'	=> $post_info[$post_id]['post_subject'],
						'forum_id'		=> $post_info[$post_id]['forum_id'],
						'topic_id'		=> $post_info[$post_id]['topic_id'],
						'post_username'	=> ($post_info[$post_id]['poster_id'] == ANONYMOUS && !empty($post_info[$post_id]['post_username'])) ? $post_info[$post_id]['post_username'] : $post_info[$post_id]['username'],
					);

				}
			}

			// Get disapproved posts/topics counts separately
			$num_disapproved_topics = sizeof($disapprove_log_topics);
			$num_disapproved_posts = sizeof($disapprove_log_posts);

			// Build the whole log
			$disapprove_log = array_merge($disapprove_log_topics, $disapprove_log_posts);

			// Unset unneeded arrays
			unset($post_data, $disapprove_log_topics, $disapprove_log_posts);

			// Let's do the job - delete disapproved posts
			if (sizeof($post_disapprove_list))
			{
				if (!function_exists('delete_posts'))
				{
					include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
				}

				// We do not check for permissions here, because the moderator allowed approval/disapproval should be allowed to delete the disapproved posts
				// Note: function delete_posts triggers related forums/topics sync,
				// so we don't need to call update_post_information later and to adjust real topic replies or forum topics count manually
				delete_posts('post_id', array_keys($post_disapprove_list));

				foreach ($disapprove_log as $log_data)
				{
					if ($is_disapproving)
					{
						$l_log_message = ($log_data['type'] == 'topic') ? 'LOG_TOPIC_DISAPPROVED' : 'LOG_POST_DISAPPROVED';
						add_log('mod', $log_data['forum_id'], $log_data['topic_id'], $l_log_message, $log_data['post_subject'], $disapprove_reason);
					}
					else
					{
						$l_log_message = ($log_data['type'] == 'topic') ? 'LOG_DELETE_TOPIC' : 'LOG_DELETE_POST';
						add_log('mod', $log_data['forum_id'], $log_data['topic_id'], $l_log_message, $log_data['post_subject'], $log_data['post_username']);
					}
				}
			}

			$phpbb_notifications = $phpbb_container->get('notification_manager');

			$lang_reasons = array();

			foreach ($post_info as $post_id => $post_data)
			{
				$disapprove_all_posts_in_topic = $topic_information[$topic_id]['topic_posts_approved'] == 0 &&
					$topic_information[$topic_id]['topic_posts_softdeleted'] == 0 &&
					$topic_information[$topic_id]['topic_posts_unapproved'] == $topic_posts_unapproved[$topic_id];

				$phpbb_notifications->delete_notifications('post_in_queue', $post_id);

				// Do we disapprove the whole topic? Remove potential notifications
				if ($disapprove_all_posts_in_topic)
				{
					$phpbb_notifications->delete_notifications('topic_in_queue', $post_data['topic_id']);
				}

				// Notify Poster?
				if ($notify_poster)
				{
					if ($post_data['poster_id'] == ANONYMOUS)
					{
						continue;
					}

					$post_data['disapprove_reason'] = '';
					if (isset($disapprove_reason_lang))
					{
						// Okay we need to get the reason from the posters language
						if (!isset($lang_reasons[$post_data['user_lang']]))
						{
							// Assign the current users translation as the default, this is not ideal but getting the board default adds another layer of complexity.
							$lang_reasons[$post_data['user_lang']] = $user->lang['report_reasons']['DESCRIPTION'][$disapprove_reason_lang];

							// Only load up the language pack if the language is different to the current one
							if ($post_data['user_lang'] != $user->lang_name && file_exists($phpbb_root_path . '/language/' . $post_data['user_lang'] . '/mcp.' . $phpEx))
							{
								// Load up the language pack
								$lang = array();
								@include($phpbb_root_path . '/language/' . basename($post_data['user_lang']) . '/mcp.' . $phpEx);

								// If we find the reason in this language pack use it
								if (isset($lang['report_reasons']['DESCRIPTION'][$disapprove_reason_lang]))
								{
									$lang_reasons[$post_data['user_lang']] = $lang['report_reasons']['DESCRIPTION'][$disapprove_reason_lang];
								}

								unset($lang); // Free memory
							}
						}

						$post_data['disapprove_reason'] = $lang_reasons[$post_data['user_lang']];
						$post_data['disapprove_reason'] .= ($reason) ? "\n\n" . $reason : '';
					}


					if ($disapprove_all_posts_in_topic && $topic_information[$topic_id]['topic_posts_unapproved'] == 1)
					{
						// If there is only 1 post when disapproving the topic,
						// we send the user a "disapprove topic" notification...
						$phpbb_notifications->add_notifications('disapprove_topic', $post_data);
					}
					else
					{
						// ... otherwise there are multiple unapproved posts and
						// all of them are disapproved as posts.
						$phpbb_notifications->add_notifications('disapprove_post', $post_data);
					}
				}
			}

			unset($lang_reasons, $post_info, $disapprove_reason, $disapprove_reason_lang);


			if ($num_disapproved_topics)
			{
				$success_msg = ($num_disapproved_topics == 1) ? 'TOPIC' : 'TOPICS';
			}
			else
			{
				$success_msg = ($num_disapproved_posts == 1) ? 'POST' : 'POSTS';
			}

			if ($is_disapproving)
			{
				$success_msg .= '_DISAPPROVED_SUCCESS';
			}
			else
			{
				$success_msg .= '_DELETED_SUCCESS';
			}

			// If we came from viewtopic, we try to go back to it.
			if (strpos($redirect, $phpbb_root_path . 'viewtopic.' . $phpEx) === 0)
			{
				if ($num_disapproved_topics == 0)
				{
					// So we need to remove the post id part from the Url
					$redirect = str_replace("&amp;p={$post_id_list[0]}#p{$post_id_list[0]}", '', $redirect);
				}
				else
				{
					// However this is only possible if the topic still exists,
					// Otherwise we go back to the viewforum page
					$redirect = append_sid($phpbb_root_path . 'viewforum.' . $phpEx, 'f=' . $request->variable('f', 0));
				}
			}

			meta_refresh(3, $redirect);
			$message = $user->lang[$success_msg];

			if ($request->is_ajax())
			{
				$json_response = new \phpbb\json_response;
				$json_response->send(array(
					'MESSAGE_TITLE'		=> $user->lang['INFORMATION'],
					'MESSAGE_TEXT'		=> $message,
					'REFRESH_DATA'		=> null,
					'visible'			=> false,
				));
			}
			$message .= '<br /><br />' . $user->lang('RETURN_PAGE', '<a href="' . $redirect . '">', '</a>');
			trigger_error($message);
		}
		else
		{
			if (!function_exists('display_reasons'))
			{
				include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
			}

			$show_notify = false;

			foreach ($post_info as $post_data)
			{
				if ($post_data['poster_id'] == ANONYMOUS)
				{
					continue;
				}
				else
				{
					$show_notify = true;
					break;
				}
			}

			$l_confirm_msg = 'DISAPPROVE_POST';
			$confirm_template = 'mcp_approve.html';
			if ($is_disapproving)
			{
				display_reasons($reason_id);
			}
			else
			{
				$user->add_lang('posting');

				$l_confirm_msg = 'DELETE_POST_PERMANENTLY';
				$confirm_template = 'confirm_delete_body.html';
			}
			$l_confirm_msg .= ((sizeof($post_id_list) == 1) ? '' : 'S');

			$template->assign_vars(array(
				'S_NOTIFY_POSTER'	=> $show_notify,
				'S_APPROVE'			=> false,
				'REASON'			=> ($is_disapproving) ? $reason : '',
				'ADDITIONAL_MSG'	=> $additional_msg,
			));

			confirm_box(false, $l_confirm_msg, $s_hidden_fields, $confirm_template);
		}

		redirect($redirect);
	}
}
