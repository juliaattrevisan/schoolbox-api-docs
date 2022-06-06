<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\MapperProxy as DueWorkResponseDataSource;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Storage\Mapper as StorageDataSource;
use Schoolbox\WebDav\HasUserSource;
use Schoolbox\WebDav\NameGenerator;

/**
 * Class Server
 *
 * DAV server for a student's My Subjects screen.
 *
 * @package Schoolbox\WebDav\SubjectServer
 */
class Server extends DAV\Collection
{
    use HasUserSource;


    /**
     * @var string
     */
    private $name;


    /**
     * @var AssessmentDataSource
     *
     * @phpstan-ignore-next-line - this IS read and written
     */
    private $assessmentDataSource;


    /**
     * @var StorageDataSource
     *
     * @phpstan-ignore-next-line - this IS read and written
     */
    private $storageDataSource;


    /**
     * @var DueWorkResponseDataSource
     *
     * @phpstan-ignore-next-line - this IS read and written
     */
    private $dueWorkResponseDataSource;


    /**
     * @var ErrorMapper
     *
     * @phpstan-ignore-next-line - this IS read and written
     */
    private $errorMapper;


    /**
     * A list of child nodes, cached whenever getChildren() is called.
     *
     * @var DAV\INode[]|null
     */
    private $childNodeCache = null;


    /**
     * A name generator, for sanitising and deduping names.
     *
     * @var NameGenerator
     *
     * @phpstan-ignore-next-line - this IS read and written
     */
    private $nameGenerator;


    /**
     * Constructor
     *
     * @param string                    $name
     * @param callable                  $userSource
     * @param AssessmentDataSource      $assessmentDataSource
     * @param StorageDataSource         $storageDataSource
     * @param DueWorkResponseDataSource $dueWorkResponseDataSource
     * @param ErrorMapper               $errorMapper
     * @param NameGenerator             $nameGenerator
     */
    public function __construct(
        $name,
        callable $userSource,
        AssessmentDataSource $assessmentDataSource,
        StorageDataSource $storageDataSource,
        DueWorkResponseDataSource $dueWorkResponseDataSource,
        ErrorMapper $errorMapper,
        NameGenerator $nameGenerator
    ) {
        $this->name                      = $name;
        $this->setUserSource($userSource);
        $this->assessmentDataSource      = $assessmentDataSource;
        $this->storageDataSource         = $storageDataSource;
        $this->dueWorkResponseDataSource = $dueWorkResponseDataSource;
        $this->errorMapper               = $errorMapper;
        $this->nameGenerator             = $nameGenerator;
    }


    /**
     * Is the subject list shown for the given path?
     * The subject list will only be shown for the root path, usually.
     *
     * @param string $path The path that is being displayed.
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function isSubjectListShown($path)
    {
        return $this->retrieveUserFromSource()->getSubjectCollection()->count() > 0 && $this->isThisDirectChildOfPath($path);
    }


    /**
     * Is this subject list a direct descendant of the given path?
     *
     * @param string $path
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
     * @param string $path
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
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    public function getChildren()
    {
        if (isset($this->childNodeCache)) {
            return $this->childNodeCache;
        }

        // Build an entire list of children
        $rootNode = new DAV\SimpleCollection('');

        $visibility = new Visibility(Access::getInstance());

        // Only due works due this term, and that accept submissions
        /** @var IAssessable[][]|Collection[] $groupedAssessments */
        $groupedAssessments = $this->assessmentDataSource
            ->findAllByStudentWithinWindow(
                $this->retrieveUserFromSource(),
                true,
                \Alaress_Date_Format::now()->startOfDay(),
                \Alaress_Date_Format::now()->endOfDay(),
                ['type' => AssessmentType::TYPE_DUE_WORK,]
            )
            ->filter(function (IAssessable $assessment) use ($visibility) {
                if (!$assessment instanceof DueWork || !($assessment->isAcceptMulti() || $assessment->isAcceptSingle())) {
                    // Not a due work? Or not accepting online submissions?
                    return false;
                }
                return $visibility->isVisibleToUser($assessment, $this->retrieveUserFromSource(), false);
            })
            ->groupByCallback(function (IAssessable $assessment) {
                return $assessment->getBaseContext()->getFolder()->getId();
            });

        foreach ($groupedAssessments as $folderId => $assessments) {
            $assessment = $assessments->first();
            if ($assessment instanceof IAssessable) {
                // /Subject/
                try {
                    $folderNode = new DAV\SimpleCollection(
                        $this->nameGenerator->getUniqueChildName(
                            $rootNode,
                            $this->nameGenerator->sanitiseName(
                                $assessment->getBaseContext()->getFolder()->getTitle()
                            )
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
                        $this->nameGenerator->getUniqueChildName(
                            $folderNode,
                            $this->nameGenerator->sanitiseName($assessment->getTitle())
                        ),
                        $this->retrieveUserFromSource(),
                        $assessment,
                        $this->storageDataSource,
                        $this->dueWorkResponseDataSource,
                        $this->errorMapper
                    );
                } catch (DAV\Exception $e) {
                    // can do nothing
                    continue;
                }

                $participant = $assessment->getParticipantIfExtant($this->retrieveUserFromSource());
                if ($participant instanceof Participant) {
                    foreach ($participant->getAllResponses() as $response) {
                        // /Subject/Assessment/Response/
                        try {
                            $responseNode = new DAV\SimpleCollection(
                                $this->nameGenerator->getUniqueChildName(
                                    $assessmentNode,
                                    $this->nameGenerator->sanitiseName(
                                        sprintf('Submission %s', $response->getSubmittedAt()->getDatabase())
                                    )
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
                                            $this->nameGenerator->getUniqueChildName(
                                                $responseNode,
                                                $this->nameGenerator->sanitiseName($file->getBasename())
                                            ),
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
