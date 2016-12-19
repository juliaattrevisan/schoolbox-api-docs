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
            $baseResult = [
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
                        'allDay'     => false,
                        'resourceId' => $folder->getId().'-units',
                    ]);

                    // If this is a quiz or due work - it's a content area
                    if ($assessable instanceof Quiz || $assessable instanceof DueWork) {
                        $result->add([
                            'id'         => 'contentArea-'.$assessable->getId(),
                            'title'      => $assessable->getTitle(),
                            'editable'   => true,
                            'start'      => $assessable->getPublishAt()->getDatabase(),
                            'end'        => $assessable->getDueAt()->getDatabase(),
                            'allDay'     => false,
                            'resourceId' => $folder->getId().'-contentAreas',
                        ]);
                    }

                    // If this is a project - get it and its constituents
                    if ($assessable instanceof Project) {
                        $result->add([
                            'id'         => 'project-'.$assessable->getId(),
                            'title'      => $assessable->getTitle(),
                            'editable'   => true,
                            'start'      => $assessable->getPublishAt()->getDatabase(),
                            'end'        => $assessable->getDueAt()->getDatabase(),
                            'allDay'     => false,
                            'resourceId' => $folder->getId().'-projects',
                        ]);

                        foreach ($assessable->getAssessables() as $activity) {
                            $result->add([
                                'id'         => 'activity-'.$assessable->getId(),
                                'title'      => $activity->getTitle(),
                                'editable'   => true,
                                'start'      => $activity->getPublishAt()->getDatabase(),
                                'end'        => $activity->getDueAt()->getDatabase(),
                                'allDay'     => false,
                                'resourceId' => $folder->getId().'-activities',
                            ]);
                        }
                    }
                }

                return $result;
            })
            ->concat();

        $this->getResponse()->content->set($this->buildJsonOutput($resultSet));
    }
}
