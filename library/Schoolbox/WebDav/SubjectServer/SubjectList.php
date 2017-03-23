<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

/**
 * A DAV collection representing the list of subjects that the current user has.
 */
class SubjectList extends DAV\Collection
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var User
     */
    private $user;

    /**
     * @var AssessmentDataSource
     */
    private $assessmentDataSource;

    /**
     * @var Term
     */
    private $currentTerm;

    /**
     * Constructor
     *
     * @param                      $name
     * @param User                 $user
     * @param AssessmentDataSource $assessmentDataSource
     * @param Term                 $currentTerm
     */
    public function __construct(
        $name,
        User $user,
        AssessmentDataSource $assessmentDataSource,
        Term $currentTerm
    ) {
        $this->setName($name);
        $this->setUser($user);
        $this->setAssessmentDataSource($assessmentDataSource);
        $this->setCurrentTerm($currentTerm);
    }

    /**
     * Is the subject list shown for the given path?
     * The subject list will only be shown for the root path, usually.
     *
     * @param  string $path The path that is being displayed.
     *
     * @return bool
     */
    public function isSubjectListShown($path)
    {
        return /*!empty($_SESSION['subjects']) && */$this->isThisDirectChildOfPath($path);
    }

    /**
     * Is this subject list a direct descendant of the given path?
     *
     * @param $path
     *
     * @return bool
     */
    private function isThisDirectChildOfPath($path)
    {
        $this_path_parts = $this->getPathParts($this->getName());
        $path_parts      = $this->getPathParts($path);
        array_pop($this_path_parts);
        return $this_path_parts === $path_parts;
    }

    /**
     * Gets the parts of the given path, used by isThisDirectChildOfPath() to
     * determine whether this item is in another path.
     *
     * @param $path
     *
     * @return array
     */
    private function getPathParts($path)
    {
        return preg_split('/[\\\\\/]+/', $path, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Gets list of child nodes from the given user's subjects.
     *
     * @return DAV\INode[]
     */
    public function getChildren()
    {
        $children = [];

        // Only due works due this term
        $groupedAssessments = $this->getAssessmentDataSource()->findAllByStudent(
            $this->getUser(),
            $this->getCurrentTerm(),
            ['type'  => AssessmentType::TYPE_DUE_WORK,]
        )->groupByCallback(function(IAssessable $assessment) {
            return $assessment->getBaseContext()->getFolder()->getId();
        });

        foreach ($groupedAssessments as $folderId => $assessments) {
            // Have to assume that there's at least one assessment
            $assessment = $assessments->first();
            if ($assessment instanceof IAssessable) {
                $children[] = new Subject(
                    $assessment->getBaseContext()->getFolder(),
                    $this->getUser(),
                    $assessments
                );
            }
        }
        return $children;
    }

    /**
     * @param  \Schoolbox\User $user
     *
     * @return $this
     */
    private function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return \Schoolbox\User
     */
    private function getUser()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return AssessmentDataSource
     */
    private function getAssessmentDataSource()
    {
        return $this->assessmentDataSource;
    }

    /**
     * @param AssessmentDataSource $assessmentDataSource
     * @return static
     */
    private function setAssessmentDataSource($assessmentDataSource)
    {
        $this->assessmentDataSource = $assessmentDataSource;
        return $this;
    }

    /**
     * @return Term
     */
    private function getCurrentTerm()
    {
        return $this->currentTerm;
    }

    /**
     * @param Term $currentTerm
     * @return static
     */
    private function setCurrentTerm($currentTerm)
    {
        $this->currentTerm = $currentTerm;
        return $this;
    }
}
