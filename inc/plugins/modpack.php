<?php
/**
 * Moderation Alerts Pack
 * 
 * Provides additional actions related to moderation for @euantor's MyAlerts plugin.
 *
 * @package Moderation Alerts Pack
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license (same as MyAlerts)
 * @version 4.0
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

// All the available alerts should be placed here	
$GLOBALS['alertslist'] = ["warn", "revokewarn", "deletethreads", "closethreads", "openthreads", "movethreads", "approvethreads", "unapprovethreads", "softdeletethreads", "restorethreads", "stickthreads", "unstickthreads", "mergethreads", "editpost", "deleteposts", "softdeleteposts", "restoreposts", "suspendposting", "moderateposting", "suspendsignature", "changeusername", "changesignature", "acceptbuddyrequest", "declinebuddyrequest", "removefrombuddylist"];

function modpack_info()
{
	$installed = false;
	if (function_exists('myalerts_is_installed')) {
		$installed = myalerts_is_installed();
	}
	
	$myalerts_notice = '';
	if (!$installed) {
		$myalerts_notice = '<br /><span style="color:#f00">MyAlerts 2.0.2 or higher is required for Moderation Alerts Pack</span>.';
	}


	$info = [
		'name'			=>	'Moderation Alerts Pack',
		'description'	=>	'Provides several more actions related to moderation for @euantor\'s <a href="http://community.mybb.com/thread-171301.html"><b>MyAlerts</b></a> plugin.' . $myalerts_notice,
		'website'		=>	'http://www.mybboost.com',
		'author'		=>	'Shade',
		'version'		=>	'4.0',
		'compatibility'	=>	'18*'
	];
	
	return $info;
}

function modpack_is_installed()
{
	global $cache;
	
	$info = modpack_info();
	$installed = $cache->read("shade_plugins");
	if ($installed[$info['name']]) {
		return true;
	}
}

function modpack_install()
{
	global $db, $PL, $lang, $mybb, $cache, $alertslist;
	
	if (!$lang->modpack) {
		$lang->load('modpack');
	}
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->modpack_error_plmissing, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	if ($PL->version < 9) {
		flash_message($lang->modpack_error_plnotuptodate, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	// MyAlerts installation check
	if (!$db->table_exists('alerts')) {
		flash_message($lang->modpack_error_myalertsnotinstalled, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	// Core edits routines
	if (modpack_fixmoderation() == false) $errors[] = $lang->modpack_error_moderation;
	if (modpack_fixuserhandler() == false) $errors[] = $lang->modpack_error_userhandler;
	
	if (!empty($errors)) {
	
		foreach($errors as $error) {
			$output.= "<li>".$error."</li>\n";
		}
		
		flash_message($lang->sprintf($lang->modpack_error_missingperms, $output), "error");
		admin_redirect("index.php?module=config-plugins");
		
	}
	
	$info = modpack_info();
	$shade_plugins = $cache->read('shade_plugins');
	$shade_plugins[$info['name']] = [
		'title'		=>	$info['name'],
		'version'	=>	$info['version']
	];
	$cache->update('shade_plugins', $shade_plugins);

	// Register our alerts
	$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);

	$alertTypesToAdd = [];
	
	foreach ($alertslist as $type) {
		
		$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode($type);
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypesToAdd[] = $alertType;
		
	}

	$alertTypeManager->addTypes($alertTypesToAdd);
	
}

function modpack_uninstall()
{
	global $db, $PL, $cache, $alertslist, $lang;
	
	if (!$lang->modpack) {
		$lang->load("modpack");
	}
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->modpack_error_plmissing, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	if ($PL->version < 9) {
		flash_message($lang->modpack_error_plnotuptodate, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	// Revert core edits we've done in installation process
	$PL->edit_core('modpack', 'inc/class_moderation.php', [], true);
	$PL->edit_core('modpack', 'inc/datahandlers/user.php', [], true);
	
	// Delete alerts
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
	$info = modpack_info();
	$shade_plugins = $cache->read('shade_plugins');
	unset($shade_plugins[$info['name']]);
	$cache->update('shade_plugins', $shade_plugins);
}

// Apply core patches
function modpack_fixmoderation()
{
	global $PL;
	
	$PL or require_once PLUGINLIBRARY;
	
	return $PL->edit_core('modpack', 'inc/class_moderation.php', [
		// Move multiple threads, inline moderation
		[
			'search' => '$arguments = array("tids" => $tids, "moveto" => $moveto);',
			'after' => '$arguments["newforum"] = &$newforum;'
		],
		// Delete post
		[
			'search' => 'SELECT p.pid, p.uid, p.fid, p.tid, p.visible, t.visible as threadvisible',
			'replace' => 'SELECT p.pid, p.uid, p.fid, p.tid, p.visible, t.visible as threadvisible, t.subject'
		],
		[
			'search' => '$plugins->run_hooks("class_moderation_delete_post", $post[\'pid\']);',
			'after' => '$args = array("post" => &$post);
$plugins->run_hooks("class_moderation_delete_post_custom", $args);'
		],
		// Soft delete posts
		[
			'search' => 'if($post[\'usepostcounts\'] != 0 && $post[\'threadvisible\'] == 1 && $post[\'visible\'] == 1)',
			'before' => '$map_users[$post[\'uid\']][$post[\'tid\']][\'counter\']++;
$map_users[$post[\'uid\']][$post[\'tid\']][\'subject\'] = $post[\'subject\'];'
		],
		[
			'search' => '$plugins->run_hooks("class_moderation_soft_delete_posts", $pids);',
			'after' => '$args = array("users" => $map_users);
$plugins->run_hooks("class_moderation_soft_delete_posts_custom", $args);'
		],
		[
			'search' => 'SELECT p.pid, p.tid, p.visible, f.fid, f.usepostcounts, p.uid, t.visible AS threadvisible',
			'replace' => 'SELECT p.pid, p.tid, p.visible, f.fid, f.usepostcounts, p.uid, t.visible AS threadvisible, t.subject'
		],
		// Restore posts
		[
			'search' => 'if($post[\'usepostcounts\'] != 0 && $post[\'threadvisible\'] == 1)',
			'before' => '$map_users[$post[\'uid\']][$post[\'tid\']][\'counter\']++;
$map_users[$post[\'uid\']][$post[\'tid\']][\'subject\'] = $post[\'subject\'];'
		],
		[
			'search' => '$plugins->run_hooks("class_moderation_restore_posts", $pids);',
			'after' => '$args = array("users" => $map_users);
$plugins->run_hooks("class_moderation_restore_posts_custom", $args);'
		],
		[
			'search' => 'SELECT p.pid, p.tid, f.fid, f.usepostcounts, p.uid, t.visible AS threadvisible',
			'replace' => 'SELECT p.pid, p.tid, f.fid, f.usepostcounts, p.uid, t.visible AS threadvisible, t.subject'
		],
	], true);
}

function modpack_fixuserhandler() {
	global $PL;
	$PL or require_once PLUGINLIBRARY;	
	return $PL->edit_core('modpack', 'inc/datahandlers/user.php', [
		// Change username
		[
			'search' => '$plugins->run_hooks("datahandler_user_update", $this);',
			'after' => '$args = array("this" => &$this, "old_user" => &$old_user);
$plugins->run_hooks("datahandler_user_update_user", $args);'
		]
	], true);
}

$plugins->add_hook('global_start', 'modpack_register_formatters');
function modpack_register_formatters()
{
	global $mybb, $lang;
	
	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
		
		if (!$lang->modpack) {
			$lang->load("modpack");
		}
		
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
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_UnapproveThreadsFormatter($mybb, $lang, 'unapprovethreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_ApproveThreadsFormatter($mybb, $lang, 'approvethreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_SoftDeleteThreadsFormatter($mybb, $lang, 'softdeletethreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_RestoreThreadsFormatter($mybb, $lang, 'restorethreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_StickThreadsFormatter($mybb, $lang, 'stickthreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_UnstickThreadsFormatter($mybb, $lang, 'unstickthreads'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_MergeThreadsFormatter($mybb, $lang, 'mergethreads'));
	    // Posts
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_EditPostFormatter($mybb, $lang, 'editpost'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_DeletePostFormatter($mybb, $lang, 'deleteposts'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_SoftDeletePostsFormatter($mybb, $lang, 'softdeleteposts'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_RestorePostsFormatter($mybb, $lang, 'restoreposts'));
	    // Users
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_ChangeUsernameFormatter($mybb, $lang, 'changeusername'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_SuspensionsFormatter($mybb, $lang, 'moderateposting'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_SuspensionsFormatter($mybb, $lang, 'suspendposting'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_SuspensionsFormatter($mybb, $lang, 'moderatesignature'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_SuspensionsFormatter($mybb, $lang, 'suspendsignature'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_ChangeSignatureFormatter($mybb, $lang, 'changesignature'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_AcceptBuddyRequestFormatter($mybb, $lang, 'acceptbuddyrequest'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_DeclineBuddyRequestFormatter($mybb, $lang, 'declinebuddyrequest'));
	    $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_RemoveFromBuddylistFormatter($mybb, $lang, 'removefrombuddylist'));
	}
}

// Generate the alerts
// INSERT/REVOKE A WARNING
$plugins->add_hook('datahandler_warnings_insert_warning', 'modpack_toggle_warn');
$plugins->add_hook('datahandler_warnings_update_warning', 'modpack_toggle_warn');
function modpack_toggle_warn(&$data)
{
	$warning = $data->data;
	
	$code = 'warn';
	if ($warning['reason']) {
		$code = 'revokewarn';
	}

    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode($code);
    
    if ($alertType != null and $alertType->getEnabled()) {
    
        $alert = new MybbStuff_MyAlerts_Entity_Alert((int) $warning['uid'], $alertType, 0);
        
        $extra_details = [
        	'points' => $warning['points']
        ];
        
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

// DELETE THREADS
$plugins->add_hook('class_moderation_delete_thread', 'modpack_delete_threads');
function modpack_delete_threads(&$tid)
{
	$thread = get_thread($tid);

    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('deletethreads');
    
    if ($alertType != null and $alertType->getEnabled() and $mybb->user['uid'] != $thread['uid']) {
    
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
		
		$alert->setExtraDetails([
			'subject' => $thread['subject']
		]);

        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
		
	}
}

// CLOSE THREADS
$plugins->add_hook('class_moderation_close_threads', 'modpack_close_threads');
function modpack_close_threads($tids)
{	
	global $mybb;
	
    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('closethreads');
    
    if ($alertType != null and $alertType->getEnabled()) {
	
		foreach ($tids as $tid) {
		
			$thread = get_thread($tid);
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($mybb->user['uid'] != $thread['uid']) {
			
				// We only want to notify the thread's author when the thread is opened and it's going to be closed
				if ($thread['closed'] != 1) {
						
					$alert->setExtraDetails([
						'subject' => $thread['subject']
					]);

					MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
					
				}
				
			}
			
		}
		
	}
}

// OPEN THREADS
$plugins->add_hook('class_moderation_open_threads', 'modpack_open_threads');
function modpack_open_threads($tids)
{
	global $mybb;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('openthreads');
    
    if ($alertType != null and $alertType->getEnabled()) {
	
		foreach ($tids as $tid) {
		
			$thread = get_thread($tid);
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($mybb->user['uid'] != $thread['uid']) {
			
				// We only want to notify the thread's author when the thread is closed and it's going to be opened
				if ($thread['closed'] == 1) {
						
					$alert->setExtraDetails([
						'subject' => $thread['subject']
					]);

					MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
					
				}
				
			}
			
		}
		
	}
}

// MOVE THREADS
$plugins->add_hook('class_moderation_move_simple', 'modpack_move_threads_simple');
$plugins->add_hook('class_moderation_move_thread_redirect', 'modpack_move_threads_simple');
$plugins->add_hook('class_moderation_copy_thread', 'modpack_move_threads_simple');
function modpack_move_threads_simple(&$args)
{
	global $mybb;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('movethreads');
    
    if ($alertType != null and $alertType->getEnabled()) {
	
		$forum = get_forum($args['new_fid']);
		$thread = get_thread($args['tid']);
		
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
		
		if ($mybb->user['uid'] != $thread['uid']) {
					
			$alert->setExtraDetails([
				'subject' => $thread['subject'],
				'destination_name' => $forum['name']
			]);

			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
	
	}
}

// MOVE THREADS (INLINE MODERATION)
$plugins->add_hook('class_moderation_move_threads', 'modpack_move_threads_inline');
function modpack_move_threads_inline(&$args)
{
	global $mybb;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('movethreads');
    
    if ($alertType != null and $alertType->getEnabled()) {
	
		$forum = get_forum($args['moveto']);
		
		foreach ($args['tids'] as $tid) {
		
			$thread = get_thread($tid);
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($mybb->user['uid'] != $thread['uid']) {
						
				$alert->setExtraDetails([
					'subject' => $thread['subject'],
					'destination_name' => $forum['name']
				]);
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				
			}
		
		}
	
	}
}

// SOFT DELETE THREADS
$plugins->add_hook('class_moderation_soft_delete_threads', 'modpack_soft_delete_threads');
function modpack_soft_delete_threads(&$tids)
{
	global $mybb;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('softdeletethreads');
    
    if ($alertType != null and $alertType->getEnabled()) {
		
		foreach ($tids as $tid) {
		
			$thread = get_thread($tid);
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, 0);
			
			if ($mybb->user['uid'] != $thread['uid']) {
						
				$alert->setExtraDetails([
					'subject' => $thread['subject']
				]);
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				
			}
		
		}
	
	}
}

// RESTORE THREADS
$plugins->add_hook('class_moderation_restore_threads', 'modpack_restore_threads');
function modpack_restore_threads(&$tids)
{
	global $mybb;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('restorethreads');
    
    if ($alertType != null and $alertType->getEnabled()) {
		
		foreach ($tids as $tid) {
		
			$thread = get_thread($tid);
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($mybb->user['uid'] != $thread['uid']) {
						
				$alert->setExtraDetails([
					'subject' => $thread['subject']
				]);
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				
			}
		
		}
	
	}
}

// APPROVE/UNAPPROVE THREADS
$plugins->add_hook('class_moderation_approve_threads', 'modpack_toggle_thread_status');
$plugins->add_hook('class_moderation_unapprove_threads', 'modpack_toggle_thread_status');
function modpack_toggle_thread_status(&$tids)
{
	global $mybb;
	
	foreach ($tids as $tid) {
	
		$thread = get_thread($tid);
	
		$code = (in_array($mybb->input['action'], ['approvethread', 'multiapprovethreads'])) ? 'approvethreads' : 'unapprovethreads';
	
		$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode($code);
	    
	    if ($alertType != null and $alertType->getEnabled()) {
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($mybb->user['uid'] != $thread['uid']) {
						
				$alert->setExtraDetails([
					'subject' => $thread['subject']
				]);
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				
			}
		
		}
		
	}
}

// STICK THREADS
$plugins->add_hook('class_moderation_stick_threads', 'modpack_stick_threads');
function modpack_stick_threads(&$tids)
{
	global $mybb;
	
	foreach ($tids as $tid) {
	
		$thread = get_thread($tid);
	
		$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('stickthreads');
	    
	    if ($alertType != null and $alertType->getEnabled()) {
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($mybb->user['uid'] != $thread['uid']) {
						
				$alert->setExtraDetails([
					'subject' => $thread['subject']
				]);
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				
			}
		
		}
		
	}
}

// UNSTICK THREADS
$plugins->add_hook('class_moderation_unstick_threads', 'modpack_unstick_threads');
function modpack_unstick_threads(&$tids)
{
	global $mybb;
	
	foreach ($tids as $tid) {
	
		$thread = get_thread($tid);
	
		$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('unstickthreads');
	    
	    if ($alertType != null and $alertType->getEnabled()) {
		
			$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $thread['uid'], $alertType, (int) $thread['tid']);
			
			if ($mybb->user['uid'] != $thread['uid']) {
						
				$alert->setExtraDetails([
					'subject' => $thread['subject']
				]);
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				
			}
		
		}
		
	}
}

// MERGE THREADS
$plugins->add_hook('class_moderation_merge_threads', 'modpack_merge_threads');
function modpack_merge_threads(&$args)
{
	global $mybb;
	
	$original_thread = get_thread($args['mergetid']);
	$new_thread = [
		'subject' => $args['subject'],
		'tid' => $args['tid']
	];
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('mergethreads');
    
    if ($alertType != null and $alertType->getEnabled()) {
	
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $original_thread['uid'], $alertType, (int) $new_thread['tid']);
		
		if ($mybb->user['uid'] != $original_thread['uid']) {
					
			$alert->setExtraDetails([
				'old_subject' => $original_thread['subject'],
				'new_subject' => $new_thread['subject']
			]);

			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
	
	}
}

// EDIT A POST
$plugins->add_hook('datahandler_post_update', 'modpack_edit_post');
function modpack_edit_post(&$args)
{
	global $mybb;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('editpost');

    if ($alertType != null and $alertType->getEnabled()) {
    
    	$post = get_post((int) $args->data['pid']);
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $post['uid'], $alertType, (int) $post['tid']);
		
		if ($mybb->user['uid'] != $post['uid']) {
					
			$alert->setExtraDetails([
				'pid' => $post['pid']
			]);

			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
	
	}
}

// DELETE ANY KIND OF POST
$plugins->add_hook('class_moderation_delete_post_custom', 'modpack_delete_post');
function modpack_delete_post(&$args)
{
	global $mybb;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('deleteposts');

    if ($alertType != null and $alertType->getEnabled()) {
    
    	$post = $args['post'];
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $post['uid'], $alertType, (int) $post['tid']);
		
		if ($mybb->user['uid'] != $post['uid']) {
					
			$alert->setExtraDetails([
				'thread_subject' => $post['subject']
			]);

			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
	
	}
}

// SOFT DELETE POSTS
$plugins->add_hook('class_moderation_soft_delete_posts_custom', 'modpack_soft_delete_posts');
function modpack_soft_delete_posts(&$args)
{
	global $mybb;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('softdeleteposts');

    if ($alertType != null and $alertType->getEnabled()) {
    
    	$users = $args['users'];
    	
    	foreach ($users as $uid => $threads) {
	    	
	    	if ($mybb->user['uid'] == $uid) {
		    	continue;
	    	}
	    	
	    	foreach ($threads as $tid => $thread) {
		    	
				$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $uid, $alertType, (int) $tid);
					
				$alert->setExtraDetails([
					'subject' => $thread['subject'],
					'counter' => $thread['counter']
				]);
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
		    	
	    	}
	    		
    	}
	
	}
}

// RESTORE POSTS
$plugins->add_hook('class_moderation_restore_posts_custom', 'modpack_restore_posts');
function modpack_restore_posts(&$args)
{
	global $mybb;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('restoreposts');

    if ($alertType != null and $alertType->getEnabled()) {
    
    	$users = $args['users'];
    	
    	foreach ($users as $uid => $threads) {
	    	
	    	if ($mybb->user['uid'] == $uid) {
		    	continue;
	    	}
	    	
	    	foreach ($threads as $tid => $thread) {
		    	
				$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $uid, $alertType, (int) $tid);
					
				$alert->setExtraDetails([
					'subject' => $thread['subject'],
					'counter' => $thread['counter']
				]);
	
				MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
		    	
	    	}
	    		
    	}
	
	}
}

// SUSPEND POSTING, MODERATE POSTING, SUSPEND SIGNATURE & OPPOSITES
$plugins->add_hook('modcp_do_editprofile_update', 'modpack_suspensions');
$plugins->add_hook('admin_user_users_edit_commit', 'modpack_suspensions');
function modpack_suspensions()
{
	global $mybb, $user, $extra_user_updates, $option, $sort_options;
	
	// Suspend posting...
	if (!empty($extra_user_updates['suspendposting'])) {
	
		$code = 'suspendposting';
		$extra_details = [
			'expiry_date' => $extra_user_updates['suspensiontime']
		];
		
	}
	// ... moderate posting
	else if (!empty($extra_user_updates['moderateposts'])) {
	
		$code = 'moderateposting';
		$extra_details = [
			'expiry_date' => $extra_user_updates['moderationtime']
		];
		
	}
	// Must be a revoke of posting suspension...
	else if (!$mybb->input['suspendposting'] and !empty($user['suspendposting'])) {
		$code = 'suspendposting';
	}
	// Must be a revoke of posting moderation...
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
		$extra_details = [
			'expiry_date' => $extra_user_updates['suspendsigtime']
		];
		
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
$plugins->add_hook('datahandler_user_update_user', 'modpack_change_username');
function modpack_change_username(&$args)
{
	global $mybb;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('changeusername');
	
    if ($alertType != null and $alertType->getEnabled()) {
    
    	$user = $args['this']->data;
		$old_user = $args['old_user'];
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $user['uid'], $alertType, 0);
		
		if (isset($user['username']) and $mybb->user['uid'] != $user['uid']) {
					
			$alert->setExtraDetails([
				'old_name' => $old_user['username'],
				'new_name' => $user['username']
			]);

			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
			
		}
	
	}
}

// CHANGE SIGNATURE
$plugins->add_hook('modcp_do_editprofile_update', 'modpack_change_signature');
$plugins->add_hook('admin_user_users_edit_commit', 'modpack_change_signature');
function modpack_change_signature()
{
	global $mybb, $user;
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('changesignature');
	
    if ($alertType != null and $alertType->getEnabled()) {
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $user['uid'], $alertType, 0);
		
		if ($mybb->input['signature'] != $user['signature'] and $mybb->user['uid'] != $user['uid']) {
			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
		}
	
	}
}

// ACCEPT OR DECLINE BUDDY REQUESTS
$plugins->add_hook('usercp_acceptrequest_end', 'modpack_handle_buddy_requests');
$plugins->add_hook('usercp_declinerequest_end', 'modpack_handle_buddy_requests');
function modpack_handle_buddy_requests()
{
	global $mybb, $user;
	
	if (empty($user)) {
		return false;
	}
	
	$code = ($mybb->get_input('action') == 'acceptrequest') ? 'acceptbuddyrequest' : 'declinebuddyrequest';
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode($code);
	
    if ($alertType != null and $alertType->getEnabled()) {
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $user['uid'], $alertType, 0);
		
		if ($mybb->user['uid'] != $user['uid']) {
			MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
		}
	
	}
}

// REMOVE FROM BUDDYLIST
$plugins->add_hook('usercp_do_editlists_end', 'modpack_remove_from_buddylist');
function modpack_remove_from_buddylist()
{
	global $mybb;
	
	if (!$mybb->get_input('delete', MyBB::INPUT_INT)) {
		return false;
	}
	
	$user = get_user($mybb->get_input('delete', MyBB::INPUT_INT));
	
	if (empty($user) or $mybb->get_input('manage') == "ignored") {
		return false;
	}
	
	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('removefrombuddylist');
	
    if ($alertType != null and $alertType->getEnabled()) {
    			
		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $user['uid'], $alertType, 0);
		
		MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
	
	}
}
