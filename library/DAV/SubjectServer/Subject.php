<?php
/**
 * A DAV collection representing a single subject that a user has.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class DAV_SubjectServer_Subject extends Sabre\DAV\SimpleCollection
{
    /**
     * @var Folder
     */
    protected $category;

    /**
     * @var User
     */
    protected $user;

    /**
     * @param Folder $category
     *
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Folder
     */
    public function getCategory()
    {
        return $this->category;
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
     * Loads children from the subject and user set in $this->setSubject().
     *
     * @return $this
     */
    public function loadChildren()
    {
        $mapper        = new SubmissionBoxMapper();
        $nameGenerator = new DAV_NameGenerator();
        foreach ($mapper->getSubmittable($this->getUser(), $this->getCategory()) as $assessment) {
            $child = new DAV_SubjectServer_Assessment(
                $nameGenerator->getUniqueChildName(
                    $this,
                    $nameGenerator->sanitiseName($assessment->getName())
                )
            );
            $child
                ->setAssessment($assessment)
                ->setUser($this->getUser())
                ->loadChildren();
            $this->addChild($child);
        }
        return $this;
    }
}
