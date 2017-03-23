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
        $this->folder      = $folder;
        $this->user        = $user;
        $this->assessments = $assessments;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->folder->getTitle();
    }

    /**
     * Loads children from the subject and user set in $this->setSubject().
     *
     * @return DAV\INode[]
     */
    public function getChildren()
    {
        $children = [];
        foreach ($this->assessments as $assessment) {
            $children[] = new Assessment($this->user, $assessment);
        }
        return $children;
    }
}
