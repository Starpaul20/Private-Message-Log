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

// Tell MyBB when to run the hooks
$plugins->add_hook("private_send_start", "pmlog_lang");

$plugins->add_hook("admin_tools_menu_logs", "pmlog_admin_menu");
$plugins->add_hook("admin_tools_action_handler", "pmlog_admin_action_handler");
$plugins->add_hook("admin_tools_permissions", "pmlog_admin_permissions");
$plugins->add_hook("admin_tools_get_admin_log_action", "pmlog_admin_adminlog");

// The information that shows up on the plugin manager
function pmlog_info()
{
	global $lang;
	$lang->load("tools_pmlog");

	return array(
		"name"				=> $lang->pmlog_info_name,
		"description"		=> $lang->pmlog_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is activated.
function pmlog_activate()
{
	global $db;

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("private_send", "#".preg_quote('{$codebuttons}')."#i", '{$codebuttons}{$message_logging}');

	change_admin_permission('tools', 'pmlog');
}

// This function runs when the plugin is deactivated.
function pmlog_deactivate()
{
	global $db;

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("private_send", "#".preg_quote('{$message_logging}')."#i", '', 0);

	change_admin_permission('tools', 'pmlog', -1);
}

// PM log warning on send page
function pmlog_lang()
{
	global $lang, $message_logging;
	$lang->load("admin/tools_pmlog");

	$message_logging = "<br /><span class=\"smalltext\"><em>{$lang->private_message_logging}</em></span>";
}

// Admin CP log page
function pmlog_admin_menu($sub_menu)
{
	global $lang;
	$lang->load("tools_pmlog");

	$sub_menu['120'] = array('id' => 'pmlog', 'title' => $lang->private_message_log, 'link' => 'index.php?module=tools-pmlog');

	return $sub_menu;
}

function pmlog_admin_action_handler($actions)
{
	$actions['pmlog'] = array('active' => 'pmlog', 'file' => 'pmlog.php');

	return $actions;
}

function pmlog_admin_permissions($admin_permissions)
{
  	global $db, $mybb, $lang;
	$lang->load("tools_pmlog");

	$admin_permissions['pmlog'] = $lang->can_manage_pm_logs;

	return $admin_permissions;
}

// Admin Log display
function pmlog_admin_adminlog($plugin_array)
{
  	global $lang;
	$lang->load("tools_pmlog");

	return $plugin_array;
}

?>