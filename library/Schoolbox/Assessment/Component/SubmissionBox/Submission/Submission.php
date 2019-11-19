<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;


use Eportfolio\ShowcaseMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * A student's submission to a due work.
 *
 * A submission may contain multiple files.
 *
 * TODO remove IStorage once all uses are found and removed
 */
class Submission implements IStorage, ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * The user who actually performed the create action for this submission
     *
     * @var User
     */
    private $submittedBy;

    /**
     * The user whom this submission was created for
     *
     * @var User
     */
    protected $owner;

    /** @var SubmissionBox */
    protected $submissionBox;

    /**
     * The list of files within this submission.
     *
     * @var Collection|SingleFile\Model[]
     */
    protected $files;

    /**
     * @var \Alaress_Date_Format
     */
    private $submittedAt;


    /**
     * @return boolean
     */
    public function isDraft()
    {
        return $this->submittedAt instanceof \Alaress_Date_NullDate;
    }


    /**
     * Will be late if not-submitted and current time is after due date, or was submitted after due date
     *
     * @return bool
     */
    public function isLate()
    {
        $now = \Alaress_Date_Format::now();
        $dueDate = $this->submissionBox->getDueDate();
        $isSubmitted = ! $this->isDraft();

        // Submitted after due date ?
        if($isSubmitted && $this->submittedAt->gt($dueDate)){
            return true;
        }

        // Or not submitted and now past due date
        return $now->gt($dueDate);
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
            $roleAccessLevel = Access::getInstance()->getFolderAccessForRole(
                $user->getRole()->getId(),
                $this->getSubmissionBox()->getCategory()->getId()
            );
            if ($roleAccessLevel >= UserAccess::ACCESS_WRITE) {
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

    /* Getters / Setters
     */

    /**
     * @return User
     */
    public function getSubmittedBy()
    {
        return $this->submittedBy;
    }


    /**
     * @param User $submittedBy
     * @return $this
     */
    public function setSubmittedBy(User $submittedBy)
    {
        $this->submittedBy = $submittedBy;
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
     * @return Collection|SingleFile\Model[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param Collection|SingleFile\Model[] $files
     * @return static
     */
    public function setFiles(Collection $files)
    {
        $this->files = $files;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getSubmittedAt()
    {
        return $this->submittedAt;
    }

    /**
     * @param \Alaress_Date_Format $submittedAt
     * @return static
     */
    public function setSubmittedAt(\Alaress_Date_Format $submittedAt = null)
    {
        $this->submittedAt = $submittedAt ?: new \Alaress_Date_NullDate();
        return $this;
    }



    // Deliberately broken implementation of IStorage.
    // Added only so that uses of this object which assume it's still an IStorage can be rewritten.

    public function getFilename()
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function setFilename($filename)
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function getExtension()
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function setExtension($extension)
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function getBasename()
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function setBasename($basename)
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function getMime()
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function getMimetype()
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function getSize()
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function getHash()
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function getLastAccessed()
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function exists()
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function read()
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }

    public function readStream()
    {
        throw new \BadMethodCallException(sprintf('%s needs to be rewritten to use multiple storage', __FUNCTION__));
    }
}
