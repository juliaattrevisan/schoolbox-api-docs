<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SimpleSubjectServer;

use Sabre\DAV;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkResponseDataSource;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Resource\Storage\Mapper as StorageDataSource;
use Schoolbox\User;
use Schoolbox\WebDav\NameGenerator;

/**
 * DAV server for a student's My Subjects screen.
 */
class Server extends DAV\Collection
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
     * A list of child nodes, cached whenever getChildren() is called.
     *
     * @var DAV\INode[]
     */
    private $childNodeCache = null;

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
        return $this->user->getSubjectCollection()->count() > 0 && $this->isThisDirectChildOfPath($path);
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
        if (isset($this->childNodeCache)) {
            return $this->childNodeCache;
        }

        // Build an entire list of children
        $rootNode = new DAV\SimpleCollection('');

        // Only due works due this term
        /** @var IAssessable[][]|Collection[] $groupedAssessments */
        $groupedAssessments = $this->assessmentDataSource->findAllByStudent(
            $this->user,
            $this->currentTerm,
            ['type'  => AssessmentType::TYPE_DUE_WORK,]
        )->groupByCallback(function(IAssessable $assessment) {
            return $assessment->getBaseContext()->getFolder()->getId();
        });

        // Name generator
        $nameGenerator = new NameGenerator();

        foreach ($groupedAssessments as $folderId => $assessments) {

            $assessment = $assessments->first();
            if ($assessment instanceof IAssessable) {
                // /Subject/
                try {
                    $folderNode = new DAV\SimpleCollection(
                        $nameGenerator->getUniqueChildName(
                            $rootNode,
                            $assessment->getBaseContext()->getFolder()->getTitle()
                        )
                    );
                } catch (DAV\Exception $e) {
                    // can do nothing
                    continue;
                }
            } else {
                continue;
            }

            foreach ($assessments as $assessment) {

                // /Subject/Assessment/
                try {
                    $assessmentNode = new Assessment(
                        $nameGenerator->getUniqueChildName($folderNode, $assessment->getTitle()),
                        $this->user,
                        $assessment,
                        $this->storageDataSource,
                        $this->dueWorkResponseDataSource
                    );
                } catch (DAV\Exception $e) {
                    // can do nothing
                    continue;
                }

                $participant = $assessment->getParticipantIfExtant($this->user);
                if ($participant instanceof Participant) {
                    foreach ($participant->getAllResponses() as $response) {

                        // /Subject/Assessment/Response/
                        try {
                            $responseNode = new DAV\SimpleCollection(
                                $nameGenerator->getUniqueChildName(
                                    $assessmentNode,
                                    sprintf('Submission %s', $response->getSubmittedAt()->getDatabase())
                                )
                            );
                        } catch (DAV\Exception $e) {
                            // can do nothing
                            continue;
                        }

                        if ($response instanceof DueWorkResponse && $response->isSubmitted()) {

                            foreach ($response->getAllFiles() as $file) {

                                // /Subject/Assessment/Response/File/
                                try {
                                    $responseNode->addChild(
                                        new ResponseFile(
                                            $nameGenerator->getUniqueChildName($responseNode, $file->getBasename()),
                                            $file
                                        )
                                    );
                                } catch (DAV\Exception $e) {
                                    // can do nothing
                                    continue;
                                }
                            }
                        }
                        $assessmentNode->addChild($responseNode);
                    }
                }

                $folderNode->addChild($assessmentNode);
            }
            $rootNode->addChild($folderNode);
        }

        $this->childNodeCache = $rootNode->getChildren();
        return $this->childNodeCache;
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
