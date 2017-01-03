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
use Schoolbox\Resource\Folder\Model as FolderModel;
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

	/** @var FolderModel $category */
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
	 * @param FolderModel $category
	 * @throws \InvalidArgumentException
	 */
	public function __construct($student, $category) {
		if(!($student instanceof User && $category instanceof FolderModel)) {
			throw new \InvalidArgumentException("Invalid conversation");
		}

		/* Find the parents of this particular student
		 */
		$this->student = $student;
		$this->student->loadParents();

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
        $this->participants = (new Collection())
            ->add($student)
            ->merge($student->getParents())
            ->merge($category->getTeachers());

		// retrieve the comments in this conversation
		$this->comments = CommentMapper::getInstance()->findByStudentAndCategory($student->getId(), $category->getId());
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
}