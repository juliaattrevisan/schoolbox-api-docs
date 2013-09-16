<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Core\User;


use Schoolbox\Mobile\Generic;
use Response_HTTP;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\External\SubjectMapper;

class API extends Generic\API {

	/**
	 * Returns a user's profile
	 *
	 * @param int $userId
	 * @return Response_HTTP
	 */
	public function Profile($userId) {
		// locate the user in question
		$targetUser = UserMapper::getInstance()->findById($userId);

		if(!$targetUser) {
			return new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
		}

		// next, are we allowed to view their profile?

		// profiles are of students only
		if($targetUser->getRole()->getType() != Role::STUDENT) {
			return new Response_HTTP(Response_HTTP::HTTP_FORBIDDEN);
		}

		$sessionUser = UserMapper::getInstance()->findById($_SESSION['id']);
		$includeHrefs = false;

		switch($sessionUser->getRole()->getType()) {
			case Role::STAFF:
				// can view any profile, complete with homepage links
				$includeHrefs = true;
				break;
			case Role::PARENT:
				// can only view your own kids
				$gHandler = new \Group();
				if(!$gHandler->isParentOfChild($userId)) {
					return new Response_HTTP(Response_HTTP::HTTP_FORBIDDEN);
				}
				break;
			case Role::STUDENT:
			default:
				if($sessionUser->getId() != $targetUser->getId()) {
					return new Response_HTTP(Response_HTTP::HTTP_FORBIDDEN);
				}
				$includeHrefs = true;
				break;
		}

		$response = new Response_HTTP();

		// permission granted

		// first article: user photo and name
		$articles[] = array(
			'title'   => '',
			'aside'   => '',
			'class'   => 'profile-pic',
			'icon'    => '',
			'content' => array(
				'fullname' => $targetUser->formatName(),
				'photouri' => '/portrait.php?userId='. $targetUser->getExternalId() .'&size=profile'
			)
		);

		/** @var $sMap SubjectMapper
		 *  @var $cMap CategoryMapper
		 */
		$sMap = SubjectMapper::getInstance();
		$cMap = CategoryMapper::getInstance();

		$subjectCollection = $sMap->findAllForStudent($targetUser);
		$groupCollection = $cMap->findForUser($targetUser);

		$subjects = array();
		$groups = array();

		foreach($subjectCollection as $subject) {

			if(!$includeHrefs) {
				unset($subject['id']);
			}
			$subjects[] = $subject;
		}

		foreach($groupCollection as $group) {
			/** @var $group Category */
			$g['name'] = $group->getName();
			if($includeHrefs) {
				$g['id'] = $group->getId();
			}
			$groups[] = $g;
		}

		// second article: subjects
		$articles[] = array(
			'title'   => 'Subjects',
			'aside'   => '',
			'class'   => 'subject-list',
			'icon'    => '',
			'content' => array('subjectlist' => $subjects)
		);

		// third article: groups
		$articles[] = array(
			'title'   => 'Groups',
			'aside'   => '',
			'class'   => 'group-list',
			'icon'    => '',
			'content' => array('grouplist' => $groups)
		);

		$response->setPage('title', $targetUser->formatName());
		$response->setResponse('articles', $articles);
		return $response;
	}
}