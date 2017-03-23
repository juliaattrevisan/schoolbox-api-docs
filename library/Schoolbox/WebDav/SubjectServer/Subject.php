<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
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
    protected $folder;

    /**
     * @var User
     */
    protected $user;

    /**
     * Constructor
     *
     * @param Folder $folder
     * @param User   $user
     */
    public function __construct(
        Folder $folder,
        User $user
    ) {
        // Params:
        // User, folder, a list of open assessments within said folder
        $this->setFolder($folder);
        $this->setUser($user);
    }

    /**
     * @param Folder $folder
     *
     * @return $this
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param  User $user
     *
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
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
        return [];
//        $mapper        = new SubmissionBoxMapper();
//        $nameGenerator = new NameGenerator();
//        foreach ($mapper->getSubmittable($this->getUser(), $this->getCategory()) as $assessment) {
//            $child = new DAV_SubjectServer_Assessment(
//                $nameGenerator->getUniqueChildName(
//                    $this,
//                    $nameGenerator->sanitiseName($assessment->getName())
//                )
//            );
//            $child
//                ->setAssessment($assessment)
//                ->setUser($this->getUser())
//                ->loadChildren();
//            $this->addChild($child);
//        }
//        return $this;
    }
}
