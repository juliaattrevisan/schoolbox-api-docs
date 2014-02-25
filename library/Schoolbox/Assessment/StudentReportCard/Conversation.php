<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\StudentReportCard;


use Schoolbox\Collection;
use Schoolbox\Resource\Category;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * This pseudo-domain class encapsulates a conversation about a student in a category, providing access to participants
 * and such.
 *
 * Class Conversation
 * @package Schoolbox\Assessment\StudentReportCard
 */
class Conversation {

	/** @var User $student */
	protected $student;

	/** @var Category $category */
	protected $category;

	/** @var Collection[User] $participants */
	protected $participants;

	/** @var Collection[User] $recipients */
	protected $emailRecipients = null;

	/** @var Collection[Comment] $comments */
	protected $comments;

	/**
	 * Generate a conversation based on student and subject
	 *
	 * @param User $student
	 * @param Category $category
	 * @throws \InvalidArgumentException
	 */
	public function __construct($student, $category) {
		if(!($student instanceof User && $category instanceof Category)) {
			throw new \InvalidArgumentException("Invalid conversation");
		}

		/* Find the parents of this particular student
		 */
		$this->student = $student;
		$this->student->loadParents();

		/* A list of email recipients
		 * This could be moved to the User object itself, if preferred
		 */
		$this->setEmailRecipients($this->findEmailRecipients($student));

		/* FInd the teachers of this particular category
		 */
		$this->category = $category;
		$this->category->loadTeachers(true);

		/* Generate participants: the student, their parent(s), and the category teacher(s)
		 *
		 * Email recipients are not necessarily participants in the conversation,
		 * merely acting as observers of the dialog, so they are excluded from this
		 * list of participants
		 */
		$this->participants = new Collection();
		$this->participants->add($student);
		$this->participants
			->merge($student->getParents())
			->merge($category->getTeachers());

		// retrieve the comments in this conversation
		$this->comments = CommentMapper::getInstance()->findByStudentAndCategory($student->getId(), $category->getId());
	}

	/**
	 * Find the email recipients for $user who should be notified whenever
	 * a comment is made
	 *
	 * @param $user
	 * @return \Schoolbox\Collection
	 */
	private function findEmailRecipients($user) {
		$parentRoles = \Schoolbox\RoleMapper::getInstance()->findByColumn('group_type', Role::PARENT)->getColumnValues('id');
		$allRecipients = new Collection();

		$DEPRECATED_EXTERNAL_USER = new \External_User();
		$emailArray = $DEPRECATED_EXTERNAL_USER->getParentEmails(array($user->getExternalId()));

		if (is_array($emailArray) && count($emailArray)) {
			foreach($emailArray as $synergyId => $emailAddress) {
				$thisRecipient = new Collection();

				// if a synergy ID is available, and a valid user can be obtained from it, use that
				if ($synergyId) {
					$thisRecipient = \Schoolbox\UserMapper::getInstance()->findByColumnList(array(
						'synergy_id' => $synergyId,
						'group_id'   => $parentRoles
					));
				}

				/* This person has a Schoolbox account, so just use that
				 */
				if ($thisRecipient->count()) {
					$recipient = $thisRecipient->first();
				}
				/* Generate a dummy user object (which will still have a correct email address)
				 *
				 * This is used when someone is specified to receive emails, but doesn't
				 * actually have a Schoolbox account. This is actually possible.
				 */
				else {
					$recipient = new User();
					$recipient->setId(-1);
				}

				// override any email retrieved from schoolbox with the one from synergy
				$recipient->setAltEmail($emailAddress);

				/* Add him to the stack
				 */
				$allRecipients->add($recipient);
			}
		}

		return $allRecipients;
	}

	/**
	 * Returns whether or not the given user is a participant in this conversation
	 *
	 * @param User $user
	 * @return bool
	 */
	public function isParticipant($user) {
		$exists = $this->participants->getBy('id', $user->getId());

		if ($exists !== null) {
			return true;
		}

		return false;
	}

	/**
	 * Alias for isParticipant()
	 *
	 * @param User $user
	 * @return bool
	 */
	public function canComment($user) {
		return $this->isParticipant($user);
	}

	/**
	 * Returns the comments visible to the given user
	 *
	 * @param User $user
	 * @throws \InvalidArgumentException
	 * @return Collection[Comment]
	 */
	public function getVisibleComments($user) {

		$role = $user->getRole()->getType();

		if($role == Role::STAFF) {
			// all staff can see all comments
			return $this->comments;
		}

		if(!$this->isParticipant($user)) {
			throw new \InvalidArgumentException("Not a participant of this conversation.");
		}

		$visible = new Collection();

		if($role == Role::PARENT) {
			foreach($this->comments as $c) {
				/** @var Comment $c */
				if($c->isVisibleToParent()) {
					$visible->add($c);
				}
			}
			return $visible;
		}
		elseif($role == Role::STUDENT) {
			foreach($this->comments as $c) {
				/** @var Comment $c */
				if($c->isVisibleToStudent()) {
					$visible->add($c);
				}
			}
			return $visible;
		}
		else {
			throw new \InvalidArgumentException("Not a participant of this conversation.");
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set recipients
	 *
	 * @param \Schoolbox\Collection $emailRecipients
	 */
	private function setEmailRecipients($emailRecipients) {
		$this->emailRecipients = $emailRecipients;
	}

	/**
	 * Get recipients
	 *
	 * @return \Schoolbox\Collection
	 */
	private function getEmailRecipients() {
		return $this->emailRecipients;
	}
}