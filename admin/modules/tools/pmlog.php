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

$sub_tabs['pm_logs'] = array(
	'title' => $lang->private_message_log,
	'link' => "index.php?module=tools-pmlog",
	'description' => $lang->private_message_log_desc
);

$sub_tabs['prune_pm_logs'] = array(
	'title' => $lang->prune_private_messages,
	'link' => "index.php?module=tools-pmlog&amp;action=prune",
	'description' => $lang->prune_private_messages_desc
);

if($mybb->input['action'] == "view")
{
	$query = $db->query("
		SELECT p.*, r.username AS to_username, f.username AS from_username
		FROM ".TABLE_PREFIX."privatemessages p
		LEFT JOIN ".TABLE_PREFIX."users r ON (r.uid=p.toid)
		LEFT JOIN ".TABLE_PREFIX."users f ON (f.uid=p.fromid)
		WHERE p.pmid='".$mybb->get_input('pmid', MyBB::INPUT_INT)."'
	");
	$log = $db->fetch_array($query);

	if(!$log['pmid'])
	{
		exit;
	}

	require_once MYBB_ROOT."inc/class_parser.php";
	$parser = new postParser;

	$log['to_username'] = htmlspecialchars_uni($log['to_username']);
	$log['from_username'] = htmlspecialchars_uni($log['from_username']);
	$log['subject'] = htmlspecialchars_uni($log['subject']);
	$log['dateline'] = date($mybb->settings['dateformat'], $log['dateline']).", ".date($mybb->settings['timeformat'], $log['dateline']);

	if(empty($log['ipaddress']))
	{
		$ipaddress = $lang->na;
	}
	else
	{
		$ipaddress = my_inet_ntop($db->unescape_binary($log['ipaddress']));
	}

	// Parse PM text
	$parser_options = array(
		"allow_html" => $mybb->settings['pmsallowhtml'],
		"allow_mycode" => $mybb->settings['pmsallowmycode'],
		"allow_smilies" => $mybb->settings['pmsallowsmilies'],
		"allow_imgcode" => $mybb->settings['pmsallowimgcode'],
		"allow_videocode" => $mybb->settings['pmsallowvideocode'],
		"nl2br" => 1
	);
	$log['message'] = $parser->parse_message($log['message'], $parser_options);

	// Log admin action
	log_admin_action($log['pmid'], $log['from_username'], $log['fromid']);

?>
	<div class="modal">
	<div style="overflow-y: auto; max-height: 400px;">
	<style type="text/css">
blockquote {
	border: 1px solid #ccc;
	margin: 0;
	background: #fff;
	padding: 10px;
	-moz-border-radius: 6px;
	-webkit-border-radius: 6px;
	border-radius: 6px;
}

blockquote cite {
	font-weight: bold;
	border-bottom: 1px solid #ccc;
	font-style: normal;
	display: block;
	padding-bottom: 3px;
	margin: 0 0 10px 0;
}

blockquote cite span {
	float: right;
	font-weight: normal;
	font-size: 12px;
	color: #666;
}

blockquote cite span.highlight {
	float: none;
	font-weight: bold;
	padding-bottom: 0;
}

.codeblock {
	background: #fff;
	border: 1px solid #ccc;
	padding: 10px;
	-moz-border-radius: 6px;
	-webkit-border-radius: 6px;
	border-radius: 6px;
}

.codeblock .title {
	border-bottom: 1px solid #ccc;
	font-weight: bold;
	padding-bottom: 3px;
	margin: 0 0 10px 0;
}

.codeblock code {
	overflow: auto;
	height: auto;
	max-height: 200px;
	display: block;
	font-family: Monaco, Consolas, Courier, monospace;
	font-size: 13px;
}
	</style>
	<base href="<?php echo $mybb->settings['bburl'] ?>/" />
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

	$table->construct_cell($lang->ip_address.":");
	$table->construct_cell($ipaddress);
	$table->construct_row();

	$table->construct_cell($log['message'], array("colspan" => 2));
	$table->construct_row();

	$table->output($lang->private_message_log_viewer);

	?>
	</div>
	</div>
	<?php
exit;
}

if($mybb->input['action'] == "delete" && $mybb->request_method == "post")
{
	if(is_array($mybb->input['log']))
	{
		$log_ids = implode(",", array_map("intval", $mybb->input['log']));
		if($log_ids)
		{
			$db->delete_query("privatemessages", "pmid IN ({$log_ids})");
			$num_deleted = $db->affected_rows();
		}

		// Log admin action
		log_admin_action($num_deleted);
	}

	flash_message($lang->success_pruned_private_messages, 'success');
	admin_redirect("index.php?module=tools-pmlog");
}

if($mybb->input['action'] == "prune")
{
	if($mybb->request_method == 'post')
	{
		$mybb->input['older_than'] = $mybb->get_input('older_than', MyBB::INPUT_INT);
		$where = 'dateline < '.(TIME_NOW-($mybb->input['older_than']*86400));

		// Searching for entries sent by a particular user
		if($mybb->input['fromid'])
		{
			$where .= " AND fromid='".$mybb->get_input('fromid', MyBB::INPUT_INT)."'";
		}

		// Searching for entries received by a particular user
		if($mybb->input['toid'])
		{
			$where .= " AND toid='".$mybb->get_input('toid', MyBB::INPUT_INT)."'";
		}

		// Searching for entries in a specific folder
		if($mybb->input['folder'] > 0)
		{
			$folder = $mybb->get_input('folder', MyBB::INPUT_INT);

			if($folder == 5)
			{
				$where .= " AND folder > 4";
			}
			else
			{
				$where .= " AND folder='{$folder}'";
			}
		}

		// Searching for entries with a specific read status
		if($mybb->input['status'])
		{
			$status = $mybb->get_input('status', MyBB::INPUT_INT);

			if($status == 1)
			{
				$where .= " AND status >= 1";
			}
			elseif($status == 0)
			{
				$where .= " AND status='0'";
			}
		}

		$db->delete_query("privatemessages", $where);
		$num_deleted = $db->affected_rows();

		// If pruned, recount PMs
		if($mybb->input['fromid'])
		{
			$fromid = $mybb->get_input('fromid', MyBB::INPUT_INT);
			update_pm_count($fromid);
		}

		if($mybb->input['toid'])
		{
			$toid = $mybb->get_input('toid', MyBB::INPUT_INT);
			update_pm_count($toid);
		}

		// Log admin action
		log_admin_action($mybb->input['older_than'], $mybb->input['fromid'], $mybb->input['toid'], $mybb->input['folder'], $mybb->input['status'], $num_deleted);

		flash_message($lang->success_pruned_private_messages, 'success');
		admin_redirect("index.php?module=tools-pmlog");
	}

	$page->add_breadcrumb_item($lang->prune_private_messages, "index.php?module=tools-pmlog&amp;action=prune");
	$page->output_header($lang->prune_private_messages);
	$page->output_nav_tabs($sub_tabs, 'prune_pm_logs');

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = 'selected="selected"';
	$ordersel[$mybb->input['order']] = 'selected="selected"';

	$from_options[''] = $lang->all_users;
	$from_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT p.fromid, u.username
		FROM ".TABLE_PREFIX."privatemessages p
		LEFT JOIN ".TABLE_PREFIX."users u ON (p.fromid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$from_options[$user['fromid']] = htmlspecialchars_uni($user['username']);
	}

	$to_options[''] = $lang->all_users;
	$to_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT p.toid, u.username
		FROM ".TABLE_PREFIX."privatemessages p
		LEFT JOIN ".TABLE_PREFIX."users u ON (p.toid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$to_options[$user['toid']] = htmlspecialchars_uni($user['username']);
	}

	$form = new Form("index.php?module=tools-pmlog&amp;action=prune", "post");
	$form_container = new FormContainer($lang->prune_private_messages);
	$form_container->output_row($lang->from_user, "", $form->generate_select_box('fromid', $from_options, $mybb->input['fromid'], array('id' => 'fromid')), 'fromid');
	$form_container->output_row($lang->to_user, "", $form->generate_select_box('toid', $to_options, $mybb->input['toid'], array('id' => 'toid')), 'toid');

	$user_folder = array(
		"1" => $lang->inbox,
		"2" => $lang->sent_items,
		"3" => $lang->drafts,
		"4" => $lang->trash_can,
		"5" => $lang->other,
	);

	$form_container->output_row($lang->in_folder, "", $form->generate_select_box('folder', $user_folder, $mybb->input['folder'], array('id' => 'folder')), 'folder');

	$read_options = array(
		$form->generate_radio_button("status", "0", $lang->unread_only, array("id" => "status_unread")),
		$form->generate_radio_button("status", "1", $lang->read_only, array("id" => "status_read")),
		$form->generate_radio_button("status", "2", $lang->both, array("id" => "status_both"))
	);
	$form_container->output_row($lang->read_status, "", implode("<br />", $read_options));

	if(!$mybb->input['older_than'])
	{
		$mybb->input['older_than'] = '60';
	}
	$form_container->output_row($lang->date_range, "", $lang->older_than.$form->generate_numeric_field('older_than', $mybb->input['older_than'], array('id' => 'older_than', 'style' => 'width: 50px', 'min' => 0)).' '.$lang->days, 'older_than');
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->prune_private_messages);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{	
	if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$per_page = $mybb->settings['threadsperpage'];

	if(!$per_page)
	{
		$per_page = 20;
	}

	if($mybb->input['page'] && $mybb->input['page'] > 1)
	{
		$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
		$start = ($mybb->input['page']*$per_page)-$per_page;
	}
	else
	{
		$mybb->input['page'] = 1;
		$start = 0;
	}

	$additional_criteria = array();

	$toid = $mybb->get_input('toid', MyBB::INPUT_INT);

	$fromid = $mybb->get_input('fromid', MyBB::INPUT_INT);

	$subject = $db->escape_string_like($mybb->input['subject']);

	$folder = $mybb->get_input('folder', MyBB::INPUT_INT);

	// Begin criteria filtering
	if(!$folder)
	{
		$folder = 1;
	}

	if($folder == 5)
	{
		$additional_sql_criteria .= " AND p.folder > 4";
		$additional_criteria[] = "folder > 4";
	}
	else
	{
		$additional_sql_criteria .= " AND p.folder = '{$folder}'";
		$additional_criteria[] = "folder={$folder}";
	}

	if($mybb->input['subject'])
	{
		$additional_sql_criteria .= " AND p.subject LIKE '%{$subject}%'";
		$additional_criteria[] = "subject=".urlencode($mybb->input['subject']);
	}

	if($mybb->input['fromid'])
	{
		$query = $db->simple_select("users", "uid, username", "uid='{$fromid}'");
		$user = $db->fetch_array($query);
		$additional_sql_criteria .= " AND p.fromid='{$fromid}'";
		$additional_criteria[] = "fromid={$fromid}";
	}
	else if($mybb->input['fromname'])
	{
		$user = get_user_by_username($mybb->input['fromname'], array('fields' => 'uid, username'));

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
		$query = $db->simple_select("users", "uid, username", "uid='{$toid}'");
		$user = $db->fetch_array($query);
		$additional_sql_criteria .= " AND p.toid='{$toid}'";
		$additional_criteria[] = "toid={$toid}";
	}
	else if($mybb->input['toname'])
	{
		$user = get_user_by_username($mybb->input['toname'], array('fields' => 'uid, username'));

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

	$page->output_nav_tabs($sub_tabs, 'pm_logs');

	$form = new Form("index.php?module=tools-pmlog&amp;action=delete", "post");

	$table = new Table;
	$table->construct_header($form->generate_check_box("allbox", 1, '', array('class' => 'checkall')));
	$table->construct_header($lang->subject, array("colspan" => 2));
	$table->construct_header($lang->from, array("class" => "align_center", "width" => "20%"));
	$table->construct_header($lang->to, array("class" => "align_center", "width" => "20%"));
	$table->construct_header($lang->date_sent, array("class" => "align_center", "width" => "20%"));
	$table->construct_header($lang->ip_address, array("class" => "align_center", 'width' => '10%'));

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
		$table->construct_cell($form->generate_check_box("log[{$log['pmid']}]", $log['pmid'], ''), array("width" => 1));
		$log['subject'] = htmlspecialchars_uni($log['subject']);
		$log['dateline'] = date($mybb->settings['dateformat'], $log['dateline']).", ".date($mybb->settings['timeformat'], $log['dateline']);

		$msg_alt = $folder = '';
		// Determine Folder Icon
		if($log['status'] == 0)
		{
			$folder = 'new_pm.png';
			$msg_alt = $lang->new_pm;
		}
		elseif($log['status'] == 1)
		{
			$folder = 'old_pm.png';
			$msg_alt = $lang->old_pm;
		}
		elseif($log['status'] == 3)
		{
			$folder = 're_pm.png';
			$msg_alt = $lang->reply_pm;
		}
		elseif($log['status'] == 4)
		{
			$folder = 'fw_pm.png';
			$msg_alt = $lang->fwd_pm;
		}

		$table->construct_cell("<img src=\"../images/{$folder}\" alt=\"{$msg_alt}\" title=\"{$msg_alt}\" />", array("width" => 1));
		$table->construct_cell("<a href=\"javascript:MyBB.popupWindow('index.php?module=tools-pmlog&amp;action=view&amp;pmid={$log['pmid']}', null, true);\">{$log['subject']}</a>");
		$find_from = "<div class=\"float_right\"><a href=\"index.php?module=tools-pmlog&amp;fromid={$log['fromid']}\"><img src=\"styles/{$page->style}/images/icons/find.png\" title=\"{$lang->find_pms_by_user}\" alt=\"{$lang->find}\" /></a></div>";
		if(!$log['from_username'])
		{
			$table->construct_cell("{$find_from}<div>{$lang->deleted_user}</div>");
		}
		else
		{
			$from_username = format_name(htmlspecialchars_uni($log['from_username']), $log['from_usergroup'], $log['from_displaygroup']);
			$table->construct_cell("{$find_from}<div><a href=\"../".get_profile_link($log['fromid'])."\">{$from_username}</a></div>");
		}
		$find_to = "<div class=\"float_right\"><a href=\"index.php?module=tools-pmlog&amp;toid={$log['toid']}\"><img src=\"styles/{$page->style}/images/icons/find.png\" title=\"{$lang->find_pms_to_user}\" alt=\"{$lang->find}\" /></a></div>"; 
		if(!$log['to_username'])
		{
			$table->construct_cell("{$find_to}<div>{$lang->deleted_user}</div>");
		}
		else
		{
			$to_username = format_name(htmlspecialchars_uni($log['to_username']), $log['to_usergroup'], $log['to_displaygroup']);
			$table->construct_cell("{$find_to}<div><a href=\"../".get_profile_link($log['toid'])."\">{$to_username}</a></div>");
		}

		$table->construct_cell($log['dateline'], array("class" => "align_center"));

		if(empty($log['ipaddress']))
		{
			$ipaddress = $lang->na;
		}
		else
		{
			$ipaddress = my_inet_ntop($db->unescape_binary($log['ipaddress']));
		}

		$table->construct_cell($ipaddress, array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_logs, array("colspan" => "7"));
		$table->construct_row();
		$table->output($lang->private_message_log);
	}
	else
	{
		$table->output($lang->private_message_log);
		$buttons[] = $form->generate_submit_button($lang->delete_selected, array('onclick' => "return confirm('{$lang->confirm_delete_pms}');"));
		$form->output_submit_wrapper($buttons);
	}

	$form->end();

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
	<link rel="stylesheet" href="../jscripts/select2/select2.css">
	<script type="text/javascript" src="../jscripts/select2/select2.min.js?ver=1804"></script>
	<script type="text/javascript">
	<!--
	$("#fromname").select2({
		placeholder: "'.$lang->search_for_a_user.'",
		minimumInputLength: 2,
		multiple: false,
		ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
			url: "../xmlhttp.php?action=get_users",
			dataType: \'json\',
			data: function (term, page) {
				return {
					query: term // search term
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				return {results: data};
			}
		},
		initSelection: function(element, callback) {
			var query = $(element).val();
			if (query !== "") {
				$.ajax("../xmlhttp.php?action=get_users&getone=1", {
					data: {
						query: query
					},
					dataType: "json"
				}).done(function(data) { callback(data); });
			}
		}
	});
	$("#toname").select2({
		placeholder: "'.$lang->search_for_a_user.'",
		minimumInputLength: 2,
		multiple: false,
		ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
			url: "../xmlhttp.php?action=get_users",
			dataType: \'json\',
			data: function (term, page) {
				return {
					query: term // search term
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				return {results: data};
			}
		},
		initSelection: function(element, callback) {
			var query = $(element).val();
			if (query !== "") {
				$.ajax("../xmlhttp.php?action=get_users&getone=1", {
					data: {
						query: query
					},
					dataType: "json"
				}).done(function(data) { callback(data); });
			}
		}
	});
	// -->
	</script>';

	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->filter_private_message_log);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}
?>