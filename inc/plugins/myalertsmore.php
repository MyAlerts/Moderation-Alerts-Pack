<?php
/**
 * MyAlerts Moderation Alerts Pack 1.0
 * 
 * Provides additional actions related to moderation for @euantor's MyAlerts plugin.
 *
 * @package MyAlerts Moderation Alerts Pack 1.0beta4
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license (same as MyAlerts)
 * @version 1.0
 */
 
if (!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if(!defined("PLUGINLIBRARY"))
{
	define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

function myalertsmore_info()
{
	return array(
		'name'          =>  'MyAlerts Extension',
		'description'   =>  'Provides additional actions related to moderation for @euantor\'s <a href="http://community.mybb.com/thread-127444.html"><b>MyAlerts</b></a> plugin.<br /><span style="color:#ff9090">MyAlerts is required for MyAlerts Moderation Alerts Pack to work</span>.',
		'website'       =>  'http://euantor.com/myalerts',
		'author'        =>  'Shade',
		'authorsite'    =>  '9f724627ed35cb4a41ee5453f09ee384',
		'version'       =>  '1.0',
		'compatibility' =>  '16*',
		);
}

function myalertsmore_is_installed()
{
	global $mybb;
	
	// MyAlerts Extension obviously adds some settings. Just check a random one, if not present then the plugin isn't installed
	if($mybb->settings['myalerts_alert_warn'])
	{
		return true;
	}
}

function myalertsmore_install()
{
	global $db, $PL, $lang, $mybb;

	if (!file_exists(PLUGINLIBRARY))
	{
		flash_message("The selected plugin could not be installed because <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing.", "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	// check if a random myalerts setting exist - if false, then MyAlerts is not installed, warn the user and redirect him
	if(!$mybb->settings['myalerts_alert_rep'])
	{
		flash_message("The selected plugin could not be installed because <a href=\"http://mods.mybb.com/view/myalerts\">MyAlerts</a> is not installed yet.", "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	
	// add extra hooks - needed for some alerts
	$PL->edit_core('myalertsmore', 'warnings.php',
				array(
				// warn alert
					array('search' => '$db->update_query("users", $updated_user, "uid=\'{$user[\'uid\']}\'");',
                     	  'before' => '$plugins->run_hooks("warnings_do_warn_end");'),
				// revoke warn alert
					array('search' => 'redirect("warnings.php?action=view&wid={$warning[\'wid\']}", $lang->redirect_warning_revoked);',
                     	  'before' => '$plugins->run_hooks("warnings_do_revoke_end");'),
					 ),
               true);
			   
	$PL->edit_core('myalertsmore', 'moderation.php',
				array(
				// close multiple threads alert
					array('search' => '$moderation->close_threads($threads);',
                     	  'before' => '$plugins->run_hooks("moderation_multiclosethreads");'),
				// close single thread alert, merged in close multiple threads alert
					array('search' => '$redirect = $lang->redirect_closethread;',
                     	  'after' => '$plugins->run_hooks("moderation_closesinglethread");'),
				// open multiple threads alert
					array('search' => '$moderation->open_threads($threads);',
                     	  'before' => '$plugins->run_hooks("moderation_multiopenthreads");'),
				// open single thread alert, merged in open multiple threads alert
					array('search' => '$redirect = $lang->redirect_openthread;',
                     	  'after' => '$plugins->run_hooks("moderation_opensinglethread");'),
				// delete multiple threads alert
					array('search' => '$tid = intval($tid);',
                     	  'after' => '$plugins->run_hooks("moderation_multideletethreads");'),
				// move multiple threads alert
					array('search' => '$moderation->move_threads($tids, $moveto);',
                     	  'after' => '$plugins->run_hooks("moderation_multimovethreads");'),
				// move single thread alert
					array('search' => 'moderation_redirect(get_thread_link($newtid), $lang->redirect_threadmoved);',
                     	  'before' => '$plugins->run_hooks("moderation_movesinglethread");'),
					 ),
               true);
			   
	$PL->edit_core('myalertsmore', 'xmlhttp.php',
				array(
				// quick edit alert
					array('search' => 'get_post_attachments($post[\'pid\'], $post);',
                     	  'after' => '$plugins->run_hooks("xmlhttp_do_quickedit");'),
					 ),
               true);
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	// search for myalerts existing settings and add our custom ones
	$query = $db->simple_select("settinggroups", "gid", "name='myalerts'");
	$gid = intval($db->fetch_field($query, "gid"));
	
	$myalertsmore_settings_1 = array(
		"name" => "myalerts_alert_warn",
		"title" => $lang->setting_myalertsmore_alert_warn,
		"description" => $lang->setting_myalertsmore_alert_warn_desc,
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => "20",
		"gid" => $gid,
	);
	$myalertsmore_settings_2 = array(
		"name" => "myalerts_alert_revokewarn",
		"title" => $lang->setting_myalertsmore_alert_revokewarn,
		"description" => $lang->setting_myalertsmore_alert_revokewarn_desc,
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => "21",
		"gid" => $gid,
	);
	$myalertsmore_settings_3 = array(
		"name" => "myalerts_alert_multideletethreads",
		"title" => $lang->setting_myalertsmore_alert_multideletethreads,
		"description" => $lang->setting_myalertsmore_alert_multideletethreads_desc,
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => "22",
		"gid" => $gid,
	);
	$myalertsmore_settings_4 = array(
		"name" => "myalerts_alert_multiclosethreads",
		"title" => $lang->setting_myalertsmore_alert_multiclosethreads,
		"description" => $lang->setting_myalertsmore_alert_multiclosethreads_desc,
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => "23",
		"gid" => $gid,
	);
	$myalertsmore_settings_5 = array(
		"name" => "myalerts_alert_multiopenthreads",
		"title" => $lang->setting_myalertsmore_alert_multiopenthreads,
		"description" => $lang->setting_myalertsmore_alert_multiopenthreads_desc,
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => "24",
		"gid" => $gid,
	);
	$myalertsmore_settings_6 = array(
		"name" => "myalerts_alert_multimovethreads",
		"title" => $lang->setting_myalertsmore_alert_multimovethreads,
		"description" => $lang->setting_myalertsmore_alert_multimovethreads_desc,
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => "25",
		"gid" => $gid,
	);
	$myalertsmore_settings_7 = array(
		"name" => "myalerts_alert_editpost",
		"title" => $lang->setting_myalertsmore_alert_editpost,
		"description" => $lang->setting_myalertsmore_alert_editpost_desc,
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => "26",
		"gid" => $gid,
	);
	$db->insert_query("settings", $myalertsmore_settings_1);
	$db->insert_query("settings", $myalertsmore_settings_2);
	$db->insert_query("settings", $myalertsmore_settings_3);
	$db->insert_query("settings", $myalertsmore_settings_4);
	$db->insert_query("settings", $myalertsmore_settings_5);
	$db->insert_query("settings", $myalertsmore_settings_6);
	$db->insert_query("settings", $myalertsmore_settings_7);
	
	// Set our alerts on for all users by default, maintaining existing alerts values
    // Declare a data array containing all our alerts settings we'd like to add. To default them, the array must be associative and keys must be set to "on" (active) or 0 (not active)
    $possible_settings = array(
            'warn' => "on",
            'revokewarn' => "on",
            'multideletethreads' => "on",
            'multiclosethreads' => "on",
            'multiopenthreads' => "on",
            'multimovethreads' => "on",
            'editpost' => "on",
            );
    
    $query = $db->simple_select('users', 'uid, myalerts_settings', '', array());
    
    while($settings = $db->fetch_array($query))
    {
        // decode existing alerts with corresponding key values. json_decode func returns an associative array by default, we don't need to edit it
        $alert_settings = json_decode($settings['myalerts_settings']);
        
        // merge our settings with existing ones...
        $my_settings = array_merge($possible_settings, (array) $alert_settings);
        
        // and update the table cell, encoding our modified array and paying attention to SQL inj (thanks Nathan!)
        $db->update_query('users', array('myalerts_settings' => $db->escape_string(json_encode($my_settings))), 'uid='.(int) $settings['uid']);
    }
		
	// rebuild settings
	rebuild_settings();

}

function myalertsmore_uninstall()
{
	global $db, $PL;
	
	if (!file_exists(PLUGINLIBRARY))
	{
		flash_message("The selected plugin could not be uninstalled because <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing.", "error");
		admin_redirect("index.php?module=config-plugins");
	}

	$PL or require_once PLUGINLIBRARY;
	
	// restore core edits we've done in installation process
	$PL->edit_core('myalertsmore', 'warnings.php',
               array(),
               true);
	$PL->edit_core('myalertsmore', 'moderation.php',
               array(),
               true);
	$PL->edit_core('myalertsmore', 'xmlhttp.php',
               array(),
               true);
			   	
	$db->write_query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN('myalerts_alert_warn','myalerts_alert_revokewarn','myalerts_alert_multideletethreads','myalerts_alert_multiclosethreads','myalerts_alert_multiopenthreads','myalerts_alert_multimovethreads','myalerts_alert_editpost')");
		
	// rebuild settings
	rebuild_settings();
}

// WARN AN USER

// firstly, add the actual alert...
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_warn'])
{
	$plugins->add_hook('warnings_do_warn_end', 'myalertsmore_addAlert_warn');
}
function myalertsmore_addAlert_warn()
{
	global $mybb, $Alerts, $user, $points, $warning_expires;
	
	$Alerts->addAlert((int) $user['uid'], 'warn', 0, (int) $mybb->user['uid'], array(
		'points'  =>  $points,
		'expires'  =>  $warning_expires,
		)
	);
}

// ... secondly, generate text and similar stuff...
$plugins->add_hook('myalerts_alerts_output_start', 'myalertsmore_parseAlert_warn');
function myalertsmore_parseAlert_warn(&$alert)
{
	global $mybb, $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	if ($alert['alert_type'] == 'warn' AND $mybb->user['myalerts_settings']['warn'])
	{
		$alert['expires'] = my_date($mybb->settings['dateformat'], $alert['content']['expires']).", ".my_date($mybb->settings['timeformat'], $alert['content']['expires']);
		$alert['message'] = $lang->sprintf($lang->myalertsmore_warn, $alert['user'], $alert['content']['points'], $alert['dateline'], $alert['expires']);
		$alert['rowType'] = 'warnAlert';
	}
}

// ... and thirdly, add settings into UCP!
$plugins->add_hook('myalerts_possible_settings', 'myalertsmore_alerts_warn');
function myalertsmore_alerts_warn(&$possible_settings)
{
	global $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	$possible_settings[] = 'warn';
}


// REVOKE A WARNING

// firstly, add the actual alert...
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_revokewarn'])
{
	$plugins->add_hook('warnings_do_revoke_end', 'myalertsmore_addAlert_revokewarn');
}
function myalertsmore_addAlert_revokewarn()
{
	global $mybb, $Alerts, $warning;
	
	$Alerts->addAlert((int) $warning['uid'], 'revokewarn', 0, (int) $mybb->user['uid'], array(
		'points'  =>  $warning['points'],
	));
}

// ... secondly, generate text and similar stuff...
$plugins->add_hook('myalerts_alerts_output_start', 'myalertsmore_parseAlert_revokewarn');
function myalertsmore_parseAlert_revokewarn(&$alert)
{
	global $mybb, $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	if ($alert['alert_type'] == 'revokewarn' AND $mybb->user['myalerts_settings']['revokewarn'])
	{
		$alert['message'] = $lang->sprintf($lang->myalertsmore_revokewarn, $alert['user'], $alert['content']['points'], $alert['dateline']);
		$alert['rowType'] = 'revokewarnAlert';
	}
}

// ... and thirdly, add settings into UCP!
$plugins->add_hook('myalerts_possible_settings', 'myalertsmore_alerts_revokewarn');
function myalertsmore_alerts_revokewarn(&$possible_settings)
{
	global $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	$possible_settings[] = 'revokewarn';
}


// DELETE MULTIPLE THREADS & SINGLE THREAD

// firstly, add the actual alert...
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_multideletethreads'])
{
	$plugins->add_hook('moderation_do_deletethread', 'myalertsmore_addAlert_singledeletethread');
	$plugins->add_hook('moderation_multideletethreads', 'myalertsmore_addAlert_multideletethreads');
}
// multiple threads
function myalertsmore_addAlert_multideletethreads()
{
	global $mybb, $Alerts, $tid;
	
	// the code is hooked into a foreach loop, just alert the user after getting thread's infos
	$thread = get_thread($tid);
	$Alerts->addAlert((int) $thread['uid'], 'multideletethreads', 0, (int) $mybb->user['uid'], array(
		'subject'  =>  $thread['subject'],
	));
}
// single thread
function myalertsmore_addAlert_singledeletethread()
{
	global $mybb, $Alerts, $thread;
	
	$Alerts->addAlert((int) $thread['uid'], 'multideletethreads', 0, (int) $mybb->user['uid'], array(
		'subject'  =>  $thread['subject'],
	));
}

// ... secondly, generate text and similar stuff...
$plugins->add_hook('myalerts_alerts_output_start', 'myalertsmore_parseAlert_multideletethreads');
function myalertsmore_parseAlert_multideletethreads(&$alert)
{
	global $mybb, $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	if ($alert['alert_type'] == 'multideletethreads' AND $mybb->user['myalerts_settings']['multideletethreads'])
	{
		$alert['message'] = $lang->sprintf($lang->myalertsmore_multideletethreads, $alert['user'], htmlspecialchars_uni($alert['content']['subject']), $alert['dateline']);
		$alert['rowType'] = 'multideletethreadsAlert';
	}
}

// ... and thirdly, add settings into UCP!
$plugins->add_hook('myalerts_possible_settings', 'myalertsmore_alerts_multideletethreads');
function myalertsmore_alerts_multideletethreads(&$possible_settings)
{
	global $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	$possible_settings[] = 'multideletethreads';
}


// CLOSE MULTIPLE THREADS & SINGLE THREAD

// firstly, add the actual alert...
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_multiclosethreads'])
{
	$plugins->add_hook('moderation_multiclosethreads', 'myalertsmore_addAlert_multiclosethreads');
	$plugins->add_hook('moderation_closesinglethread', 'myalertsmore_addAlert_closesinglethread');
}
// multiple closing alert
function myalertsmore_addAlert_multiclosethreads()
{
	global $mybb, $Alerts, $threads;
	
	// multiple threads tid stored in $threads array, loop alert execution
	foreach($threads as $tid) {
		// get single thread data
		$thread = get_thread($tid);
		if($mybb->user['uid'] != $thread['uid']) {
		// we only want to notify the thread's author when the thread is being closed but it must be opened. Mods can close threads already closed accidentally, so just check if thread is not already closed and if it is, notify the user
			if($thread['closed'] != 1) {
				$Alerts->addAlert((int) $thread['uid'], 'multiclosethreads', (int) $thread['tid'], (int) $mybb->user['uid'], array(
					'subject'  =>  $thread['subject'],
					'tid'  =>  $thread['tid'],
				));
			}
		}
	}
}
// single closing alert
function myalertsmore_addAlert_closesinglethread()
{
	global $mybb, $Alerts, $thread;
	
	if($mybb->user['uid'] != $thread['uid']) {
	// we positioned the hook already in a closed/opened check, so we can alert directly the user
		$Alerts->addAlert((int) $thread['uid'], 'multiclosethreads', (int) $thread['tid'], (int) $mybb->user['uid'], array(
			'subject'  =>  $thread['subject'],
			'tid'  =>  $thread['tid'],
		));
	}
}

// ... secondly, generate text and similar stuff...
$plugins->add_hook('myalerts_alerts_output_start', 'myalertsmore_parseAlert_multiclosethreads');
function myalertsmore_parseAlert_multiclosethreads(&$alert)
{
	global $mybb, $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	if ($alert['alert_type'] == 'multiclosethreads' AND $mybb->user['myalerts_settings']['multiclosethreads'])
	{
		$alert['threadLink'] = get_thread_link($alert['content']['tid']);
		$alert['message'] = $lang->sprintf($lang->myalertsmore_multiclosethreads, $alert['user'], htmlspecialchars_uni($alert['content']['subject']), $alert['dateline'], $alert['threadLink']);
		$alert['rowType'] = 'multiclosethreadsAlert';
	}
}

// ... and thirdly, add settings into UCP!
$plugins->add_hook('myalerts_possible_settings', 'myalertsmore_alerts_multiclosethreads');
function myalertsmore_alerts_multiclosethreads(&$possible_settings)
{
	global $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	$possible_settings[] = 'multiclosethreads';
}

// OPEN MULTIPLE THREADS & SINGLE THREAD

// firstly, add the actual alert...
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_multiopenthreads'])
{
	$plugins->add_hook('moderation_multiopenthreads', 'myalertsmore_addAlert_multiopenthreads');
	$plugins->add_hook('moderation_opensinglethread', 'myalertsmore_addAlert_opensinglethread');
}
// multiple opening alert
function myalertsmore_addAlert_multiopenthreads()
{
	global $mybb, $Alerts, $threads;
	
	// multiple threads tid stored in $threads array, loop alert execution
	foreach($threads as $tid) {
		// get single thread data
		$thread = get_thread($tid);
		if($mybb->user['uid'] != $thread['uid']) {
		// we only want to notify the thread's author when the thread is being opened but it must be closed. Mods can open threads already opened accidentally, so just check if thread is not already opened and if it is, notify the user
			if($thread['closed'] == 1) {
				$Alerts->addAlert((int) $thread['uid'], 'multiopenthreads', (int) $thread['tid'], (int) $mybb->user['uid'], array(
					'subject'  =>  $thread['subject'],
					'tid'  =>  $thread['tid'],
				));
			}
		}
	}
}
// single opening alert
function myalertsmore_addAlert_opensinglethread()
{
	global $mybb, $Alerts, $thread;
	
	if($mybb->user['uid'] != $thread['uid']) {
	// we positioned the hook already in a closed/opened check, so we can alert directly the user
		$Alerts->addAlert((int) $thread['uid'], 'multiopenthreads', (int) $thread['tid'], (int) $mybb->user['uid'], array(
			'subject'  =>  $thread['subject'],
			'tid'  =>  $thread['tid'],
		));
	}
}

// ... secondly, generate text and similar stuff...
$plugins->add_hook('myalerts_alerts_output_start', 'myalertsmore_parseAlert_multiopenthreads');
function myalertsmore_parseAlert_multiopenthreads(&$alert)
{
	global $mybb, $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	if ($alert['alert_type'] == 'multiopenthreads' AND $mybb->user['myalerts_settings']['multiopenthreads'])
	{
		$alert['threadLink'] = get_thread_link($alert['content']['tid']);
		$alert['message'] = $lang->sprintf($lang->myalertsmore_multiopenthreads, $alert['user'], htmlspecialchars_uni($alert['content']['subject']), $alert['dateline'], $alert['threadLink']);
		$alert['rowType'] = 'multiopenthreadsAlert';
	}
}

// ... and thirdly, add settings into UCP!
$plugins->add_hook('myalerts_possible_settings', 'myalertsmore_alerts_multiopenthreads');
function myalertsmore_alerts_multiopenthreads(&$possible_settings)
{
	global $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	$possible_settings[] = 'multiopenthreads';
}


// MOVE MULTIPLE THREADS & SINGLE THREAD

// firstly, add the actual alert...
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_multimovethreads'])
{
	$plugins->add_hook('moderation_multimovethreads', 'myalertsmore_addAlert_multimovethreads');
	$plugins->add_hook('moderation_movesinglethread', 'myalertsmore_addAlert_movesinglethread');
}
// multiple threads
function myalertsmore_addAlert_multimovethreads()
{
	global $mybb, $Alerts, $tids, $newforum;
	
	// some optimizations to keep a low # of queries. Since threads are moved in the same forum, we can generate its link immediately, saving a lot of queries. The forum's name is already stored into $newforum array, we don't need to query more over
	$forumLink = get_forum_link($newforum['fid']);
	// multiple threads tid stored in $tids array, loop alert execution
	foreach($tids as $tid) {
		// get single thread data
		$thread = get_thread($tid);
		// moderators are the only actual users allowed to move threads. But if the thread they're moving belongs to themselves, then it's annoying. Check this out and react depending on the situation.
		if($mybb->user['uid'] != $thread['uid']) {
			$Alerts->addAlert((int) $thread['uid'], 'multimovethreads', (int) $thread['tid'], (int) $mybb->user['uid'], array(
				'subject'  =>  $thread['subject'],
				'tid'  =>  $thread['tid'],
				'forumName'  =>  $newforum['name'],
				'forumLink'  =>  $forumLink,
			));
		}
	}
}
// single thread
function myalertsmore_addAlert_movesinglethread()
{
	global $mybb, $Alerts, $newtid, $newforum;
	
	// see multiple threads moving above...
	$forumLink = get_forum_link($newforum['fid']);
	$thread = get_thread($newtid);
	// moderators are the only actual users allowed to move threads. But if the thread they're moving belongs to themselves, then it's annoying. Check this out and react depending on the situation.
	if($mybb->user['uid'] != $thread['uid']) {
		$Alerts->addAlert((int) $thread['uid'], 'multimovethreads', (int) $thread['tid'], (int) $mybb->user['uid'], array(
			'subject'  =>  $thread['subject'],
			'tid'  =>  $thread['tid'],
			'forumName'  =>  $newforum['name'],
			'forumLink'  =>  $forumLink,
		));
	}
}

// ... secondly, generate text and similar stuff...
$plugins->add_hook('myalerts_alerts_output_start', 'myalertsmore_parseAlert_multimovethreads');
function myalertsmore_parseAlert_multimovethreads(&$alert)
{
	global $mybb, $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	if ($alert['alert_type'] == 'multimovethreads' AND $mybb->user['myalerts_settings']['multimovethreads'])
	{
		$alert['threadLink'] = get_thread_link($alert['content']['tid']);
		$alert['message'] = $lang->sprintf($lang->myalertsmore_multimovethreads, $alert['user'], htmlspecialchars_uni($alert['content']['subject']), $alert['dateline'], $alert['threadLink'], $alert['content']['forumName'], $alert['content']['forumLink']);
		$alert['rowType'] = 'multimovethreadsAlert';
	}
}

// ... and thirdly, add settings into UCP!
$plugins->add_hook('myalerts_possible_settings', 'myalertsmore_alerts_multimovethreads');
function myalertsmore_alerts_multimovethreads(&$possible_settings)
{
	global $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	$possible_settings[] = 'multimovethreads';
}


// EDIT A POST, QUICK AND FULL

// firstly, add the actual alert...
if ($settings['myalerts_enabled'] AND $settings['myalerts_alert_editpost'])
{
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
	if($postinfo['uid'] != $mybb->user['uid'])
	{
		$Alerts->addAlert((int) $postinfo['uid'], 'editpost', (int) $postinfo['tid'], (int) $mybb->user['uid'], array(
			'pid'  =>  $post['pid'],
			'tid'  =>  $postinfo['tid'],
		));
	}
}
// quick edit
function myalertsmore_addAlert_editpost_quick()
{
	global $mybb, $Alerts, $post;
	
	// check if post belongs to the user itself. Is it not? Then alert the user!
	if($post['uid'] != $mybb->user['uid'])
	{
		$Alerts->addAlert((int) $post['uid'], 'editpost', (int) $post['tid'], (int) $mybb->user['uid'], array(
			'pid'  =>  $post['pid'],
			'tid'  =>  $post['tid'],
		));
	}
}

// ... secondly, generate text and similar stuff...
$plugins->add_hook('myalerts_alerts_output_start', 'myalertsmore_parseAlert_editpost');
function myalertsmore_parseAlert_editpost(&$alert)
{
	global $mybb, $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	if ($alert['alert_type'] == 'editpost' AND $mybb->user['myalerts_settings']['editpost'])
	{
		$alert['postLink'] = $mybb->settings['bburl'].'/'.get_post_link($alert['content']['pid'], $alert['content']['tid']).'#pid'.$alert['content']['pid'];
		$alert['message'] = $lang->sprintf($lang->myalertsmore_editpost, $alert['user'], $alert['postLink'], $alert['dateline']);
		$alert['rowType'] = 'editpostAlert';
	}
}

// ... and thirdly, add settings into UCP!
$plugins->add_hook('myalerts_possible_settings', 'myalertsmore_alerts_editpost');
function myalertsmore_alerts_editpost(&$possible_settings)
{
	global $lang;
	
	if (!$lang->myalertsmore)
	{
		$lang->load('myalertsmore');
	}
	
	$possible_settings[] = 'editpost';
}