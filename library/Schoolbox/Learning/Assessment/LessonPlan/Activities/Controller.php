<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan\Activities;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param UnitSpecificationMapper  $unitSpecificationMapper
     * @param UnitImplementationMapper $unitImplementationMapper
     * @param LessonPlanMapper         $lessonPlanMapper
     * @param AssessmentMapperProxy    $assessmentMapperProxy
     */
    public function __construct(
        private UnitSpecificationMapper $unitSpecificationMapper,
        private UnitImplementationMapper $unitImplementationMapper,
        private LessonPlanMapper $lessonPlanMapper,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private \Component_InstanceMapper $componentInstanceMapper
    ) {
    }


    /**
     * Provide form
     *
     * @param CourseSpecification       $courseSpec
     * @param CourseImplementation|null $courseImpl
     */
    public function getCourse(CourseSpecification $courseSpec, ?CourseImplementation $courseImpl): void
    {
        /**
         * Assessments within the course grouped by units
         */
        $results = [];


        // if we have specified a course implementation... then we should just return assessments within that
        if ($courseImpl) {
            $courseImpl->setUnits($this->unitImplementationMapper->findAllByCourseId($courseImpl->getId()));

            foreach ($courseImpl->getUnits() as $unitImpl) {
                $unitImplId = $unitImpl->getId();
                $folderId = $unitImpl->getFolder()->getId();
                $options = ['order' => ['sequence' => 'ASC']];
                $assessments = $this->assessmentMapperProxy->findAllByUnitImplementationIds([$unitImplId], $options);
                $unitImpl->setAssessables($assessments);
                $results[$unitImplId] = $assessments;
            }
        } else {
            $courseSpec->setUnits($this->unitSpecificationMapper->findAllByCourseId($courseSpec->getId()));

            foreach ($courseSpec->getUnits() as $unitSpec) {
                $unitSpecId = $unitSpec->getId();
                $folderId = $unitSpec->getFolder()->getId();
                $options = ['order' => ['sequence' => 'ASC']];
                $assessments = $this->assessmentMapperProxy->findAllByUnitSpecificationId($unitSpecId, $options);
                $unitSpec->setAssessables($assessments);
                $results[$unitSpecId] = $assessments;
            }
        }

        $this->response = $this->responseAsJson($results);
    }

    /**
     * Get the list of
     * @param Folder\Model $folder
     */
    public function getHomepage(Folder\Model $folder): void
    {
        $this->getHomepageComponent($folder);
    }

    /**
     * @param Folder\Model $folder
     * @param int|null     $componentId
     * @return void
     */
    public function getHomepageComponent(Folder\Model $folder, ?int $componentId = null)
    {
        $dateStart = null;
        $dateEnd = null;
        // if there is a component specified grab the active date range
        if ($componentId) {
            $instanceConfig = $this->componentInstanceMapper->findByInstanceId($componentId)->getConfigArray();

            //if there is no source, update to the current homepage
            if ('' === trim($instanceConfig['componentname']['value'])) {
                $instanceConfig['componentname']['value'] = $instanceConfig['componentname']['defaultValue'];
            }
            $dateStart = \Alaress_Date_Format::now()->startOfDay()->addDays(-(int)$instanceConfig['range']['value']);
            $dateEnd = \Alaress_Date_Format::now()->endOfDay()->addDays((int)$instanceConfig['range']['value']);
        }

        /**
         * If user is a student, we have specific logic:
         * Students only see lesson plans that have been published
         * Students should only see lesson plans that have the visibility set for students to view
         */
        $isStudent = $this->getUser()->getRole()->isStudent();
        $publishDate = $isStudent ? \Alaress_Date_Format::now() : null;

        // grab the assessmentID for all lesson plans from the students subject list
        $assessmentIds = $this->lessonPlanMapper->findForFolder([$folder->getId()], null, null, $publishDate, $isStudent);
        // hydrate those assessments
        $lessonPlans = $this->assessmentMapperProxy->findAllByIds($assessmentIds, ['order'  => ['due_date' => 'DESC']])
        // Group by Unit
        ->groupByCallback(
            function ($lessonPlan) {
                return $lessonPlan->getContext()->getId();
            }
        )
        // Group now by Course
        ->groupByCallback(
            function ($unit) {
                return $unit->first()->getContext()->getSpecification()->getCourse()->getId();
            }
        );

        $twigData = [
            'lessonPlans'       => $lessonPlans,
            'folder'            => $folder,
            'dateStart'         => $dateStart,
            'dateEnd'           => $dateEnd,
            'returnUrlName'     => Assessment\Redirect::RETURN_URL,
            'encodedReturnUrl'  => Assessment\Redirect::encode($this->routeGenerator->generate('lessonPlanActivities.getHomepage', ['folderId' => $folder->getId()])),
            'readOnly'          => Access::getInstance()->getFolderAccess($this->getUser(), $folder->getId()) < UserAccess::ACCESS_WRITE
        ];

        $this->response = $this->responseFromTemplate(
            '@lessonPlanActivities/viewAll.twig',
            $twigData
        );
    }
}
