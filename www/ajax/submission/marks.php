<?php
/**
 * @author    Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

// disallow SitePage context
use Schoolbox\Assessment\Grades;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\UserMapper;

if(isset($this)) {
	error(19);
}

// get autoloader and trigger session
require_once('../../../include/Bootstrap.php');
new SitePage();

// no request method, this didn't come via apache
if(!isset($_SERVER['REQUEST_METHOD'])) {
	respond('Not a server request', '400 Bad Request');
}

switch($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		if(isset($_GET['year']) && isset($_GET['semester'])) {
			$output = getMarks((int)$_GET['user'], (int)$_GET['category'], (int)$_SESSION['id'], (int)$_GET['year'], (int)$_GET['semester']);
		}
		else {
			$output = getMarks((int)$_GET['user'], (int)$_GET['category'], (int)$_SESSION['id']);
		}

		respond($output, '200 OK', 'application/json');
		break;

	default:
		respond('Can only GET or POST marks.', '405 Method Not Allowed');
}

/**
 * Retrieve the marks for the specified user in the specified category
 *
 * @param int $userId
 * @param int $catId
 * @param int $sessionUserId
 * @param int $year
 * @param int $semester
 * @return string
 */
function getMarks($userId, $catId, $sessionUserId, $year = 0, $semester = 0) {

	try {
		// test permissions on sessionUser
		$sessionUser = UserMapper::getInstance()->findById($sessionUserId);
		if($sessionUser->getRole()->getType() != \Schoolbox\Role::STAFF) {
			respond('Access denied', '403 Forbidden');
			exit;
		}

		// get a Collection containing the one user, to match Grades method signature
		$students = UserMapper::getInstance()->findByColumn('id', $userId);

		if($students->count() != 1) {
			throw new MapperException("Invalid number of students found for ID.");
		}

		$category = CategoryMapper::getInstance()->findById($catId);

	}
	catch(MapperException $e) {
		respond($e->getMessage(), '400 Bad Request');
		exit;
	}

	$grades = Grades::getInstance()->getStudentGradesForSubject($students, $category, $year, $semester);

	return json_encode($grades);
}



function respond($body, $code = '200 OK', $contentType = 'text/plain') {
	// clear any extraneous output
	ob_clean();
	header("HTTP/1.1 $code");
	header("Content-Type: $contentType");
	die($body);
}