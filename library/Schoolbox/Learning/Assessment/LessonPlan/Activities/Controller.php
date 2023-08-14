<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan\Activities;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Course\Markbook\CourseMarkbook;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as Unit;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access as AccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param UnitSpecificationMapper    $unitSpecificationMapper
     * @param UnitImplementationMapper   $unitImplementationMapper
     * @param CourseImplementationMapper $courseImplementationMapper
     * @param LessonPlanMapper           $lessonPlanMapper
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param \Component_InstanceMapper  $componentInstanceMapper
     * @param AccessMapper               $accessMapper
     */
    public function __construct(
        private UnitSpecificationMapper $unitSpecificationMapper,
        private UnitImplementationMapper $unitImplementationMapper,
        private CourseImplementationMapper $courseImplementationMapper,
        private LessonPlanMapper $lessonPlanMapper,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private \Component_InstanceMapper $componentInstanceMapper,
        private AccessMapper $accessMapper,
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
        $componentInstance = $this->componentInstanceMapper->findByInstanceId($componentId);
        if ($componentInstance) {
            $instanceConfig = $componentInstance->getConfigArray();

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
        ->groupByCallback(function ($assessment) {
            return $assessment->getContext()->getId();
        });
        $courses = $this->courseImplementationMapper->findByFolderId($folder->getId());
        $accessibleFolderIds = [];
        foreach ($courses as $course) {
            $specFolderIds = [];
            $course->setUnits($this->unitImplementationMapper->findAllByCourseId($course->getId()));
            foreach ($course->getUnits() as $unitImpl) {
                if (isset($lessonPlans[$unitImpl->getId()])) {
                    $unitImpl->setAssessables($lessonPlans[$unitImpl->getId()]);
                }
            }
            // filter out units that contain no assessments
            $course->setUnits($course->getUnits()->filter(function ($unit) {
                return $unit->getAssessables()->count();
            }));
            $specFolderIds = $course->getUnits()
            // this is to sort units and needs to be placed right after getUnits()
            ->sortByCallback(
                static function (Unit $a, Unit $b) {
                    if ($a->getDueDate()->eq($b->getDueDate())) {
                        return strnatcasecmp($a->getTitle(), $b->getTitle());
                    }
                    return $b->getDueDate()->compare($a->getDueDate());
                }
            )
            ->filter(static function (\Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model $unit) {
                   return !$unit->getSpecification()->getFolder()->isDeleted();
            })
            ->map(static function (\Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model $unit) {
                   return $unit->getSpecification()->getFolder()->getId();
            })->getAll();
            // course spec folder id, if not deleted
            if (!$course->getSpecification()->getFolder()->isDeleted()) {
                $specFolderIds[] = $course->getSpecification()->getFolder()->getId();
            }
            // gather folder ids the current user can access
            $accessibleFolderIds = array_merge($accessibleFolderIds, array_filter(
                $specFolderIds,
                function (int $folderId) {
                    try {
                        return $this->accessMapper->getFolderAccess($this->getUser(), $folderId) >= UserAccess::ACCESS_READ;
                    } catch (MapperException $e) {
                    }
                    return false;
                }
            ));
        }

        // filter out courses that contain no assessments
        $courses = $courses->filter(function ($course) {
            return $course->getUnits()->count();
        })
        /**
         * Sort courses' based on the:
         * Course name
         * First Unit open date
         * Course import ID
         */
        ->sortByCallback(function (CourseImplementation $left, CourseImplementation $right) {
            // Course Import's folder name
            $folderNameCompare = strnatcasecmp($left->getFolder()->getTitle(), $right->getFolder()->getTitle());
            if (0 !== $folderNameCompare) {
                return $folderNameCompare;
            }

            // Course-imports first unit open date
            $firstLeftUnitImpl   = CourseMarkbook::getFirstUnitImpl($left);
            $firstRightUnitImpl  = CourseMarkbook::getFirstUnitImpl($right);
            $unitOpenDateCompare = $firstLeftUnitImpl->getOpenDate()->compare($firstRightUnitImpl->getOpenDate());
            if (0 !== $unitOpenDateCompare) {
                return $unitOpenDateCompare;
            }

            // Course import id
            return $left->getId() <=> $right->getId();
        }, true);

        $twigData = [
            'courses'               => $courses,
            'accessibleFolderIds'   => $accessibleFolderIds,
            'lessonPlans'           => $lessonPlans,
            'folder'                => $folder,
            'dateStart'             => $dateStart,
            'dateEnd'               => $dateEnd,
            'returnUrlName'         => Assessment\Redirect::RETURN_URL,
            'encodedReturnUrl'      => Assessment\Redirect::encode($this->routeGenerator->generate('lessonPlanActivities.getHomepage', ['folderId' => $folder->getId()])),
            'readOnly'              => $this->accessMapper->getFolderAccess($this->getUser(), $folder->getId()) < UserAccess::ACCESS_WRITE
        ];

        $this->response = $this->responseFromTemplate(
            '@lessonPlanActivities/viewAll.twig',
            $twigData
        );
    }
}
