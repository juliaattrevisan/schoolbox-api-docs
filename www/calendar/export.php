<?php
/**
 * Updated calendar exporter
 *
 * Now supports more types of export than just all events at once, and supports
 * export for events for a user, given a token.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
require_once dirname(__FILE__) . '/../include/iCalcreator.class.php';

use Schoolbox\User;
use Schoolbox\UserMapper;
try {
	$session     = new Session();
	$token       = $_GET['token'];
	$export_type = $_GET['export'];
	$export_id   = intval($_GET['id']);
	$api         = null;
	if (empty($token)) {
		throw new Exception_Schoolbox('No token provided', 401);
	}

	//
	// TODO use a proper token instead of username
	$user = Schoolbox\UserMapper::getInstance()->findOneByColumn('username', $token);
	if (!$user instanceof User) {
		throw new Exception_Schoolbox('Invalid token', 401);
	}
	else {
		//
		// Clobber current user's session for this request only.
		// Register a shutdown function so that user's session is restored.
		// (If PHP < 5.5 supported finally blocks, I'd use that.)
		$old_session = $_SESSION;
		$session->create($user->getUsername());
		register_shutdown_function(function() use ($old_session) {
			$_SESSION = $old_session;
		});
	}

	switch ($export_type) {
		case 'personal':
			$api = new Calendar_Api_EventType_Event_Schoolbox_Personal(array());
			break;

		case 'group':
			$api = new Calendar_Api_EventType_Event_Schoolbox_Group(array());
			if ($export_id > 0) {
				$api->setGroupId($export_id);
			}
			else {
				throw new Exception_Schoolbox('A group ID must be provided', 400);
			}
			break;

		case 'campus':
			$api = new Calendar_Api_EventType_Event_Schoolbox_Campus(array());
			if ($export_id > 0) {
				$api->setCampusId($export_id);
			}
			else {
				throw new Exception_Schoolbox('A campus ID must be provided', 400);
			}
			break;

		case 'work':
			$api = new Calendar_Api_EventType_Submission_Schoolbox_Default(array());
			break;

		case 'all':
			$factory = new Calendar_Api_Factory();
			$api     = $factory->produce();
			break;

		case '':
			throw new Exception_Schoolbox('An export type must be provided', 400);
			break;

		default:
			throw new Exception_Schoolbox('Invalid export type', 400);
			break;
	}

	//
	// API should always be a Calendar_Api_EventType_Interface object, no need
	// to check.
	$exporter = new Calendar_Event_Collection_Exporter(
		$api,
		$_SERVER['REQUEST_TIME'],
		strtotime('+1 year', $_SERVER['REQUEST_TIME']),
		new SitePage(false)
	);
	$exporter->renderAll();
}
catch (Exception_Schoolbox $e) {
	//
	// I wish I could use http_response_code() method here!
	$code = $e->getCode();
	$text = '';
	switch ($code) {
		case 400:
			$text = 'Bad Request';
			break;

		case 401:
			$text = 'Unauthorized';
			break;

		default:
			$code = 500;
			$text = 'Internal Server Error';
			break;
	}
	$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
	header($protocol . ' ' . $code . ' ' . $text);
	echo $e->getMessage();
}
