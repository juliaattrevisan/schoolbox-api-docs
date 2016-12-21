<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Personalised;

use Alaress_Date_Format;
use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * Controller for the personalised learning screen.
 */
class Controller extends Base
{
    /**
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * @var AssessmentDataSource
     */
    protected $assessmentDataSource;

    /**
     * Constructor
     *
     * @param FolderMapper         $folderMapper
     * @param AssessmentDataSource $assessmentDataSource
     */
    public function __construct(FolderMapper $folderMapper, AssessmentDataSource $assessmentDataSource)
    {
        $this->folderMapper         = $folderMapper;
        $this->assessmentDataSource = $assessmentDataSource;
    }

    /**
     * Redirects to the personalised learning screen for the current user.
     *
     * @api GET /learning/personalised/
     */
    public function getPersonalised()
    {
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate(
                'learningPersonalised.getPersonalisedForStudent',
                [
                    'studentId' => $this->getUser()->getId(),
                ]
            )
        );
    }

    /**
     * Displays personalised learning screen for the given user.
     *
     * @api   GET /learning/personalised/:studentId/
     * @param User $student
     */
    public function getPersonalisedForStudent(User $student)
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningPersonalised/personalised.twig',
                [
                    'student' => $student,
                ],
                'Personalised Learning for '.$student->formatName(),
                'submission'
            )
        );
    }

    /**
     * Retrieves the resources to display in this student's calendar.
     *
     * TODO consider not AJAXing this
     *
     * @api   GET /learning/personalised/resources/:studentId/
     * @param User $student
     */
    public function getResourcesForStudent(User $student)
    {
        $resultSet = [];

        foreach ($this->folderMapper->findForUser($student) as $folder) {

            // Resource for folder
            $resultSet[] = [
                'id'          => $folder->getId(),
                'title'       => $folder->getTitle(),
                'folderTitle' => $folder->getTitle(),
            ];

            $baseResult = [
                'parentId'    => $folder->getId(),
                'folderId'    => $folder->getId(),
                'folderTitle' => $folder->getTitle(),
            ];

            // Resources for: units, content areas, projects, activities
            $resultSet[] = [
                'id'    => $folder->getId().'-units',
                'title' => 'Units',
            ] + $baseResult;
            $resultSet[] = [
                'id'    => $folder->getId().'-contentAreas',
                'title' => 'Content Areas',
            ] + $baseResult;
            $resultSet[] = [
                'id'    => $folder->getId().'-projects',
                'title' => 'Projects',
            ] + $baseResult;
            $resultSet[] = [
                'id'    => $folder->getId().'-activities',
                'title' => 'Activities',
            ] + $baseResult;
        }

        $this->getResponse()->content->set($this->buildJsonOutput($resultSet));
    }

    /**
     * Gets the resource calendar for this student (at this time).
     *
     * @param User $student
     */
    public function getCalendarForStudent(User $student)
    {
        // This will contain a from and to date.
        $term = (new Term())
            ->setStart(new Alaress_Date_Format($this->getRequest()->query->get('start'), Alaress_Date_Format::TIMESTAMP))
            ->setActualFinish(new Alaress_Date_Format($this->getRequest()->query->get('end'), Alaress_Date_Format::TIMESTAMP))
        ;

        // Use the calendar renderer classes here?
        $resultSet = $this->assessmentDataSource->findAllByStudent($student, $term)
            ->map(function(IAssessable $assessable) {
                $result = new Collection();

                // Process unit implementations only
                if ($assessable->getContext() instanceof UnitImplementation) {
                    /** @var UnitImplementation $context */
                    $context = $assessable->getContext();

                    // TODO fix UnitImplementation::getBaseContext
                    /** @var Folder $folder */
                    $folder  = $context->getCourse()->getFolder();

                    // The unit itself
                    $result->add([
                        'id'         => 'unit-'.$context->getId(),
                        'title'      => $context->getTitle(),
                        'editable'   => false,
                        'start'      => $context->getOpenDate()->getDatabase(),
                        'end'        => $context->getDueDate()->getDatabase(),
                        'allDay'     => true,
                        'resourceId' => $folder->getId().'-units',
                        'meta'       => [
                            'type'        => 'unit',
                            'description' => '',
                            'link'        => '/homepage/'.$context->getSpecification()->getFolder()->getId(),
                        ],
                    ]);


                    // If this is a quiz or due work - it's a content area
                    if ($assessable instanceof Quiz || $assessable instanceof DueWork) {
                        // A background event for the bounds
                        $result->add([
                            'id'         => 'contentArea-'.$assessable->getId().'-bounds',
                            'editable'   => false,
                            'start'      => $assessable->getPublishAt()->getDatabase(),
                            'end'        => $assessable->getDueAt()->getDatabase(),
                            'allDay'     => false,
                            'resourceId' => $folder->getId().'-contentAreas',
                            'rendering'  => 'background',
                            'meta'       => [],
                        ]);

                        // A foreground event for the actual quiz/duework
                        $result->add([
                            'id'         => 'contentArea-'.$assessable->getId(),
                            'title'      => $assessable->getTitle(),
                            'editable'   => true,
                            'start'      => $assessable->getPublishAt()->getDatabase(),
                            'end'        => $assessable->getDueAt()->getDatabase(),
                            'allDay'     => false,
                            'resourceId' => $folder->getId().'-contentAreas',
                            'constraint' => 'contentArea-'.$assessable->getId().'-bounds',
                            'meta'       => [
                                'type'        => $assessable->getFamilyName(),
                                'description' => \Scrub::html($assessable->getDescription()),
                                'link'        => $this->generateLink($assessable),
                            ],
                        ]);

                    } elseif ($assessable instanceof Project) {
                        // If this is a project - get it and its constituents
                        $result->add([
                            'id'         => 'project-'.$assessable->getId(),
                            'title'      => $assessable->getTitle(),
                            'editable'   => false,
                            'start'      => $assessable->getPublishAt()->getDatabase(),
                            'end'        => $assessable->getDueAt()->getDatabase(),
                            'allDay'     => false,
                            'resourceId' => $folder->getId().'-projects',
                            'meta'       => [
                                'type'        => $assessable->getFamilyName(),
                                'description' => \Scrub::html($assessable->getDescription()),
                                'link'        => $this->generateLink($assessable),
                            ],
                        ]);

                        foreach ($assessable->getAssessables() as $activity) {
                            // Background for the bounds
                            $result->add([
                                'id'         => 'activity-'.$activity->getId().'-bounds',
                                'editable'   => false,
                                'start'      => $activity->getPublishAt()->getDatabase(),
                                'end'        => $activity->getDueAt()->getDatabase(),
                                'allDay'     => false,
                                'resourceId' => $folder->getId().'-activities',
                                'rendering'  => 'background',
                                'meta'       => [],
                            ]);

                            // A foreground event for the actual activity
                            $result->add([
                                'id'         => 'activity-'.$activity->getId(),
                                'title'      => $activity->getTitle(),
                                'editable'   => true,
                                'start'      => $activity->getPublishAt()->getDatabase(),
                                'end'        => $activity->getDueAt()->getDatabase(),
                                'allDay'     => false,
                                'resourceId' => $folder->getId().'-activities',
                                'constraint' => 'activity-'.$activity->getId().'-bounds',
                                'meta'       => [
                                    'type'        => $activity->getFamilyName(),
                                    'description' => \Scrub::html($activity->getDescription()),
                                    'link'        => $this->generateLink($activity),
                                ],
                            ]);
                        }
                    }
                }

                return $result;
            })
            ->concat();

        // Filter out non-unique values
        $newResultSet = new Collection();
        foreach ($resultSet as $row) {
            if (!isset($newResultSet[$row['id']])) {
                $newResultSet[$row['id']] = $row;
            }
        }

        $this->getResponse()->content->set($this->buildJsonOutput($newResultSet->reindex()));
    }

    /**
     * Helper for generating a link to an assessable.
     *
     * @param IAssessable $assessable
     * @return string
     */
    protected function generateLink(IAssessable $assessable)
    {
        return $this->getRouter()->generate('learningAssessment.getSummary', ['assessment' => $assessable->getId()]);
    }
}
