<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * A DAV collection representing a single subject.
 */
class Subject extends DAV\Collection
{
    /**
     * @var Folder
     */
    private $folder;

    /**
     * @var User
     */
    private $user;

    /**
     * @var IAssessable[]
     */
    private $assessments;

    /**
     * Constructor
     *
     * @param Folder        $folder
     * @param User          $user
     * @param IAssessable[] $assessments
     */
    public function __construct(
        Folder $folder,
        User $user,
        $assessments
    ) {
        // Params:
        // User, folder, a list of open assessments within said folder
        $this->setFolder($folder);
        $this->setUser($user);
        $this->setAssessments($assessments);
    }

    /**
     * @param Folder $folder
     *
     * @return $this
     */
    private function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Folder
     */
    private function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param  User $user
     *
     * @return $this
     */
    private function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    private function getUser()
    {
        return $this->user;
    }

    /**
     * @return IAssessable[]
     */
    private function getAssessments()
    {
        return $this->assessments;
    }

    /**
     * @param IAssessable[] $assessments
     * @return static
     */
    private function setAssessments($assessments)
    {
        $this->assessments = $assessments;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getFolder()->getTitle();
    }

    /**
     * Loads children from the subject and user set in $this->setSubject().
     *
     * @return DAV\INode[]
     */
    public function getChildren()
    {
        $children = [];
        foreach ($this->getAssessments() as $assessment) {
            $children[] = new Assessment($this->getUser(), $assessment);
        }
        return $children;
    }
}
