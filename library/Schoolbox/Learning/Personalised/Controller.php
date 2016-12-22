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
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
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

        // Build the list
        $eventFactory = new Event\Factory($this->getRouter());
        $resultSet    = $this->assessmentDataSource->findAllByStudent($student, $term)
            ->map(function (IAssessable $assessable) use ($eventFactory) {
                return $eventFactory->buildFromAssessment($assessable);
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
     * Saves the participation for this assessment.
     *
     * POST vars:
     * * assessmentId:      The assessment to update for
     * * openDate, dueDate: The open/due date the student sets for themselves
     *
     * @param User $student
     */
    public function postUpdateParticipation(User $student)
    {
        $this->getResponse()->content->set(
            $this->buildJsonOutput(['result' => 'ok',])
        );
    }
}
