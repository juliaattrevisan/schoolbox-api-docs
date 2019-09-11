<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\StudentReportCard;


use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
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
class Conversation
{
    /** @var Collection[User] $participants */
    protected $participants;

    /** @var Collection[Comment] $comments */
    protected $comments;

    /**
     * Generate a conversation based on student and subject
     *
     * @param IConfigLoader $config
     * @param User $student
     * @param FolderModel $subject
     * @throws \InvalidArgumentException
     */
    public function __construct(IConfigLoader $config, User $student, FolderModel $subject)
    {
        $this->participants = new Collection();

        if($config->get('grade_comments_enable')) {
            /* Find the parents of this particular student
             */
            $student->loadParents();

            /* Generate participants: the student, their parent(s), and the category teacher(s)
             *
             * Email recipients are not necessarily participants in the conversation,
             * merely acting as observers of the dialog, so they are excluded from this
             * list of participants
             */

            $this->participants = $this->participants->add($student)
                ->merge($student->getParents())
                ->merge($subject->getTeachers());
        }

        // retrieve the comments in this conversation
        $this->comments = CommentMapper::getInstance()->findByStudentAndCategory($student->getId(), $subject->getId());
    }

    /**
     * Returns whether or not the given user is a participant in this conversation
     *
     * @param User $user
     * @return bool
     */
    public function isParticipant($user)
    {
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
    public function canComment($user)
    {
        return $this->isParticipant($user);
    }

    /**
     * Returns the comments visible to the given user
     *
     * @param User $user
     * @return Collection[Comment]
     */
    public function getVisibleComments($user)
    {
        $role = $user->getRole()->getType();

        // All staff can see all comments

        if ($role == Role::STAFF) {
            return $this->comments;
        }

        $visible = new Collection();

        // The user has to be a participant to see any comments

        if ($this->isParticipant($user)) {
            if ($role == Role::PARENT) {
                /** @var Comment $c */
                foreach ($this->comments as $c) {
                    if ($c->isVisibleToParent()) {
                        $visible->add($c);
                    }
                }
            } elseif ($role == Role::STUDENT) {
                /** @var Comment $c */
                foreach ($this->comments as $c) {
                    if ($c->isVisibleToStudent()) {
                        $visible->add($c);
                    }
                }
            }
        }

        // Return whatever comments (if any) they were entitled to see

        return $visible;
    }
}
