<?php
/**
 * Private Message Log
 * Copyright 2012 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->private_message_log, "index.php?module=tools-pmlog");

if($mybb->input['action'] == "view")
{
	$query = $db->query("
		SELECT p.*, r.username AS to_username, f.username AS from_username
		FROM ".TABLE_PREFIX."privatemessages p
		LEFT JOIN ".TABLE_PREFIX."users r ON (r.uid=p.toid)
		LEFT JOIN ".TABLE_PREFIX."users f ON (f.uid=p.fromid)
		WHERE pmid='".intval($mybb->input['pmid'])."'
	");
	$log = $db->fetch_array($query);

	if(!$log['pmid'])
	{
		exit;
	}

	$log['to_username'] = htmlspecialchars_uni($log['to_username']);
	$log['from_username'] = htmlspecialchars_uni($log['from_username']);
	$log['subject'] = htmlspecialchars_uni($log['subject']);
	$log['message'] = htmlspecialchars_uni($log['message']);
	$log['dateline'] = date($mybb->settings['dateformat'], $log['dateline']).", ".date($mybb->settings['timeformat'], $log['dateline']);

	// Log admin action
	log_admin_action($log['pmid'], $log['from_username'], $log['fromid']);

	?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head profile="http://gmpg.org/xfn/1">
	<title><?php echo $lang->private_message_log_viewer; ?></title>
	<link rel="stylesheet" href="styles/<?php echo $page->style; ?>/main.css" type="text/css" />
	<link rel="stylesheet" href="styles/<?php echo $page->style; ?>/popup.css" type="text/css" />
</head>
<body id="popup">
	<div id="popup_container">
	<div class="popup_title"><a href="#" onClick="window.close();" class="close_link"><?php echo $lang->close_window; ?></a><?php echo $lang->private_message_log_viewer; ?></div>

	<div id="content">
	<?php
	$table = new Table();

	$table->construct_cell($lang->to.":");
	$table->construct_cell($log['to_username']);
	$table->construct_row();

	$table->construct_cell($lang->from.":");
	$table->construct_cell($log['from_username']);
	$table->construct_row();

	$table->construct_cell($lang->subject.":");
	$table->construct_cell($log['subject']);
	$table->construct_row();

	$table->construct_cell($lang->date.":");
	$table->construct_cell($log['dateline']);
	$table->construct_row();

	$table->construct_cell($log['message'], array("colspan" => 2));
	$table->construct_row();

	$table->output($lang->private_message);

	?>
	</div>
</div>
</body>
</html>
	<?php
}

if(!$mybb->input['action'])
{	
	$per_page = 20;

	if($mybb->input['page'] && $mybb->input['page'] > 1)
	{
		$mybb->input['page'] = intval($mybb->input['page']);
		$start = ($mybb->input['page']*$per_page)-$per_page;
	}
	else
	{
		$mybb->input['page'] = 1;
		$start = 0;
	}

	$additional_criteria = array();

	// Begin criteria filtering
	if(!$mybb->input['folder'])
	{
		$mybb->input['folder'] = 1;
	}

	if($mybb->input['folder'] == 5)
	{
		$additional_sql_criteria .= " AND p.folder > 4";
		$additional_criteria[] = "folder > 4";
	}
	else
	{
		$additional_sql_criteria .= " AND p.folder = '".intval($mybb->input['folder'])."'";
		$additional_criteria[] = "folder=".intval($mybb->input['folder']);
	}

	if($mybb->input['subject'])
	{
		$additional_sql_criteria .= " AND p.subject LIKE '%".$db->escape_string($mybb->input['subject'])."%'";
		$additional_criteria[] = "subject='".htmlspecialchars_uni($mybb->input['subject'])."'";
	}

	if($mybb->input['fromid'])
	{
		$query = $db->simple_select("users", "uid, username", "uid='".intval($mybb->input['fromid'])."'");
		$user = $db->fetch_array($query);
		$additional_sql_criteria .= " AND p.fromid='".intval($mybb->input['fromid'])."'";
		$additional_criteria[] = "fromid='".intval($mybb->input['fromid'])."'";
	}
	else if($mybb->input['fromname'])
	{
		$query = $db->simple_select("users", "uid, username", "LOWER(username)='".my_strtolower($mybb->input['fromname'])."'");
		$user = $db->fetch_array($query);

		if(!$user['uid'])
		{
			flash_message($lang->error_invalid_user, 'error');
			admin_redirect("index.php?module=tools-pmlog");
		}
		$additional_sql_criteria .= "AND p.fromid='{$user['uid']}'";
		$additional_criteria[] = "fromid={$user['uid']}";
	}

	if($mybb->input['toid'])
	{
		$query = $db->simple_select("users", "uid, username", "uid='".intval($mybb->input['toid'])."'");
		$user = $db->fetch_array($query);
		$additional_sql_criteria .= " AND p.toid='".intval($mybb->input['toid'])."'";
		$additional_criteria[] = "toid='".intval($mybb->input['toid'])."'";
	}
	else if($mybb->input['toname'])
	{
		$query = $db->simple_select("users", "uid, username", "LOWER(username)='".my_strtolower($mybb->input['toname'])."'");
		$user = $db->fetch_array($query);

		if(!$user['uid'])
		{
			flash_message($lang->error_invalid_user, 'error');
			admin_redirect("index.php?module=tools-pmlog");
		}
		$additional_sql_criteria .= "AND p.toid='{$user['uid']}'";
		$additional_criteria[] = "toid='{$user['uid']}'";
	}

	if($additional_criteria)
	{
		$additional_criteria = "&amp;".implode("&amp;", $additional_criteria);
	}

	$page->output_header($lang->private_message_log);

	$sub_tabs['pmlogs'] = array(
		'title' => $lang->private_message_log,
		'link' => "index.php?module=tools-pmlog",
		'description' => $lang->private_message_log_desc
	);

	$page->output_nav_tabs($sub_tabs, 'pmlogs');

	$table = new Table;
	$table->construct_header($lang->subject, array("colspan" => 2));
	$table->construct_header($lang->from, array("class" => "align_center", "width" => "20%"));
	$table->construct_header($lang->to, array("class" => "align_center", "width" => "20%"));
	$table->construct_header($lang->date_sent, array("class" => "align_center", "width" => "20%"));

	$query = $db->query("
		SELECT p.*, r.username AS to_username, r.usergroup AS to_usergroup, r.displaygroup AS to_displaygroup, f.username AS from_username, f.usergroup AS from_usergroup, f.displaygroup AS from_displaygroup
		FROM ".TABLE_PREFIX."privatemessages p
		LEFT JOIN ".TABLE_PREFIX."users r ON (r.uid=p.toid)
		LEFT JOIN ".TABLE_PREFIX."users f ON (f.uid=p.fromid)
		WHERE 1=1 {$additional_sql_criteria}
		ORDER BY p.dateline DESC
		LIMIT {$start}, {$per_page}
	");
	while($log = $db->fetch_array($query))
	{
		$log['subject'] = htmlspecialchars_uni($log['subject']);
		$log['dateline'] = date($mybb->settings['dateformat'], $log['dateline']).", ".date($mybb->settings['timeformat'], $log['dateline']);

		$msg_alt = $folder = '';
		// Determine Folder Icon
		if($log['status'] == 0)
		{
			$folder = 'new_pm.gif';
			$msg_alt = $lang->new_pm;
		}
		elseif($log['status'] == 1)
		{
			$folder = 'old_pm.gif';
			$msg_alt = $lang->old_pm;
		}
		elseif($log['status'] == 3)
		{
			$folder = 're_pm.gif';
			$msg_alt = $lang->reply_pm;
		}
		elseif($log['status'] == 4)
		{
			$folder = 'fw_pm.gif';
			$msg_alt = $lang->fwd_pm;
		}

		$table->construct_cell("<img src=\"../images/{$folder}\" alt=\"{$msg_alt}\" title=\"{$msg_alt}\" />", array("width" => 1));
		$table->construct_cell("<a href=\"javascript:MyBB.popupWindow('index.php?module=tools-pmlog&amp;action=view&amp;pmid={$log['pmid']}', 'log_entry', 450, 450);\">{$log['subject']}</a>");
		$find_from = "<div class=\"float_right\"><a href=\"index.php?module=tools-pmlog&amp;fromid={$log['fromid']}\"><img src=\"styles/{$page->style}/images/icons/find.gif\" title=\"{$lang->find_pms_by_user}\" alt=\"{$lang->find}\" /></a></div>";
		if(!$log['from_username'])
		{
			$table->construct_cell("{$find_from}<div>{$lang->deleted_user}</div>");
		}
		else
		{
			$from_username = format_name($log['from_username'], $log['from_usergroup'], $log['from_displaygroup']);
			$table->construct_cell("{$find_from}<div><a href=\"../".get_profile_link($log['fromid'])."\">{$from_username}</a></div>");
		}
		$find_to = "<div class=\"float_right\"><a href=\"index.php?module=tools-pmlog&amp;toid={$log['toid']}\"><img src=\"styles/{$page->style}/images/icons/find.gif\" title=\"{$lang->find_pms_to_user}\" alt=\"{$lang->find}\" /></a></div>"; 
		if(!$log['to_username'])
		{
			$table->construct_cell("{$find_to}<div>{$lang->deleted_user}</div>");
		}
		else
		{
			$to_username = format_name($log['to_username'], $log['to_usergroup'], $log['to_displaygroup']);
			$table->construct_cell("{$find_to}<div><a href=\"../".get_profile_link($log['toid'])."\">{$to_username}</a></div>");
		}
		$table->construct_cell($log['dateline'], array("class" => "align_center"));
		$table->construct_row();
	}
	
	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_logs, array("colspan" => "5"));
		$table->construct_row();
		$table->output($lang->private_message_log);
	}
	else
	{
		$table->output($lang->private_message_log);
	}

	$query = $db->simple_select("privatemessages p", "COUNT(p.pmid) as logs", "1=1 {$additional_sql_criteria}");
	$total_rows = $db->fetch_field($query, "logs");

	echo draw_admin_pagination($mybb->input['page'], $per_page, $total_rows, "index.php?module=tools-pmlog&amp;page={page}{$additional_criteria}")."<br />";

	$form = new Form("index.php?module=tools-pmlog", "post");
	$form_container = new FormContainer($lang->filter_private_message_log);

	$user_folder = array(
		"1" => $lang->inbox,
		"2" => $lang->sent_items,
		"3" => $lang->drafts,
		"4" => $lang->trash_can,
		"5" => $lang->other,
	);

	$form_container->output_row($lang->folder, "", $form->generate_select_box('folder', $user_folder, $mybb->input['folder'], array('id' => 'folder')), 'folder');	
	$form_container->output_row($lang->subject_contains, "", $form->generate_text_box('subject', $mybb->input['subject'], array('id' => 'subject')), 'subject');	
	$form_container->output_row($lang->from_username, "", $form->generate_text_box('fromname', $mybb->input['fromname'], array('id' => 'fromname')), 'fromname');
	$form_container->output_row($lang->to_username, "", $form->generate_text_box('toname', $mybb->input['toname'], array('id' => 'toname')), 'toname');
	$form_container->end();

	// Autocompletion for usernames
		echo '
		<script type="text/javascript" src="../jscripts/autocomplete.js?ver=140"></script>
		<script type="text/javascript">
		<!--
			new autoComplete("to_username", "../xmlhttp.php?action=get_users", {valueSpan: "username"});
			new autoComplete("from_username", "../xmlhttp.php?action=get_users", {valueSpan: "username"});
		// -->
	</script>';

	$buttons[] = $form->generate_submit_button($lang->filter_private_message_log);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}
?>