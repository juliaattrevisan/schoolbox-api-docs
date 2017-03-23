<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkResponseDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Storage\Mapper as StorageDataSource;
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
     * @var StorageDataSource
     */
    private $storageDataSource;

    /**
     * @var DueWorkResponseDataSource
     */
    private $dueWorkResponseDataSource;

    /**
     * Constructor
     *
     * @param                           $name
     * @param User                      $user
     * @param AssessmentDataSource      $assessmentDataSource
     * @param StorageDataSource         $storageDataSource
     * @param DueWorkResponseDataSource $dueWorkResponseDataSource
     * @param Term                      $currentTerm
     */
    public function __construct(
        $name,
        User $user,
        AssessmentDataSource $assessmentDataSource,
        StorageDataSource $storageDataSource,
        DueWorkResponseDataSource $dueWorkResponseDataSource,
        Term $currentTerm
    ) {
        $this->name                      = $name;
        $this->user                      = $user;
        $this->assessmentDataSource      = $assessmentDataSource;
        $this->currentTerm               = $currentTerm;
        $this->storageDataSource         = $storageDataSource;
        $this->dueWorkResponseDataSource = $dueWorkResponseDataSource;
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
        /** @var IAssessable[][]|Collection[] $groupedAssessments */
        $groupedAssessments = $this->assessmentDataSource->findAllByStudent(
            $this->user,
            $this->currentTerm,
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
                    $this->user,
                    $assessments,
                    $this->storageDataSource,
                    $this->dueWorkResponseDataSource
                );
            }
        }
        return $children;
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
}
