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

			$subject = $this->appendTeacherData($subject);

			if(!$includeHrefs) {
				unset($subject['id']);
			}
			$subjects[] = $subject;
		}

		foreach($groupCollection as $group) {
			/** @var $group Category */
			$g['name'] = $group->getName();
			$g['id'] = $group->getId();
			$g = $this->appendTeacherData($g);
			if($includeHrefs) {
				unset($g['id']);
			}
			$groups[] = $this->appendTeacherData($g);
		}

		foreach($subjects as $s) {
			$articles[] = array(
				'title'   => $s['name'],
				'aside'   => '',
				'class'   => 'subject',
				'icon'    => '',
				'content' => $s
			);
		}

		foreach ($groups as $g) {
			$articles[] = array(
				'title'   => $g['name'],
				'aside'   => '',
				'class'   => 'group',
				'icon'    => '',
				'content' => $g
			);
		}

		$response->setPage('title', $targetUser->formatName());
		$response->setResponse('articles', $articles);
		return $response;
	}

	/**
	 * Append teacher data to a given category and return it
	 *
	 * @param $category
	 * @return array
	 */
	protected function appendTeacherData($category) {
		$userHandler = new \User();
		$catId = $category['id'];
		$category['teachers'] = array();

		// attempt a synergy lookup only if a code exists
		if(array_key_exists('code', $category)) {
			$externalClassHandler = new \External_Class();
			$groupHandler = new \Group();
			$catCode = $category['code'];

			$listSyns = $externalClassHandler->getTeacherName($catCode);
			$staffGroups = $groupHandler->getStaffGroupTypeIds();

			if (is_array($listSyns)) {
				foreach($listSyns as $synid) {
					$teacher = $userHandler->getBySynergyAndGroup($synid, $staffGroups);
					if (is_array($teacher)) {
						$teacher['format_name'] = $userHandler->formatName($teacher);
						$category['teachers'][$teacher['uid']] = $teacher;
					}
				}
			}
		}

		// attempt an internal lookup if nothing was returned from external
		if(!count($category['teachers'])) {
			$managers = $userHandler->getManagers($catId);

			if(is_array($managers)) {
				foreach($managers as $teacher) {
					$teacher['format_name'] = $userHandler->formatName($teacher);
					$category['teachers'][$teacher['uid']] = $teacher;
				}
			}
		}

		// generate email links for all teachers, and clear unnecessary data
		$emailObj = new \Email();

		foreach($category['teachers'] as $k => $teacher) {
			$emailAddress = $emailObj->formatEmailFromArray($teacher);
			$category['teachers'][$k]['mailto'] = $_SESSION['mailhandle'] . $emailAddress;
			foreach(array('username','group_id','local_email','alt_email','campus','year','eportfolio_enabled','house','enabled') as $field) {
				unset($category['teachers'][$k][$field]);
			}
		}


		return $category;
	}
}