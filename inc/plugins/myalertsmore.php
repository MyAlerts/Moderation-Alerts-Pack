<?php
/**
 * Moderation Alerts Pack
 * 
 * Provides additional actions related to moderation for @euantor's MyAlerts plugin.
 *
 * @package Moderation Alerts Pack
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license (same as MyAlerts)
 * @version 1.1
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

function myalertsmore_info()
{
	return array(
		'name'			=>	'Moderation Alerts Pack',
		'description'	=>	'Provides several more actions related to moderation for @euantor\'s <a href="http://community.mybb.com/thread-127444.html"><b>MyAlerts</b></a> plugin.<br /><span style="color:#f00">MyAlerts is required for Moderation Alerts Pack to work</span>.',
		'website'		=>	'https://github.com/MyAlerts/Moderation-Alerts-Pack',
		'author'		=>	'Shade',
		'authorsite'	=>	'http://www.idevicelab.net/forum',
		'version'		=>	'1.1',
		'compatibility'	=>	'16*',
		'guid'			=>	'9f724627ed35cb4a41ee5453f09ee384'
	);
}

function myalertsmore_is_installed()
{
	global $cache;
	
	$info = myalertsmore_info();
	$installed = $cache->read("shade_plugins");
	if ($installed[$info['name']]) {
		return true;
	}
}

function myalertsmore_install()
{
	global $db, $PL, $lang, $mybb, $cache;
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message("The selected plugin could not be installed because <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing.", "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	// check if myalerts table exist - if false, then MyAlerts is not installed, warn the user and redirect him
	if (!$db->table_exists('alerts')) {
		flash_message("The selected plugin could not be installed because <a href=\"http://mods.mybb.com/view/myalerts\">MyAlerts</a> is not installed. Moderation Alerts Pack requires MyAlerts to be installed in order to properly work.", "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	$info = myalertsmore_info();
	$shadePlugins = $cache->read('shade_plugins');
	$shadePlugins[$info['name']] = array(
		'title'		=>	$info['name'],
		'version'	=>	$info['version']
	);
	$cache->update('shade_plugins', $shadePlugins);
	
	// add extra hooks - needed for some alerts
	$PL->edit_core('myalertsmore', 'warnings.php', array(
		// warn alert
		array(
			'search' => '$db->update_query("users", $updated_user, "uid=\'{$user[\'uid\']}\'");',
			'before' => '$plugins->run_hooks("warnings_do_warn_end");'
		),
		// revoke warn alert
		array(
			'search' => 'redirect("warnings.php?action=view&wid={$warning[\'wid\']}", $lang->redirect_warning_revoked);',
			'before' => '$plugins->run_hooks("warnings_do_revoke_end");'
		)
	), true);
	
	$PL->edit_core('myalertsmore', 'xmlhttp.php', array(
		// quick edit alert
		array(
			'search' => 'get_post_attachments($post[\'pid\'], $post);',
			'after' => '$plugins->run_hooks("xmlhttp_do_quickedit");'
		)
	), true);
	
	$PL->edit_core('myalertsmore', 'inc/class_moderation.php', array(
		// delete threads
		array(
			'search' => '$plugins->run_hooks("class_moderation_delete_thread", $tid);',
			'after' => '$args = array("thread" => &$thread);
$plugins->run_hooks("class_moderation_delete_thread_custom", $args);'
		),
		// move single thread
		array(
			'search' => '$arguments = array("tid" => $tid, "new_fid" => $new_fid);',
			'after' => '$arguments = array_merge($arguments, array("newforum" => &$newforum, "thread" => &$thread));',
			'multi' => true
		),
		// move multiple threads, inline moderation
		array(
			'search' => '$arguments = array("tids" => $tids, "moveto" => $moveto);',
			'after' => '$arguments["newforum"] = &$newforum;'
		),
		// delete post
		array(
			'search' => '$query = $db->query("
			SELECT p.pid, p.uid, p.fid, p.tid, p.visible, f.usepostcounts, t.visible as threadvisible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.pid=\'$pid\'
		");',
			'replace' => '$query = $db->query("
			SELECT p.pid, p.uid, p.fid, p.tid, p.visible, f.usepostcounts, t.visible as threadvisible, t.subject
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.pid=\'$pid\'
		");'
		),
		array(
			'search' => '$plugins->run_hooks("class_moderation_delete_post", $post[\'pid\']);',
			'after' => '$args = array("post" => &$post);
$plugins->run_hooks("class_moderation_delete_post_custom", $args);'
		)
	), true);
	
	$PL->edit_core('myalertsmore', 'inc/datahandlers/user.php', array(
		// change username alert
		array(
			'search' => '$plugins->run_hooks("datahandler_user_update", $this);',
			'after' => '$args = array("this" => &$this, "old_user" => &$old_user);
$plugins->run_hooks("datahandler_user_update_user", $args);'
		)
	), true);
	
	if (!$lang->myalertsmore) {
		$lang->load('myalertsmore');
	}
	
	$query = $db->simple_select("settinggroups", "gid", "name='myalerts'");
	$gid = (int) $db->fetch_field($query, "gid");
	
	$myalertsmore_settings = array();
	
	$myalertsmore_settings[] = array(
		"name" => "warn",
		"title" => $lang->setting_myalertsmore_alert_warn,
		"description" => $lang->setting_myalertsmore_alert_warn_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	$myalertsmore_settings[] = array(
		"name" => "revokewarn",
		"title" => $lang->setting_myalertsmore_alert_revokewarn,
		"description" => $lang->setting_myalertsmore_alert_revokewarn_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	$myalertsmore_settings[] = array(
		"name" => "multideletethreads",
		"title" => $lang->setting_myalertsmore_alert_multideletethreads,
		"description" => $lang->setting_myalertsmore_alert_multideletethreads_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	$myalertsmore_settings[] = array(
		"name" => "multiclosethreads",
		"title" => $lang->setting_myalertsmore_alert_multiclosethreads,
		"description" => $lang->setting_myalertsmore_alert_multiclosethreads_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	$myalertsmore_settings[] = array(
		"name" => "multiopenthreads",
		"title" => $lang->setting_myalertsmore_alert_multiopenthreads,
		"description" => $lang->setting_myalertsmore_alert_multiopenthreads_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	$myalertsmore_settings[] = array(
		"name" => "multimovethreads",
		"title" => $lang->setting_myalertsmore_alert_multimovethreads,
		"description" => $lang->setting_myalertsmore_alert_multimovethreads_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	$myalertsmore_settings[] = array(
		"name" => "editpost",
		"title" => $lang->setting_myalertsmore_alert_editpost,
		"description" => $lang->setting_myalertsmore_alert_editpost_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	$myalertsmore_settings[] = array(
		"name" => "multideleteposts",
		"title" => $lang->setting_myalertsmore_alert_multideleteposts,
		"description" => $lang->setting_myalertsmore_alert_multideleteposts_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	$myalertsmore_settings[] = array(
		"name" => "suspendposting",
		"title" => $lang->setting_myalertsmore_alert_suspendposting,
		"description" => $lang->setting_myalertsmore_alert_suspendposting_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	$myalertsmore_settings[] = array(
		"name" => "moderateposting",
		"title" => $lang->setting_myalertsmore_alert_moderateposting,
		"description" => $lang->setting_myalertsmore_alert_moderateposting_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	$myalertsmore_settings[] = array(
		"name" => "suspendsignature",
		"title" => $lang->setting_myalertsmore_alert_suspendsignature,
		"description" => $lang->setting_myalertsmore_alert_suspendsignature_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	$myalertsmore_settings[] = array(
		"name" => "changeusername",
		"title" => $lang->setting_myalertsmore_alert_changeusername,
		"description" => $lang->setting_myalertsmore_alert_changeusername_desc,
		"optionscode" => "yesno",
		"value" => "1"
	);
	
	$i = 20;
	foreach($myalertsmore_settings as $setting)
	{
		$setting['name'] = "myalerts_alert_".$setting['name'];
		$setting['disporder'] = $i;
		$setting['gid'] = $gid;
		
		$db->insert_query("settings", $setting);
		$i++;
	}
	
	$insertArray = array(
		0 => array(
			'code' => 'warn'
		),
		1 => array(
			'code' => 'revokewarn'
		),
		2 => array(
			'code' => 'multideletethreads'
		),
		3 => array(
			'code' => 'multiclosethreads'
		),
		4 => array(
			'code' => 'multiopenthreads'
		),
		5 => array(
			'code' => 'multimovethreads'
		),
		6 => array(
			'code' => 'editpost'
		),
		7 => array(
			'code' => 'multideleteposts'
		),
		8 => array(
			'code' => 'suspendposting'
		),
		9 => array(
			'code' => 'moderateposting'
		),
		10 => array(
			'code' => 'suspendsignature'
		),
		11 => array(
			'code' => 'changeusername'
		)
	);
	
	$db->insert_query_multiple('alert_settings', $insertArray);
	
	$query = $db->simple_select('users', 'uid');
	while ($uids = $db->fetch_array($query)) {
		$users[] = $uids['uid'];
	}
	
	$query = $db->simple_select("alert_settings", "id", "code IN ('warn', 'revokewarn', 'multideletethreads', 'multiclosethreads', 'multiopenthreads', 'multimovethreads', 'editpost', 'multideleteposts', 'suspendposting', 'moderateposting', 'suspendsignature', 'changeusername')");
	while ($setting = $db->fetch_array($query)) {
		$settings[] = $setting['id'];
	}
	
	foreach ($users as $user) {
		foreach ($settings as $setting) {
			$userSettings[] = array(
				'user_id' => (int) $user,
				'setting_id' => (int) $setting,
				'value' => 1
			);
		}
	}
	
	$db->insert_query_multiple('alert_setting_values', $userSettings);
	
	rebuild_settings();
	
}

function myalertsmore_uninstall()
{
	global $db, $PL, $cache;
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message("The selected plugin could not be uninstalled because <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing.", "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	// restore core edits we've done in installation process
	$PL->edit_core('myalertsmore', 'warnings.php', array(), true);
	$PL->edit_core('myalertsmore', 'xmlhttp.php', array(), true);
	$PL->edit_core('myalertsmore', 'inc/class_moderation.php', array(), true);
	$PL->edit_core('myalertsmore', 'inc/datahandlers/user.php', array(), true);
	
	// delete ACP settings
	$db->write_query("DELETE FROM " . TABLE_PREFIX . "settings WHERE name IN('myalerts_alert_warn','myalerts_alert_revokewarn','myalerts_alert_multideletethreads','myalerts_alert_multiclosethreads','myalerts_alert_multiopenthreads','myalerts_alert_multimovethreads','myalerts_alert_editpost','myalerts_alert_multideleteposts','myalerts_alert_suspendposting','myalerts_alert_moderateposting','myalerts_alert_suspendsignature','myalerts_alert_changeusername')");
	
	// delete existing values
	$query = $db->simple_select("alert_settings", "id", "code IN ('warn', 'revokewarn', 'multideletethreads', 'multiclosethreads', 'multiopenthreads', 'multimovethreads', 'editpost', 'multideleteposts', 'suspendposting', 'moderateposting', 'suspendsignature', 'changeusername')");
	while ($setting = $db->fetch_array($query)) {
		$settings[] = $setting['id'];
	}
	$settings = implode(",", $settings);
	
	// truly delete them
	if(!empty($settings)) {
		$db->delete_query("alert_setting_values", "setting_id IN ({$settings})");
	}
	// delete UCP settings
	$db->delete_query("alert_settings", "code IN ('warn', 'revokewarn', 'multideletethreads', 'multiclosethreads', 'multiopenthreads', 'multimovethreads', 'editpost', 'multideleteposts', 'suspendposting', 'moderateposting', 'suspendsignature', 'changeusername')");
	
	$info = myalertsmore_info();
	// delete the plugin from cache
	$shadePlugins = $cache->read('shade_plugins');
	unset($shadePlugins[$info['name']]);
	$cache->update('shade_plugins', $shadePlugins);
	// rebuild settings
	rebuild_settings();
}

// load our custom lang file into MyAlerts
$plugins->add_hook('myalerts_load_lang', 'myalertsmore_load_lang');
function myalertsmore_load_lang()
{
	global $lang;
	
	if (!$lang->myalertsmore) {
		$lang->load('myalertsmore');
	}
}

// generate text and stuff like that - fixes #1
$plugins->add_hook('myalerts_alerts_output_start', 'myalertsmore_parseAlerts');
function myalertsmore_parseAlerts(&$alert)
{
	global $mybb, $lang;
	
	if (!$lang->myalertsmore) {
		$lang->load('myalertsmore');
	}
	
	// warn
	if ($alert['alert_type'] == 'warn' AND $mybb->user['myalerts_settings']['warn']) {
		$alert['expires'] = my_date($mybb->settings['dateformat'], $alert['content']['expires']) . ", " . my_date($mybb->settings['timeformat'], $alert['content']['expires']);
		$alert['message'] = $lang->sprintf($lang->myalertsmore_warn, $alert['user'], $alert['content']['points'], $alert['dateline'], $alert['expires']);
		$alert['rowType'] = 'warnAlert';
	}
	// revoke warn
	elseif ($alert['alert_type'] == 'revokewarn' AND $mybb->user['myalerts_settings']['revokewarn']) {
		$alert['message'] = $lang->sprintf($lang->myalertsmore_revokewarn, $alert['user'], $alert['content']['points'], $alert['dateline']);
		$alert['rowType'] = 'revokewarnAlert';
	}
	// delete threads
		elseif ($alert['alert_type'] == 'multideletethreads' AND $mybb->user['myalerts_settings']['multideletethreads']) {
		$alert['message'] = $lang->sprintf($lang->myalertsmore_multideletethreads, $alert['user'], htmlspecialchars_uni($alert['content']['subject']), $alert['dateline']);
		$alert['rowType'] = 'multideletethreadsAlert';
	}
	// close threads
		elseif ($alert['alert_type'] == 'multiclosethreads' AND $mybb->user['myalerts_settings']['multiclosethreads']) {
		$alert['threadLink'] = get_thread_link($alert['content']['tid']);
		$alert['message'] = $lang->sprintf($lang->myalertsmore_multiclosethreads, $alert['user'], htmlspecialchars_uni($alert['content']['subject']), $alert['dateline'], $alert['threadLink']);
		$alert['rowType'] = 'multiclosethreadsAlert';
	}
	// open threads
		elseif ($alert['alert_type'] == 'multiopenthreads' AND $mybb->user['myalerts_settings']['multiopenthreads']) {
		$alert['threadLink'] = get_thread_link($alert['content']['tid']);
		$alert['message'] = $lang->sprintf($lang->myalertsmore_multiopenthreads, $alert['user'], htmlspecialchars_uni($alert['content']['subject']), $alert['dateline'], $alert['threadLink']);
		$alert['rowType'] = 'multiopenthreadsAlert';
	}
	// move threads
		elseif ($alert['alert_type'] == 'multimovethreads' AND $mybb->user['myalerts_settings']['multimovethreads']) {
		$alert['threadLink'] = get_thread_link($alert['content']['tid']);
		$alert['message'] = $lang->sprintf($lang->myalertsmore_multimovethreads, $alert['user'], htmlspecialchars_uni($alert['content']['subject']), $alert['dateline'], $alert['threadLink'], $alert['content']['forumName'], $alert['content']['forumLink']);
		$alert['rowType'] = 'multimovethreadsAlert';
	}
	// edit posts
		elseif ($alert['alert_type'] == 'editpost' AND $mybb->user['myalerts_settings']['editpost']) {
		$alert['postLink'] = $mybb->settings['bburl'] . '/' . get_post_link($alert['content']['pid'], $alert['content']['tid']) . '#pid' . $alert['content']['pid'];
		$alert['message'] = $lang->sprintf($lang->myalertsmore_editpost, $alert['user'], $alert['postLink'], $alert['dateline']);
		$alert['rowType'] = 'editpostAlert';
	}
	// delete posts
		elseif ($alert['alert_type'] == 'multideleteposts' AND $mybb->user['myalerts_settings']['multideleteposts']) {
		$alert['message'] = $lang->sprintf($lang->myalertsmore_multideleteposts, $alert['user'], $alert['content']['threadUrl'], $alert['dateline'], $alert['content']['threadName']);
		$alert['rowType'] = 'multideletepostsAlert';
	}
	// posting suspension
		elseif ($alert['alert_type'] == 'suspendposting' AND $mybb->user['myalerts_settings']['suspendposting']) {
		// workaround for different alert into one setting - pretty cool uh?
		if ($alert['content']['unsuspendCheck']) {
			$alert['message'] = $lang->sprintf($lang->myalertsmore_unsuspendposting, $alert['user'], $alert['dateline']);
			$alert['rowType'] = 'unsuspendpostingAlert';
		} else {
			// permanent suspension?
			if ($alert['content']['expireDate'] == "0") {
				$alert['expiryDate'] = $lang->myalertsmore_expire_never;
			} else {
				$alert['expiryDate'] = my_date($mybb->settings['dateformat'], $alert['content']['expireDate']) . ", " . my_date($mybb->settings['timeformat'], $alert['content']['expireDate']);
			}
			$alert['message'] = $lang->sprintf($lang->myalertsmore_suspendposting, $alert['user'], $alert['expiryDate'], $alert['dateline']);
			$alert['rowType'] = 'suspendpostingAlert';
		}
	}
	// posting moderation
		elseif ($alert['alert_type'] == 'moderateposting' AND $mybb->user['myalerts_settings']['moderateposting']) {
		// workaround for different alert into one setting - pretty cool uh?
		if ($alert['content']['unsuspendCheck']) {
			$alert['message'] = $lang->sprintf($lang->myalertsmore_unmoderateposting, $alert['user'], $alert['dateline']);
			$alert['rowType'] = 'unmoderatepostingAlert';
		} else {
			// permanent suspension?
			if ($alert['content']['expireDate'] == "0") {
				$alert['expiryDate'] = $lang->myalertsmore_expire_never;
			} else {
				$alert['expiryDate'] = my_date($mybb->settings['dateformat'], $alert['content']['expireDate']) . ", " . my_date($mybb->settings['timeformat'], $alert['content']['expireDate']);
			}
			$alert['message'] = $lang->sprintf($lang->myalertsmore_moderateposting, $alert['user'], $alert['expiryDate'], $alert['dateline']);
			$alert['rowType'] = 'moderatepostingAlert';
		}
	}
	// signature suspension
		elseif ($alert['alert_type'] == 'suspendsignature' AND $mybb->user['myalerts_settings']['suspendsignature']) {
		// workaround for different alert into one setting - pretty cool uh?
		if ($alert['content']['unsuspendCheck']) {
			$alert['message'] = $lang->sprintf($lang->myalertsmore_unsuspendsignature, $alert['user'], $alert['dateline']);
			$alert['rowType'] = 'unsuspendsignatureAlert';
		} else {
			// permanent suspension?
			if ($alert['content']['expireDate'] == "0") {
				$alert['expiryDate'] = $lang->myalertsmore_expire_never;
			} else {
				$alert['expiryDate'] = my_date($mybb->settings['dateformat'], $alert['content']['expireDate']) . ", " . my_date($mybb->settings['timeformat'], $alert['content']['expireDate']);
			}
			$alert['message'] = $lang->sprintf($lang->myalertsmore_suspendsignature, $alert['user'], $alert['expiryDate'], $alert['dateline']);
			$alert['rowType'] = 'suspendsignatureAlert';
		}
	}
	// change username
	elseif ($alert['alert_type'] == 'changeusername' AND $mybb->user['myalerts_settings']['changeusername']) {
		$alert['message'] = $lang->sprintf($lang->myalertsmore_changeusername, $alert['user'], $alert['content']['oldName'], $alert['content']['newName'], $alert['dateline']);
		$alert['rowType'] = 'changeusernameAlert';
	}
}

// Generate the actual alerts

// WARN AN USER
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_warn']) {
	$plugins->add_hook('warnings_do_warn_end', 'myalertsmore_addAlert_warn');
}
function myalertsmore_addAlert_warn()
{
	global $mybb, $Alerts, $user, $points, $warning_expires;
	
	$Alerts->addAlert((int) $user['uid'], 'warn', 0, (int) $mybb->user['uid'], array(
		'points' => $points,
		'expires' => $warning_expires
	));
}


// REVOKE A WARNING
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_revokewarn']) {
	$plugins->add_hook('warnings_do_revoke_end', 'myalertsmore_addAlert_revokewarn');
}
function myalertsmore_addAlert_revokewarn()
{
	global $mybb, $Alerts, $warning;
	
	$Alerts->addAlert((int) $warning['uid'], 'revokewarn', 0, (int) $mybb->user['uid'], array(
		'points' => $warning['points']
	));
}


// DELETE ANY KIND OF THREAD
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_multideletethreads']) {
	$plugins->add_hook('class_moderation_delete_thread_custom', 'myalertsmore_addAlert_deletethread');
}
function myalertsmore_addAlert_deletethread(&$args)
{
	global $mybb, $Alerts;
	
	$thread = $args['thread'];
		
	if ($mybb->user['uid'] != $thread['uid']) {
		$Alerts->addAlert((int) $thread['uid'], 'multideletethreads', (int) $thread['tid'], (int) $mybb->user['uid'], array(
			'subject' => $thread['subject']
		));
	}
}


// CLOSE ANY KIND OF THREAD
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_multiclosethreads']) {
	$plugins->add_hook('class_moderation_close_threads', 'myalertsmore_addAlert_closethreads');
}
function myalertsmore_addAlert_closethreads($tids)
{
	global $mybb, $Alerts;
	
	foreach ($tids as $tid) {
		$thread = get_thread($tid);
		if ($mybb->user['uid'] != $thread['uid']) {
			// we only want to notify the thread's author when the thread is being closed but it must be opened. Mods can close threads already closed accidentally, so just check if thread is not already closed and if it passes the check, notify the user
			if ($thread['closed'] != 1) {
				$Alerts->addAlert((int) $thread['uid'], 'multiclosethreads', (int) $thread['tid'], (int) $mybb->user['uid'], array(
					'subject' => $thread['subject'],
					'tid' => $thread['tid']
				));
			}
		}
	}
}


// OPEN ANY KIND OF THREAD
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_multiopenthreads']) {
	$plugins->add_hook('class_moderation_open_threads', 'myalertsmore_addAlert_openthreads');
}
function myalertsmore_addAlert_openthreads($tids)
{
	global $mybb, $Alerts;
	
	foreach ($tids as $tid) {
		$thread = get_thread($tid);
		if ($mybb->user['uid'] != $thread['uid']) {
			// we only want to notify the thread's author when the thread is being opened but it must be closed. Mods can open threads already opened accidentally, so just check if thread is not already opened and if it passes the check, notify the user
			if ($thread['closed'] == 1) {
				$Alerts->addAlert((int) $thread['uid'], 'multiopenthreads', (int) $thread['tid'], (int) $mybb->user['uid'], array(
					'subject' => $thread['subject'],
					'tid' => $thread['tid']
				));
			}
		}
	}
}


// MOVE ANY KIND OF THREAD
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_multimovethreads']) {
	$plugins->add_hook('class_moderation_move_simple', 'myalertsmore_addAlert_move_simple');
	$plugins->add_hook('class_moderation_move_threads', 'myalertsmore_addAlert_move_threads');
}
function myalertsmore_addAlert_move_simple(&$arguments)
{
	global $mybb, $Alerts;
	
	$newforum = $arguments['newforum'];
	$forumLink = get_forum_link($newforum['fid']);
	$thread = $arguments['thread'];
	// moderators are the only actual users allowed to move threads. But if the thread they're moving belongs to themselves, then it's annoying. Check this out and react depending on the situation.
	if ($mybb->user['uid'] != $thread['uid']) {
		$Alerts->addAlert((int) $thread['uid'], 'multimovethreads', (int) $thread['tid'], (int) $mybb->user['uid'], array(
			'subject' => $thread['subject'],
			'tid' => $thread['tid'],
			'forumName' => $newforum['name'],
			'forumLink' => $forumLink
		));
	}
}
// inline moderation
function myalertsmore_addAlert_move_threads(&$arguments)
{
	global $mybb, $Alerts;
	
	$newforum = $arguments['newforum'];
	$forumLink = get_forum_link($newforum['fid']);
	foreach ($arguments['tids'] as $tid) {
		$thread = get_thread($tid);
		if ($mybb->user['uid'] != $thread['uid']) {
			$Alerts->addAlert((int) $thread['uid'], 'multimovethreads', (int) $thread['tid'], (int) $mybb->user['uid'], array(
				'subject' => $thread['subject'],
				'tid' => $thread['tid'],
				'forumName' => $newforum['name'],
				'forumLink' => $forumLink
			));
		}
	}
}


// EDIT A POST, QUICK AND FULL
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_editpost']) {
	$plugins->add_hook('editpost_do_editpost_end', 'myalertsmore_addAlert_editpost');
	$plugins->add_hook('xmlhttp_do_quickedit', 'myalertsmore_addAlert_editpost_quick');
}
// full edit
function myalertsmore_addAlert_editpost()
{
	global $mybb, $Alerts, $post;
	
	// we need to check a few things, retrieve data
	$postinfo = get_post($post['pid']);
	
	// check if post belongs to the user itself. Is it not? Then alert the user!
	if ($postinfo['uid'] != $mybb->user['uid']) {
		$Alerts->addAlert((int) $postinfo['uid'], 'editpost', (int) $postinfo['tid'], (int) $mybb->user['uid'], array(
			'pid' => $post['pid'],
			'tid' => $postinfo['tid']
		));
	}
}
// quick edit
function myalertsmore_addAlert_editpost_quick()
{
	global $mybb, $Alerts, $post;
	
	// check if post belongs to the user itself. Is it not? Then alert the user!
	if ($post['uid'] != $mybb->user['uid']) {
		$Alerts->addAlert((int) $post['uid'], 'editpost', (int) $post['tid'], (int) $mybb->user['uid'], array(
			'pid' => $post['pid'],
			'tid' => $post['tid']
		));
	}
}


// DELETE ANY KIND OF POST
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_multideleteposts']) {
	$plugins->add_hook('class_moderation_delete_post_custom', 'myalertsmore_addAlert_delete_post');
}
function myalertsmore_addAlert_delete_post(&$args)
{
	global $mybb, $Alerts;
	
	$post = $args['post'];
	$threadUrl = get_thread_link($post['tid']);
	
	// check if post belongs to the user itself. Is doesn't? Then alert the user!
	if ($post['uid'] != $mybb->user['uid']) {
		$Alerts->addAlert((int) $post['uid'], 'multideleteposts', (int) $post['tid'], (int) $mybb->user['uid'], array(
			'threadUrl' => $threadUrl,
			'threadName' => $post['subject']
		));
	}
}


// SUSPEND POSTING, MODERATE POSTING, SUSPEND SIGNATURE & OPPOSITES
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_suspendposting']) {
	$plugins->add_hook('modcp_do_editprofile_update', 'myalertsmore_addAlert_suspensions');
	// ACP hook - the code there is copy&pasted from modcp.php, just hook to the same function
	$plugins->add_hook('admin_user_users_edit_commit', 'myalertsmore_addAlert_suspensions');
}
function myalertsmore_addAlert_suspensions()
{
	global $mybb, $Alerts, $user, $extra_user_updates, $option, $db, $sort_options;
	
	// MyAlerts isn't instantiated. My apologies ACP... but we've got the solution for ya!
	if (isset($sort_options['username'])) {
		require_once MYALERTS_PLUGIN_PATH . 'Alerts.class.php';
		try {
			$Alerts = new Alerts($mybb, $db);
		}
		catch (Exception $e) {
			die($e->getMessage());
		}
	}
	
	// suspend posting...
	if (!empty($extra_user_updates['suspendposting'])) {
		$Alerts->addAlert((int) $user['uid'], 'suspendposting', 0, (int) $mybb->user['uid'], array(
			'expireDate' => $extra_user_updates['suspensiontime']
		));
	}
	// ... moderate posting
	elseif (!empty($extra_user_updates['moderateposts'])) {
		$Alerts->addAlert((int) $user['uid'], 'moderateposting', 0, (int) $mybb->user['uid'], array(
			'expireDate' => $extra_user_updates['moderationtime']
		));
	}
	// must be a revoke of posting suspension...
		elseif (!$mybb->input['suspendposting'] AND !empty($user['suspendposting'])) {
		$Alerts->addAlert((int) $user['uid'], 'suspendposting', 0, (int) $mybb->user['uid'], array(
			'unsuspendCheck' => 1 // MyAlerts doesn't display any alert if it hasn't its corresponding UCP setting. Let's workaround this!
		));
	}
	// must be a revoke of posting moderation...
		elseif (!$mybb->input['moderateposting'] AND !empty($user['moderateposts'])) {
		$Alerts->addAlert((int) $user['uid'], 'moderateposting', 0, (int) $mybb->user['uid'], array(
			'unsuspendCheck' => 1
		));
	}
	// suspend signature!
	if (!empty($extra_user_updates['suspendsignature'])) {
		$Alerts->addAlert((int) $user['uid'], 'suspendsignature', 0, (int) $mybb->user['uid'], array(
			'expireDate' => $extra_user_updates['suspendsigtime']
		));
	}
	// must be a revoke of signature suspension...
	elseif (!$mybb->input['suspendsignature'] AND !empty($user['suspendsignature'])) {
		$Alerts->addAlert((int) $user['uid'], 'suspendsignature', 0, (int) $mybb->user['uid'], array(
			'unsuspendCheck' => 1
		));
	}
}


// CHANGE USERNAME
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_changeusername']) {
	$plugins->add_hook('datahandler_user_update_user', 'myalertsmore_addAlert_changeusername');
}
function myalertsmore_addAlert_changeusername(&$args)
{
	global $mybb, $db, $Alerts;
	
	// MyAlerts isn't instantiated. My apologies ACP... but we've got the solution for ya!
	if (!isset($Alerts)) {
		require_once MYALERTS_PLUGIN_PATH . 'Alerts.class.php';
		try {
			$Alerts = new Alerts($mybb, $db);
		}
		catch (Exception $e) {
			die($e->getMessage());
		}
	}
	
	$user = $args['this']->data;
	$old_user = $args['old_user'];
	
	if(isset($user['username']) AND $mybb->user['uid'] != $user['uid'])
	{
		$Alerts->addAlert((int) $user['uid'], 'changeusername', 0, (int) $mybb->user['uid'], array(
			'oldName' => $old_user['username'],
			'newName' => $user['username']
		));
	}
}

/** 
 * Debug function
 *
 * return mixed Any data that can be debugged
 *
 **/
function myalertsmore_debug($data) {
	echo "<pre>";
	echo print_r($data);
	echo "</pre>";
	exit;
}