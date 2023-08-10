<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan\Activities;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private CourseSpecificationMapper $courseSpecificationMapper,
        private UnitSpecificationMapper $unitSpecificationMapper,
        private AssessmentMapperProxy $assessmentMapperProxy
    ) {
    }


    /**
     * Provide form
     *
     * @param CourseSpecification $course
     */
    public function getCourse(CourseSpecification $courseSpec): void
    {
        $courseSpec->setUnits($this->unitSpecificationMapper->findAllByCourseId($courseSpec->getId()));

        /**
         * Assessments within the course grouped by units
         */
        $results = [];

        foreach ($courseSpec->getUnits() as $unitSpec) {
            $unitSpecId = $unitSpec->getId();
            $folderId = $unitSpec->getFolder()->getId();
            $options = ['order' => ['sequence' => 'ASC']];
            $assessments = $this->assessmentMapperProxy->findAllByUnitSpecificationId($unitSpecId, $options);
            $unitSpec->setAssessables($assessments);
            $results[$unitSpecId] = $assessments;
        }

        $this->response = $this->responseAsJson($results);
    }

    /**
     * @param Model $lessonPlan
     * @return void
     */
    public function getLink(Model $lessonPlan): void
    {
        new \Alaress_Probe($lessonPlan);
    }

    /**
     * @param Model $lessonPlan
     * @return void
     */
    public function createLink(Model $lessonPlan): void
    {
        new \Alaress_Probe($lessonPlan);
    }

    /**
     * @param Model $lessonPlan
     * @return void
     */
    public function deleteLink(Model $lessonPlan): void
    {
        new \Alaress_Probe($lessonPlan);
    }

    /**
     *
     */
    public function getHomepage(Folder\Model $folder): void
    {
        $lessonPlanMapper = LessonPlanMapper::getInstance();
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        $publishDate = \Alaress_Date_Format::now();

        // grab the assessmentID for all lesson plans from the students subject list
        $assessmentIds = $lessonPlanMapper->findForFolder([$folder->getId()], null, null, $publishDate, $this->getUser()->getRole()->isStudent());
        // hydrate those assessments
        $lessonPlans = $assessmentDataSource->findAllByIds($assessmentIds, ['order'  => ['due_date' => 'DESC']])
        ->groupByCallback(
            function ($lessonPlan) {
                return $lessonPlan->getContext()->getId();
            }
        )
        ;
        // Schoolbox\Learning\Assessment\LessonPlan\Model

        $twigData = [
            'lessonPlans'       => $lessonPlans,
            'folder'            => $folder,
            'returnUrlName'     => Assessment\Redirect::RETURN_URL,
            'encodedReturnUrl'  => Assessment\Redirect::encode('/homepage/' . $folder->getId()),
            // 'readOnly'          => $this->getContentPermission() < UserAccess::ACCESS_WRITE;,
            'readOnly'          => false
        ];

        $this->response = $this->responseFromTemplate(
            '@lessonPlanActivities/viewAll.twig',
            $twigData
        );
    }
}
