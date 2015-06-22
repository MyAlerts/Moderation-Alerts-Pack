<?php
/**
 * Moderation Alerts Pack
 * 
 * Provides additional actions related to moderation for @euantor's MyAlerts plugin.
 *
 * @package Moderation Alerts Pack
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license (same as MyAlerts)
 * @version 3.0
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

// All the available alerts should be placed here	
$GLOBALS['alertslist'] = array("warn", "revokewarn", "deletethreads", "closethreads", "openthreads", "movethreads", "editpost", "deleteposts", "suspendposting", "moderateposting", "suspendsignature", "changeusername", "changesignature", "approvethreads", "unapprovethreads");

function myalertsmore_info()
{
	$installed = false;
	if (function_exists('myalerts_is_installed')) {
		$installed = myalerts_is_installed();
	}
	
	$myalerts_notice = '';
	if (!$installed) {
		$myalerts_notice = '<br /><span style="color:#f00">MyAlerts 2.0.2 or higher is required for Moderation Alerts Pack</span>.';
	}


	$info = array(
		'name'			=>	'Moderation Alerts Pack',
		'description'	=>	'Provides several more actions related to moderation for @euantor\'s <a href="http://community.mybb.com/thread-171301.html"><b>MyAlerts</b></a> plugin.' . $myalerts_notice,
		'website'		=>	'https://github.com/MyAlerts/Moderation-Alerts-Pack',
		'author'		=>	'Shade',
		'version'		=>	'3.0',
		'compatibility'	=>	'18*'
	);
	
	return $info;
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
	global $db, $PL, $lang, $mybb, $cache, $alertslist;
	
	if (!$lang->myalertsmore) {
		$lang->load('myalertsmore');
	}
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->myalertsmore_error_plmissing, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	if ($PL->version < 9) {
		flash_message($lang->myalertsmore_error_plnotuptodate, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	// MyAlerts installation check
	if (!$db->table_exists('alerts')) {
		flash_message($lang->myalertsmore_error_myalertsnotinstalled, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	// Core edits routines
	if (myalertsmore_fixmoderation() == false) $errors[] = $lang->myalertsmore_error_moderation;
	if (myalertsmore_fixuserhandler() == false) $errors[] = $lang->myalertsmore_error_userhandler;
	
	if (!empty($errors)) {
	
		foreach($errors as $error) {
			$output.= "<li>".$error."</li>\n";
		}
		
		flash_message($lang->sprintf($lang->myalertsmore_error_missingperms, $output), "error");
		admin_redirect("index.php?module=config-plugins");
		
	}
	
	$info = myalertsmore_info();
	$shadePlugins = $cache->read('shade_plugins');
	$shadePlugins[$info['name']] = array(
		'title'		=>	$info['name'],
		'version'	=>	$info['version']
	);
	$cache->update('shade_plugins', $shadePlugins);

	// Register our alerts!
	$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);

	$alertTypesToAdd = array();
	foreach ($alertslist as $type) {
		$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode($type);
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypesToAdd[] = $alertType;
	}

	$alertTypeManager->addTypes($alertTypesToAdd);
	
}

function myalertsmore_uninstall()
{
	global $db, $PL, $cache, $alertslist, $lang;
	
	if (!$lang->myalertsmore) {
		$lang->load("myalertsmore");
	}
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->myalertsmore_error_plmissing, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	if ($PL->version < 9) {
		flash_message($lang->myalertsmore_error_plnotuptodate, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	// Revert core edits we've done in installation process
	$PL->edit_core('myalertsmore', 'inc/class_moderation.php', array(), true);
	$PL->edit_core('myalertsmore', 'inc/datahandlers/user.php', array(), true);
	
	// Delete ACP settings
	if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
	    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
	
	    if (!$alertTypeManager) {
	        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
	    }
	    
		foreach ($alertslist as $type) {
	    	$alertTypeManager->deleteByCode($type);
	    }
	}
	
	// Delete the plugin from cache
	$info = myalertsmore_info();
	$shadePlugins = $cache->read('shade_plugins');
	unset($shadePlugins[$info['name']]);
	$cache->update('shade_plugins', $shadePlugins);
}

// Apply core patches
function myalertsmore_fixmoderation()
{
	global $PL;
	
	$PL or require_once PLUGINLIBRARY;
	
	return $PL->edit_core('myalertsmore', 'inc/class_moderation.php', array(
		// Move multiple threads, inline moderation
		array(
			'search' => '$arguments = array("tids" => $tids, "moveto" => $moveto);',
			'after' => '$arguments["newforum"] = &$newforum;'
		),
		// Delete post
		array(
			'search' => 'SELECT p.pid, p.uid, p.fid, p.tid, p.visible, t.visible as threadvisible',
			'replace' => 'SELECT p.pid, p.uid, p.fid, p.tid, p.visible, t.visible as threadvisible, t.subject'
		),
		array(
			'search' => '$plugins->run_hooks("class_moderation_delete_post", $post[\'pid\']);',
			'after' => '$args = array("post" => &$post);
$plugins->run_hooks("class_moderation_delete_post_custom", $args);'
		)
	), true);
}

function myalertsmore_fixuserhandler() {
	global $PL;
	$PL or require_once PLUGINLIBRARY;	
	return $PL->edit_core('myalertsmore', 'inc/datahandlers/user.php', array(
		// change username alert
		array(
			'search' => '$plugins->run_hooks("datahandler_user_update", $this);',
			'after' => '$args = array("this" => &$this, "old_user" => &$old_user);
$plugins->run_hooks("datahandler_user_update_user", $args);'
		)
	), true);
}

$plugins->add_hook('global_start', 'myalertsmore_register_formatters');
function myalertsmore_register_formatters()
{
	global $mybb, $lang;
	
	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
	    $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
	
	    if (!$formatterManager) {
	        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
	    }
		
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_WarnFormatter($mybb, $lang, 'warn'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_RevokeWarnFormatter($mybb, $lang, 'revokewarn'));
	    // Threads
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_DeleteThreadsFormatter($mybb, $lang, 'deletethreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_CloseThreadsFormatter($mybb, $lang, 'closethreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_OpenThreadsFormatter($mybb, $lang, 'openthreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_MoveThreadsFormatter($mybb, $lang, 'movethreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_ApproveThreadsFormatter($mybb, $lang, 'approvethreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_StickThreadsFormatter($mybb, $lang, 'stickthreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_UnstickThreadsFormatter($mybb, $lang, 'unstickthreads'));
	    // Posts
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_UnapproveThreadsFormatter($mybb, $lang, 'unapprovethreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_EditPostFormatter($mybb, $lang, 'editpost'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_DeletePostFormatter($mybb, $lang, 'deletepost'));
	    // Users
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_ChangeUsernameFormatter($mybb, $lang, 'changeusername'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_SuspensionsFormatter($mybb, $lang, 'moderateposting'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_SuspensionsFormatter($mybb, $lang, 'suspendposting'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_SuspensionsFormatter($mybb, $lang, 'moderatesignature'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_SuspensionsFormatter($mybb, $lang, 'suspendsignature'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_ChangeSignatureFormatter($mybb, $lang, 'changesignature'));
	}
}


// Generate the alerts
// INSERT/REVOKE A WARNING
$plugins->add_hook('datahandler_warnings_insert_warning', 'myalertsmore_addAlert_toggle_warn');
$plugins->add_hook('datahandler_warnings_update_warning', 'myalertsmore_addAlert_toggle_warn');
function myalertsmore_addAlert_toggle_warn(&$data)
{
	$warning = $data->data;
	
	$code = 'warn';
	if ($warning['reason']) {
		$code = 'revokewarn';
	}

    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode($code);
    
    if ($alertType != null and $alertType->getEnabled()) {
    
        $alert = new MybbStuff_MyAlerts_Entity_Alert((int) $warning['uid'], $alertType, 0);
        
        $extra_details = array(
        	'points' => $warning['points']
        );
        
        if ($code == 'warn') {
        	$extra_details['expires'] = $warning['expires'];
        }
        else {
	        $extra_details['reason'] = $warning['reason'];
        }
        
		$alert->setExtraDetails($extra_details);

        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
        
    }
}

// DELETE ANY KIND OF THREAD
$plugins->add_hook('class_moderation_delete_thread', 'myalertsmore_addAlert_delete_threads');
function myalertsmore_addAlert_delete_threads(&$tids)
{
	foreach ($tids as $tid) {
	
		$thread = get_thread($tid);
	
	    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('deletethreads');
	    
	    if ($alertType != null and $alertType->getEnabled() and $GLOBALS['mybb']->user['uid'] != $thread['uid']) {
	    
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			$alert->setExtraDetails(array(
				'subject' => $thread['subject']
			));
	
	        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
		
	}
}

// CLOSE ANY KIND OF THREAD
$plugins->add_hook('class_moderation_close_threads', 'myalertsmore_addAlert_close_threads');
function myalertsmore_addAlert_close_threads($tids)
{	
    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('closethreads');
    
    if ($alertType != null and $alertType->getEnabled()) {
	
		foreach ($tids as $tid) {
		
			$thread = get_thread($tid);
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($GLOBALS['mybb']->user['uid'] != $thread['uid']) {
			
				// We only want to notify the thread's author when the thread is opened and it's going to be closed
				if ($thread['closed'] != 1) {
						
					$alert->setExtraDetails(array(
						'subject' => $thread['subject']
					));

					MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
					
				}
				
			}
			
		}
		
	}
}

// OPEN ANY KIND OF THREAD
$plugins->add_hook('class_moderation_open_threads', 'myalertsmore_addAlert_open_threads');
function myalertsmore_addAlert_open_threads($tids)
{
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('openthreads');
    
    if ($alertType != null and $alertType->getEnabled()) {
	
		foreach ($tids as $tid) {
		
			$thread = get_thread($tid);
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($GLOBALS['mybb']->user['uid'] != $thread['uid']) {
			
				// We only want to notify the thread's author when the thread is closed and it's going to be opened
				if ($thread['closed'] == 1) {
						
					$alert->setExtraDetails(array(
						'subject' => $thread['subject']
					));

					MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
					
				}
				
			}
			
		}
		
	}
}

// MOVE ANY KIND OF THREAD
$plugins->add_hook('class_moderation_move_simple', 'myalertsmore_addAlert_move_threads_simple');
$plugins->add_hook('class_moderation_move_thread_redirect', 'myalertsmore_addAlert_move_threads_simple');
$plugins->add_hook('class_moderation_copy_thread', 'myalertsmore_addAlert_move_threads_simple');
function myalertsmore_addAlert_move_threads_simple(&$args)
{
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('movethreads');
    
    if ($alertType != null and $alertType->getEnabled()) {
	
		$forum = get_forum($args['new_tid']);
		$thread = get_thread($args['tid']);
		
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
		
		if ($GLOBALS['mybb']->user['uid'] != $thread['uid']) {
					
			$alert->setExtraDetails(array(
				'subject' => $thread['subject'],
				'destination_name' => $forum['name']
			));

			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
	
	}
}

// MOVE ANY KIND OF THREAD (INLINE MODERATION)
$plugins->add_hook('class_moderation_move_threads', 'myalertsmore_addAlert_move_threads_inline');
function myalertsmore_addAlert_move_threads_inline(&$args)
{
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('movethreads');
    
    if ($alertType != null and $alertType->getEnabled()) {
	
		$forum = get_forum($args['moveto']);
		
		foreach ($args['tids'] as $tid) {
		
			$thread = get_thread($tid);
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($GLOBALS['mybb']->user['uid'] != $thread['uid']) {
						
				$alert->setExtraDetails(array(
					'subject' => $thread['subject'],
					'destination_name' => $forum['name']
				));
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				
			}
		
		}
	
	}
}

// APPROVE/UNAPPROVE THREADS
$plugins->add_hook('class_moderation_approve_threads', 'myalertsmore_addAlert_toggle_thread_status');
$plugins->add_hook('class_moderation_unapprove_threads', 'myalertsmore_addAlert_toggle_thread_status');
function myalertsmore_addAlert_toggle_thread_status(&$tids)
{
	foreach ($tids as $tid) {
	
		$thread = get_thread($tid);
	
		$code = ($thread['visible'] != 1) ? 'approvethreads' : 'unapprovethreads';
	
		$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode($code);
	    
	    if ($alertType != null and $alertType->getEnabled()) {
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($GLOBALS['mybb']->user['uid'] != $thread['uid']) {
						
				$alert->setExtraDetails(array(
					'subject' => $thread['subject']
				));
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				
			}
		
		}
		
	}
}

// STICK THREADS
$plugins->add_hook('class_moderation_stick_threads', 'myalertsmore_addAlert_stick_thread');
function myalertsmore_addAlert_stick_threads(&$tids)
{
	foreach ($tids as $tid) {
	
		$thread = get_thread($tid);
	
		$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('stickthreads');
	    
	    if ($alertType != null and $alertType->getEnabled()) {
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($GLOBALS['mybb']->user['uid'] != $thread['uid']) {
						
				$alert->setExtraDetails(array(
					'subject' => $thread['subject']
				));
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				
			}
		
		}
		
	}
}

// UNSTICK THREADS
$plugins->add_hook('class_moderation_unstick_threads', 'myalertsmore_addAlert_unstick_thread');
function myalertsmore_addAlert_unstick_threads(&$tids)
{
	foreach ($tids as $tid) {
	
		$thread = get_thread($tid);
	
		$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('unstickthreads');
	    
	    if ($alertType != null and $alertType->getEnabled()) {
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($GLOBALS['mybb']->user['uid'] != $thread['uid']) {
						
				$alert->setExtraDetails(array(
					'subject' => $thread['subject']
				));
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				
			}
		
		}
		
	}
}

// EDIT A POST
$plugins->add_hook('datahandler_post_update', 'myalertsmore_addAlert_edit_post');
function myalertsmore_addAlert_edit_post(&$args)
{
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('editpost');

    if ($alertType != null and $alertType->getEnabled()) {
    
    	$post = get_post((int) $args->data['pid']);
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $post['uid'], $alertType, (int) $post['tid']);
		
		if ($GLOBALS['mybb']->user['uid'] != $post['uid']) {
					
			$alert->setExtraDetails(array(
				'pid' => $post['pid']
			));

			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
	
	}
}

// DELETE ANY KIND OF POST
$plugins->add_hook('class_moderation_delete_post_custom', 'myalertsmore_addAlert_delete_post');
function myalertsmore_addAlert_delete_post(&$args)
{
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('deletepost');

    if ($alertType != null and $alertType->getEnabled()) {
    
    	$post = $args['post'];
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $post['uid'], $alertType, (int) $post['tid']);
		
		if ($GLOBALS['mybb']->user['uid'] != $post['uid']) {
					
			$alert->setExtraDetails(array(
				'thread_subject' => $post['subject']
			));

			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
	
	}
}

// SUSPEND POSTING, MODERATE POSTING, SUSPEND SIGNATURE & OPPOSITES
$plugins->add_hook('modcp_do_editprofile_update', 'myalertsmore_addAlert_suspensions');
$plugins->add_hook('admin_user_users_edit_commit', 'myalertsmore_addAlert_suspensions');
function myalertsmore_addAlert_suspensions()
{
	global $mybb, $user, $extra_user_updates, $option, $sort_options;
	
	// Suspend posting...
	if (!empty($extra_user_updates['suspendposting'])) {
	
		$code = 'suspendposting';
		$extra_details = array(
			'expiry_date' => $extra_user_updates['suspensiontime']
		);
		
	}
	// ... moderate posting
	else if (!empty($extra_user_updates['moderateposts'])) {
	
		$code = 'moderateposting';
		$extra_details = array(
			'expiry_date' => $extra_user_updates['moderationtime']
		);
		
	}
	// must be a revoke of posting suspension...
	else if (!$mybb->input['suspendposting'] and !empty($user['suspendposting'])) {
		$code = 'suspendposting';
	}
	// must be a revoke of posting moderation...
	else if (!$mybb->input['moderateposting'] and !empty($user['moderateposts'])) {
		$code = 'moderateposting';
	}
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode($code);
	
    if ($alertType != null and $alertType->getEnabled()) {
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $user['uid'], $alertType, 0);
		
		if ($mybb->user['uid'] != $user['uid']) {
					
			if ($extra_details) {
				$alert->setExtraDetails($extra_details);
			}
			
			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
	
	}
	
	// Suspend signature
	if (!empty($extra_user_updates['suspendsignature'])) {
	
		$code = 'suspendsignature';
		$extra_details = array(
			'expiry_date' => $extra_user_updates['suspendsigtime']
		);
		
	}
	// Must be a revoke of signature suspension...
	else if (!$mybb->input['suspendsignature'] and !empty($user['suspendsignature'])) {
		$code = 'suspendsignature';
	}
	// Don't proceed any further
	else {
		return;
	}
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode($code);
	
    if ($alertType != null and $alertType->getEnabled()) {
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $user['uid'], $alertType, 0);
		
		if ($mybb->user['uid'] != $user['uid']) {
					
			if ($extra_details) {
				$alert->setExtraDetails($extra_details);
			}
			
			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
	
	}
}

// CHANGE USERNAME
$plugins->add_hook('datahandler_user_update_user', 'myalertsmore_addAlert_change_username');
function myalertsmore_addAlert_change_username(&$args)
{
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('changeusername');
	
    if ($alertType != null and $alertType->getEnabled()) {
    
    	$user = $args['this']->data;
		$old_user = $args['old_user'];
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $user['uid'], $alertType, 0);
		
		if (isset($user['username']) and $GLOBALS['mybb']->user['uid'] != $user['uid']) {
					
			$alert->setExtraDetails(array(
				'old_name' => $old_user['username'],
				'new_name' => $user['username']
			));

			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
	
	}
}

// CHANGE SIGNATURE
$plugins->add_hook('modcp_do_editprofile_update', 'myalertsmore_addAlert_change_signature');
$plugins->add_hook('admin_user_users_edit_commit', 'myalertsmore_addAlert_change_signature');
function myalertsmore_addAlert_change_signature()
{
	global $user;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('changesignature');
	
    if ($alertType != null and $alertType->getEnabled()) {
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $user['uid'], $alertType, 0);
		
		if ($GLOBALS['mybb']->input['signature'] != $user['signature'] and $GLOBALS['mybb']->user['uid'] != $user['uid']) {
			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
		}
	
	}
}
