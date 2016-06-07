<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;


use Alaress_Date_Format as ADF;
use Eportfolio\ShowcaseMapper;
use Plagscan_Document;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\File\Mimetype;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Role;
use Schoolbox\User;

class File implements IFile
{
    use StorableTrait;

    /** @var string */
    protected $uuid;

    /** @var bool */
    protected $draft;

    /** @var Plagscan_Document */
    protected $plagscan;

    /** @var SubmissionBox */
    protected $submissionBox;

    /** @var bool */
    protected $marked;

    /** @var bool */
    protected $late;

    /** @var User */
    protected $owner;

    /** @var ADF */
    protected $createdAt;


    /**
     * Returns the full mime string for the file
     *
     * @return string
     */
    public function getMime()
    {
        return $this->getMimetype()->getMime();
    }


    /**
     * Returns the full mime string for the file
     *
     * @return Mimetype
     */
    public function getMimetype()
    {
        return $this->getStorage()->getMimetype();
    }


    /**
     * Returns the size on disk of the file
     *
     * @return int
     */
    public function getSize()
    {
        return $this->getStorage()->getSize();
    }


    /**
     * @return string
     */
    public function getHash()
    {
        return $this->getStorage()->getHash();
    }


    /**
     * @return ADF
     */
    public function getLastAccessed()
    {
        return $this->getStorage()->getLastAccessed();
    }


    /**
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }


    /**
     * @param string $uuid
     * @return static
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
        return $this;
    }

    /**
     * @param Plagscan_Document $plagscan
     */
    public function setPlagscan($plagscan)
    {
        $this->plagscan = $plagscan;
    }

    /**
     * @return Plagscan_Document
     */
    public function getPlagscan()
    {
        return $this->plagscan;
    }


    /**
     * @return boolean
     */
    public function isDraft()
    {
        return $this->draft;
    }


    /**
     * @deprecated only exists for mapper compatibility
     * @return boolean
     */
    public function getDraft()
    {
        return $this->isDraft();
    }


    /**
     * @param boolean $draft
     * @return static
     */
    public function setDraft($draft)
    {
        $this->draft = $draft;
        return $this;
    }


    /**
     * @return ADF
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }


    /**
     * @param ADF $createdAt
     * @return static
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }


    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }


    /**
     * @param User $owner
     * @return static
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }


    /**
     * @return SubmissionBox
     */
    public function getSubmissionBox()
    {
        return $this->submissionBox;
    }


    /**
     * @param SubmissionBox $submissionBox
     * @return static
     */
    public function setSubmissionBox($submissionBox)
    {
        $this->submissionBox = $submissionBox;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isLate()
    {
        return $this->late;
    }

    /**
     * @deprecated for mapper use only
     * @return bool
     */
    public function getLate()
    {
        return $this->isLate();
    }


    /**
     * @param boolean $late
     * @return static
     */
    public function setLate($late)
    {
        $this->late = $late;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isMarked()
    {
        return $this->marked;
    }


    /**
     * @deprecated for mapper use only
     * @return bool
     */
    public function getMarked()
    {
        return $this->isMarked();
    }


    /**
     * @param boolean $marked
     * @return static
     */
    public function setMarked($marked)
    {
        $this->marked = $marked;
        return $this;
    }

    /**
     * Does this user have permission to view this submission?
     * Users can view if:
     * * Is SuperUser
     * * Is Student and owns submission return
     * * Is Parent of student who owns submission return
     * * Is Staff and owns Submission Box
     * * Is Staff and teaches the Class containing the Submission Box
     * * Is Staff and has write access to Homepage containing Submission Box
     * * If this work is showcased, anyone may view it
     *
     * @param  User             $user
     * @param  UserAccessMapper $userAccessMapper
     * @param  ShowcaseMapper   $showcaseMapper
     * @return bool
     */
    public function hasPermission(User $user, UserAccessMapper $userAccessMapper, ShowcaseMapper $showcaseMapper)
    {
        if ($user->isSuperuser()) {
            return true;
        }

        // Owns the Submission Return
        if ($user->getId() == $this->getOwner()->getId()) {
            return true;
        }

        if ($user->getRole()->isStaff()) {

            // Owns the submission Box
            if((int)$this->getSubmissionBox()->getOwner()->getId() === $user->getId()){
                return true;
            }

            // Teaches the Class containing the Submission Box
            $teachers = $userAccessMapper->findUsersInCategory(
                $this->getSubmissionBox()->getCategory(),
                Role::STAFF,
                UserAccess::ACCESS_WRITE,
                true
            );
            foreach($teachers as $teacher){
                if($teacher->getId() == $user->getId()){
                    return true;
                }
            }

            // Individual user permissions
            if($userAccessMapper->userHasMinimumAccessToAll(
                $user->getId(),
                [$this->getSubmissionBox()->getCategory()->getId(),],
                UserAccess::ACCESS_WRITE
            )){
                return true;
            }

            // Role permissions
            $roleAccessMapper = RoleAccessMapper::getInstance();
            $roleAccess = $roleAccessMapper->getAccess($user, $this->getSubmissionBox()->getCategory()->getId());
            if($roleAccess->getType() >= UserAccess::ACCESS_WRITE){
                return true;
            }
        }

        // Parents can view their own children's submissions
        $this->getOwner()->loadParents();
        if ($this->getOwner()->isChildOf($user)) {
            return true;
        }

        // Check if file has been published/showcased in the ePortfolio, if so anyone can download the file
        return $showcaseMapper->countByColumnList([
            'uid'               => $this->getOwner()->getId(),
            'submission_box_id' => $this->getSubmissionBox()->getId(),
        ]) > 0;
    }
}
