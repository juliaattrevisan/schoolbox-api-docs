<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Alaress\Web\Controller as Base;
use Alaress\Web\Request\Values;
use Aura\Router\Exception\RouteNotFound;
use Component_Definition;
use Component_Instance;
use Component_InstanceMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Component\Homepage\Outline\Controller as HomepageOutlineComponentController;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LessonPlan;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;

class Controller extends Base\Base
{
    const IMPORT_STAGE_COURSE_AND_CLASSES = 1;
    const IMPORT_STAGE_UNITS = 2;
    const IMPORT_STAGE_ASSESSMENTS = 3;

    /** @var CourseSpecification\Mapper */
    private $courseSpecificationMapper;

    /** @var CourseImplementation\Mapper */
    private $courseImplementationMapper;

    /** @var UnitSpecification\Mapper */
    private $unitSpecificationMapper;

    /** @var UnitImplementation\Mapper */
    private $unitImplementationMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var Access */
    private $access;

    /** @var Assessment\MapperProxy */
    private $assessmentMapperProxy;

    /** @var Component_InstanceMapper */
    private $componentInstanceMapper;

    /**
     * @var ImportFactory
     */
    private $importFactory;


    public function __construct(
        UnitSpecification\Mapper $unitSpecificationMapper,
        UnitImplementation\Mapper $unitImplementationMapper,
        Folder\Mapper $folderMapper,
        CourseSpecification\Mapper $courseSpecificationMapper,
        CourseImplementation\Mapper $courseImplementationMapper,
        Component_InstanceMapper $componentInstanceMapper,
        Access $access,
        ImportFactory $importFactory,
        Assessment\MapperProxy $assessmentMapperProxy,
        private \Schoolbox\Learning\Assessment\Project\ResultCache\Mapper $projectResultCacheMapper,
        private LessonPlan\Activities\Replicator $lessonPlanActivitiesReplicator,
    ) {
        $this->unitSpecificationMapper         = $unitSpecificationMapper;
        $this->unitImplementationMapper        = $unitImplementationMapper;
        $this->folderMapper                    = $folderMapper;
        $this->courseSpecificationMapper       = $courseSpecificationMapper;
        $this->courseImplementationMapper      = $courseImplementationMapper;
        $this->componentInstanceMapper         = $componentInstanceMapper;
        $this->access                          = $access;
        $this->importFactory                   = $importFactory;
        $this->assessmentMapperProxy           = $assessmentMapperProxy;
    }


    /**
     * This GET is the start of the import process. Import a Course into a set of Folders (classes)
     *
     * If both a CourseSpec + Folder are specified, populate stages 1 + 2 and jump straight to stage 2.
     *
     * @param null|CourseSpecification\Model $courseSpec  Null if a '0' in URL.
     * @param null|Folder\Model              $targetClass Null if a '0' in URL.
     * @param null|Component_Instance        $component   Null if not specified in URL
     * @throws MapperException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getImport(
        ?CourseSpecification\Model $courseSpec,
        ?Folder\Model $targetClass,
        ?Component_Instance $component
    ): void {
        // If the URL specified both a CourseSpec or Import destination (ie a Folder), then skip to step 2
        $initialStage = $courseSpec && $targetClass ? self::IMPORT_STAGE_UNITS : self::IMPORT_STAGE_COURSE_AND_CLASSES;

        /* Find out if $courseSpecId already exists in one or more of the target folder ids
         * $exists is truthy in this circumstance
         */
        $existingInFolderIds = (new ExistingImports($this->getDi()->get('databaseInternal')))->generate($courseSpec->getId());
        $exists = (bool)\count(array_intersect($existingInFolderIds, $targetClass ? [$targetClass->getId()] : []));

        $data = [
            'action'           => 'import',
            'initialStage'     => $initialStage,
            'courseSpec'       => $courseSpec,
            'destinations'     => $targetClass ?
                [
                    'id'   => $targetClass->getId(),
                    'name' => $targetClass->getTitle(),
                ] : [],
            'componentId'      => $component ? $component->getId() : null,
            'returnUrlName'    => Redirect::RETURN_URL,
            'encodedReturnUrl' => $this->requestQuery->get(Redirect::RETURN_URL),

            /* Go to the redirect destination if you've already imported this course
             * and you chose to cancel what you were doing
             */
            'importedAlready' => $exists,
            'importedAlreadyRedirect' => Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL)),
        ];

        if (self::IMPORT_STAGE_UNITS === $initialStage) {
            // If jumping straight to step2, generate a list of units for the specified course
            $data = array_merge($data, [
                'courseSpecId'     => $courseSpec->getId(),
                'folderIds'        => [(int)$targetClass->getId()],
                'unitsImpls'       => $this->findUnitImplementationsForCourseSpecId($courseSpec->getId()),
                'canReadUnitSpecs' => $this->canReadUnitSpecs($courseSpec),
            ]);
        }

        $this->response = $this->responseDependentOnRequest('@course/import.twig', $data);
    }


    /**
     * This POST is the result of import stage 1.
     *
     * Receives the CourseSpec, Component, + target Classes to import into.
     * Returns a HTML fragment of UnitSpecs belonging to the specified CourseSpec.
     *
     * @api   POST /course/import/classes
     * @since 160
     * @var int      $_POST [course_spec_id]    The course to import
     * @var string   $_POST ['import_folders']  CSV folder ids to import to
     * @var null|int $_POST ['component_id']    The component to import into
     * @throws Base\Exception
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function postClasses()
    {
        $courseSpecId    = trim($this->requestPost['course_spec_id']);
        $targetFolderIds = $this->parseFolderIds($this->requestPost['import_folders']);
        $componentId     = trim($this->requestPost['component_id']);

        try {
            $courseSpec = $this->courseSpecificationMapper->findById($courseSpecId);
        } catch (MapperException $e) {
            // the nominated "course" is not actually a course...
            $this->sendError('The selected source is not a course');
            return;
        }

        if (empty($targetFolderIds) && empty($courseSpecId)) {
            // They didn't select anything
            $this->sendError('You must select at least one class and a course to import before you can proceed');
            return;
        }

        if (empty($targetFolderIds)) {
            // They didn't select a class code
            $this->sendError('You must select at least one class before you can proceed');
            return;
        }

        if (empty($courseSpecId)) {
            // They didn't select a course to import
            $this->sendError('You must select a course to import before you can proceed');
            return;
        }

        /* Find out if $courseSpecId already exists in one or more of the target folder ids
         * $exists is truthy in this circumstance
         */
        $existingInFolderIds = (new ExistingImports($this->getDi()->get('databaseInternal')))->generate($courseSpecId);
        $exists = (bool)\count(array_intersect($existingInFolderIds, $targetFolderIds));

        // respond with stage 2: units
        $this->response = $this->responseDependentOnRequest(
            '@course/unitImportForm.twig',
            [
                'action'           => 'import',
                'courseSpecId'     => $courseSpecId,
                'folderIds'        => $targetFolderIds,
                'componentId'      => $componentId,
                'unitsImpls'       => $this->findUnitImplementationsForCourseSpecId($courseSpecId),
                'canReadUnitSpecs' => $this->canReadUnitSpecs($courseSpec),

                /* Go to the redirect destination if you've already imported this course
                 * and you chose to cancel what you were doing
                 */
                'importedAlready' => $exists,
                'importedAlreadyRedirect' => $this->getRouteIfAuthorized('course.courseList'),
            ]
        );
    }


    /**
     * This POST is the result of import stage 3.
     *
     * Returns a HTML fragment containing a list of Assessments, grouped by UnitImpl
     *
     * @api   POST /course/import/units
     * @since 160
     *
     * @var string   $_POST ['encodedReturnUrl']         Optional. Return URL set by caller.
     * @var string   $_POST ['action']                   {'import'|'modify'}
     * @var int      $_POST ['course_spec_id']
     * @var int      $_POST ['course_impl_id']
     * @var int      $_POST ['component_id']
     * @var string   $_POST ['import_folders']           Comma-separated list of folderId:userId, eg '176:151,43:151'
     * @var array    $_POST ['unit']
     * @var int      $_POST ['unit'][]['unit_spec_id']
     * @var int|null $_POST ['unit'][]['unit_impl_id']
     * @var string   $_POST ['unit'][]['open_date']
     * @var string   $_POST ['unit'][]['close_date']
     *
     * @throws Base\Exception
     * @throws MapperException
     */
    public function postUnits()
    {
        $post = $this->requestPost;

        $action           = trim($post['action']);
        $courseSpecId     = trim($post['course_spec_id']);
        $targetFolderIds  = $this->parseFolderIds($this->requestPost['import_folders']);
        $courseImplId     = trim($post['course_impl_id']);
        $componentId      = trim($post['component_id']);
        $units            = $post['unit'] ?? [];

        if (empty($courseSpecId)) {
            // The course homepage ID has gone missing
            $this->sendError('No course was specified');
            return;
        }

        if (!count($targetFolderIds)) {
            // The homepage IDs have gone missing
            $this->sendError('No class code was specified');
            return;
        }

        // Test permissions. Must have write permission on the destination page and read permission
        // on the course we are trying to import.

        // Test course page read access
        try {
            $courseSpec = $this->courseSpecificationMapper->findById($courseSpecId);
        } catch (MapperException\Deleted $e) {
            $courseSpec = $e->getDeletedObject();
        }

        $canReadCourseSpec = $this->canReadCourseSpec($courseSpec);
        // If modifying an already imported course,
        // and I can't read the original copy:
        // I can still modify the import, but can't add anything new to it.
        if (!($canReadCourseSpec || $courseImplId)) {
            $this->sendError('You do not have permission to import this course');
            return;
        }

        // Test each homepage for write access
        foreach ($targetFolderIds as $folderId) {
            if ($this->access->getFolderAccess($this->getUser(), $folderId) < UserAccess::ACCESS_WRITE) {
                $this->sendError('You do not have permission to import into this class');
                return;
            }
        }

        // Hydrate + Validate dates
        $canReadUnitSpecs = $this->canReadUnitSpecs($courseSpec);
        $defaultDueTime   = $this->getConfigLoader()->get('lms_duetime_default');
        // Map of unitSpecId -> close date with default due time
        // This is used on the UI to reset changed due date to unit default
        $unitDefaultDueDates = new Collection();

        try {
            $countImportedUnits = 0;

            foreach ($units as $idx => $unit) {
                if (isset($unit['import'])) {
                    $countImportedUnits++;
                    $unitSpecId = $unit['unit_spec_id'];

                    $openDate  = \Alaress_Date_Format::newLocalShort($unit['open_date']);
                    $closeDate = \Alaress_Date_Format::newLocalShort($unit['close_date']);
                    $dueDate = clone $closeDate;
                    $dueDate->endOfDay();
                    $unitDefaultDueDates->offsetSet($unitSpecId, $dueDate->setTimeFromString($defaultDueTime));
                    if ($openDate->gt($closeDate)) {
                        $this->sendError('The open date must be before the close date');
                        return;
                    }
                    $units[$idx]['open_date']  = $openDate;
                    $units[$idx]['close_date'] = $closeDate;

                    // If modifying an already imported unit,
                    // and I can't read the original copy:
                    // I can still modify the dates of the assessments in
                    // this unit, but can't add anything new to it.
                    if (!($courseImplId || $canReadUnitSpecs[$unitSpecId])) {
                        $this->sendError('You do not have permission to import this unit');
                        return;
                    }
                }
            }

            // Ensure at least one unit was selected for import
            if (!$countImportedUnits) {
                $this->sendError('You must select at least one unit to import');
                return;
            }
        } catch (\Exception $e) {
            $this->sendError('You must specify a valid open and close date for each unit you are importing');
            return;
        }

        // How many Units are already imported? (cf. new import)
        $countImportedUnits = 0;

        $unitImpls = $this->unitImplementationMapper->createCollection();
        foreach ($units as $unit) {
            if (isset($unit['import'])) {
                // Are we modifying an already imported Unit?
                if ($unit['unit_impl_id']) {
                    $countImportedUnits++;
                }

                // ID will be null if UnitSpec not yet imported
                $unitImpl = $this->unitImplementationMapper->createModel($unit['unit_impl_id']);
                $unitSpec = $this->unitSpecificationMapper->createModel($unit['unit_spec_id']);
                $unitImpl->setSpecification($unitSpec);
                $unitImpl->setOpenDate($unit['open_date']);
                $unitImpl->setDueDate($unit['close_date']);
                $unitImpls->add($unitImpl);
            }
        }

        try {
            // If not modifying any already imported Units, we can treat this modification merely as a fresh import.
            if ('import' === $action || !$countImportedUnits) {
                $dto = $this->importFactory->buildForNewImport($unitImpls, $defaultDueTime);
            } elseif ('modify' === $action) {
                $dto = $this->importFactory->buildForImportModify($unitImpls, $defaultDueTime);
                /** @var UnitImplementation\Model $unitImpl */
                foreach ($dto->getUnitImplementations() as $unitImpl) {
                    // get name from impl title, if spec folder is null
                    // this happens when unit spec is deleted and there's no assessments
                    if (!$unitImpl->getSpecification()->getFolder() instanceof Folder\Model) {
                        $fetchedUnitImpl = $this->unitImplementationMapper->findById($unitImpl->getId());
                        $unitImpl->setTitle($fetchedUnitImpl->getTitle());
                    }
                }
            } else {
                throw new \DomainException('Unable to determine if this is a new import or modification of prior import.');
            }
        } catch (InvalidDataStateException $e) {
            // Let sentry know that we have broken data on this school which needs to be fixed
            $this->captureException($e);
            // Halt the import, but let the user know why in a useful way.
            $this->sendError($e->getMessage() . ' - This issue has been logged');
            return;
        }

        // respond with stage 4: coursework list
        $this->response = $this->responseDependentOnRequest(
            '@course/unitImportResults.twig',
            [
                'action'              => $action,
                'canReadCourseSpec'   => $canReadCourseSpec,
                'canReadUnitSpecs'    => $canReadUnitSpecs,
                'componentId'         => $componentId,
                'courseImplId'        => $courseImplId,
                'courseSpecId'        => $courseSpecId,
                'dto'                 => $dto,
                'folderIds'           => $targetFolderIds,
                'unitDefaultDueDates' => $unitDefaultDueDates,
            ]
        );
    }


    /**
     * This POST is the result of import stage 3 (final).
     * Do the actual import and create all relevant database entries.
     *
     * @api   POST /course/import/activities
     * @since 160
     * @var string   $query [Redirect::RETURN_URL]                              Optional return URL set by caller
     * @var string   $post  ['action']                                           {'import' | 'modify'}
     * @var int      $post  ['course_spec_id']
     * @var int|null $post  ['course_impl_id']
     * @var int|null $post  ['component_id']
     * @var string   $post  ['import_folders']                                   comma-separated folderIds to import to
     * @var array    $post  ['unit']  Indexed by unitSpec.id
     * @var int      $post  ['unit'][unitSpecId]['unit_spec_id']
     * @var int|null $post  ['unit'][unitSpecId]['unit_impl_id']
     * @var string   $post  ['unit'][unitSpecId]['open_date']                    UnitImpl's Open Date
     * @var string   $post  ['unit'][unitSpecId]['close_date']                   UnitImpl's Close Date
     * @var array    $post  ['unit'][unitSpecId]['a']                            UnitImpl's Indexed by Assessment.id
     * @var int      $post  ['unit'][unitSpecId]['a'][assId]['specId']
     * @var int      $post  ['unit'][unitSpecId]['a'][assId]['implId']
     * @var bool     $post  ['unit'][unitSpecId]['a'][assId]['checked']
     * @var string   $post  ['unit'][unitSpecId]['a'][assId]['dates']['publish']
     * @var string   $post  ['unit'][unitSpecId]['a'][assId]['dates']['open']
     * @var string   $post  ['unit'][unitSpecId]['a'][assId]['dates']['due']
     * @var string   $post  ['unit'][unitSpecId]['a'][assId]['dates']['date']
     * @throws Base\Exception
     */
    public function postAssessments()
    {
        // Transact this entire request, rolling back if any exception is raised
        try {
            $this->unitImplementationMapper->getDatabase()->transact(function () {
                $this->persistCourseImplementation($this->requestPost);
            });
        } catch (\Throwable $e) {
            $this->sendError($e->getMessage());
            return;
        }

        /* Redirect back to wherever you came from...
         * ...hopefully (...your confidence is inspiring)
         */
        $encodedReturnUrl = $this->requestQuery->get(Redirect::RETURN_URL);
        if ($encodedReturnUrl) {
            $redirect = Redirect::decode($encodedReturnUrl);
        } else {
            $redirect = $this->getSession()->getHistory(2);
        }
        $this->response = $this->responseAsJson(['redirect' => $redirect]);
    }


    /**
     * Recursive method to create/update assessments using form data
     *
     * @param AssessmentContext $context
     * @param array             $data
     * @var null|int            $data['spec_id']    Will be null if not imported from a Spec
     * @var null|int            $data['impl_id']    Will be null if not yet imported
     * @var null|int            $data['checked']     Did user check the item?
     * @var null|string[]       $data['dates']
     * @var null|mixed[]        $data['a']          If this assessment is a Project, the data for its sub-assessables
     * @throws MapperException
     */
    protected function parseAssessmentForm(AssessmentContext $context, array $data)
    {
        $specId    = isset($data['spec_id']) ? (int)$data['spec_id'] : null;
        $implId    = isset($data['impl_id']) ? (int)$data['impl_id'] : null;
        $isChecked = isset($data['checked']);
        $dateData  = $data['dates'] ?? [];
        $subData   = $data['a'] ?? null;

        // Base Case #1 - Is item unchecked, and un-imported?
        if (!$isChecked && !$implId) {
            return;
        }

        $specAssessment = null;
        if (!$implId) {
            // This is a creation from the spec
            $specAssessment = $this->assessmentMapperProxy->findById($specId);
        }

        $assessment = $this->importFactory->importAssessment($implId, $specAssessment, $context, !$isChecked, $this->getUser(), $dateData);

        // Recursive case
        // If $subData exists but $assessment is null, the project is de-selected and removed with all subData
        if ($assessment && $subData) {
            foreach ($subData as $subDatum) {
                /** @var Project $project */
                $project = $assessment;
                $this->parseAssessmentForm($project, $subDatum);
            }
        }

        /* A specific usage where creating projects and activities
         * always invalidates the project result cache
         */
        if ($assessment instanceof Project) {
            $projectResultCache = new Project\ResultCache\ResultCache($this->projectResultCacheMapper);
            $projectResultCache->refresh($this->assessmentMapperProxy, $assessment);
        }
    }


    /**
     * This GET provides the import form, stepped in at stage 2 (since course + folder cannot be changed)
     * Modify the Unit information for a previously imported Course
     *
     * @api   GET /course/import/{courseImplId}/modify
     * @since 165
     *
     * @param CourseImplementation\Model $courseImpl
     * @throws MapperException
     */
    public function getModify(CourseImplementation\Model $courseImpl)
    {
        try {
            $courseSpec = $this->courseSpecificationMapper->findById($courseImpl->getSpecification()->getId());
        } catch (MapperException\Deleted $e) {
            // We can still modify a course-import if the courseSpec is deleted
            $courseSpec = $e->getDeletedObject();
        }

        // Load UnitSpecs that have not been soft-deleted (to make available for import)
        /** @var CourseSpecification\Model $courseSpec */
        $courseSpec->setUnits($this->unitSpecificationMapper->findAllByCourseId($courseSpec->getId()));

        // Load UnitImpls
        $courseImpl->setUnits($this->unitImplementationMapper->findAllByCourseId($courseImpl->getId()));

        // Add un-imported UnitSpecs to CourseImpl, so user can select them for import
        foreach ($courseSpec->getUnits() as $unitSpec) {
            $this->createProposedUnitImplementations($unitSpec, $courseImpl);
        }

        // Re-order UnitImpls alphabetically
        /** @var GenericCollection|UnitImplementation\Model[] $orderedUnits */
        $orderedUnits = $courseImpl->getUnits()->sortBy('title', 'ASC');
        $courseImpl->setUnits($orderedUnits);

        $this->response = $this->responseDependentOnRequest(
            '@course/import.twig',
            [
                'action'           => 'modify',
                'initialStage'     => self::IMPORT_STAGE_UNITS,
                'courseSpec'       => $courseSpec,
                'courseSpecId'     => $courseSpec->getId(),
                'courseImpl'       => $courseImpl,
                'folderIds'        => [$courseImpl->getFolder()->getId()],
                'destinations'     => [
                    'id'   => $courseImpl->getFolder()->getId(),
                    'name' => $courseImpl->getFolder()->getTitle(),
                ],
                'unitsImpls'       => $courseImpl->getUnits(),
                'returnUrlName'    => Redirect::RETURN_URL,
                'encodedReturnUrl' => $this->requestQuery->get(Redirect::RETURN_URL),
                'canReadUnitSpecs' => $this->canReadUnitSpecs($courseSpec),

                /* You're modifying an already-imported course so don't worry about this warning
                 * You can turn it off by pretending it was never imported already (the Import
                 * and Modify Import share the same twig fragments...)
                 */
                'importedAlready' => false,
                'importedAlreadyRedirect' => null,
            ]
        );
    }


    /**
     * Determine if the specified UnitSpec has already been imported to the specified CourseImpl.
     *
     * If not, create a proposed UnitImpl with no ID, and add to the CourseImpl. The user will then
     * be able to select that they wish to import the proposed UnitImpl.
     *
     * @param UnitSpecification\Model    $unitSpec
     * @param CourseImplementation\Model $courseImpl
     * @throws MapperException
     */
    private function createProposedUnitImplementations(
        UnitSpecification\Model $unitSpec,
        CourseImplementation\Model $courseImpl
    ): void {
        // Has this UnitSpec been imported?
        foreach ($courseImpl->getUnits() as $unitImpl) {
            if ((int)$unitImpl->getSpecification()->getId() === (int)$unitSpec->getId()) {
                // unitSpec already imported, do nothing
                return;
            }
        }

        // unitSpec not imported, add it to CourseImpl as available for import
        $unitImpl = $this->unitImplementationMapper->createModel();
        $unitImpl->setSpecification($unitSpec);
        $unitImpl->setTitle($unitSpec->getFolder()->getName());
        $courseImpl->getUnits()->add($unitImpl);
    }


    /**
     * View a list of courses associated with a homepage
     *
     * @api   GET /course/import/management/{folderId}
     * @since 165
     *
     * @param int $folderId
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function getManagement($folderId)
    {
        /* Find all the Courses associated with this Folder, via imported Units
         */
        $courseImpls = $this->courseImplementationMapper->findByFolderId($folderId);

        // Short cut if possible.
        $folder = $courseImpls->count()
            ? $courseImpls->first()->getFolder()
            : $this->folderMapper->findById($folderId);

        $module     = Component_Definition::MODULE_HOMEPAGE;
        $class      = HomepageOutlineComponentController::class;
        $components = $this->componentInstanceMapper->findByContextAndClass($module, $folder->getId(), $class);

        // Find the names of components displaying each CourseImport
        $componentNames = [];
        foreach ($courseImpls as $impl) {
            $componentNames[$impl->getId()] = [];
        }

        foreach ($components as $component) {
            $courseImplId = $component->getConfigArray()['importedCourse']['value'];

            // Is it displaying a CourseImport ?
            if ($courseImplId) {
                $config                          = $component->getConfigArray();
                $name                            = $config['componentname']['value'] ?: $config['componentname']['defaultValue'];
                $componentNames[$courseImplId][] = $name;
            }
        }

        // Traverse the ancestry of each CourseSpec's folder so we can render breadcrumbs, caching along the way
        $folderCache = [];
        foreach ($courseImpls as $courseImpl) {
            $folderCrumb = $courseImpl->getSpecification()->getFolder();

            while (true) {
                $folderId = $folderCrumb->getId();

                // Have we seen this folder before?
                if (($folderCache[$folderId] ?? null) instanceof Folder\Model) {
                    break;
                }
                // Add to cache
                $folderCache[$folderId] = $folderCrumb;

                // Is this the root?
                if (0 === (int)$folderCrumb->getParent()->getId()) {
                    break;
                }
                try {
                    $folderCrumb = $this->folderMapper->findCheaplyById($folderCrumb->getParent()->getId());
                } catch (MapperException $e) {
                    break;
                }
            }
        }

        // Convert our cache to linear arrays for twig
        $folderAncestry = [];
        foreach ($courseImpls as $courseImpl) {
            $courseImplId                  = $courseImpl->getId();
            $parent                        = $courseImpl->getSpecification()->getFolder();
            $parent                        = $folderCache[$parent->getParent()->getId()];
            $folderAncestry[$courseImplId] = [];
            while (true) {
                $folderAncestry[$courseImplId][] = $parent;

                if (!$parent instanceof Folder\Model) {
                    break;
                }
                $parent = $folderCache[$parent->getParent()->getId()] ?? null;
            }
            $folderAncestry[$courseImplId] = array_reverse($folderAncestry[$courseImplId]);
        }

        $this->response = $this->responseDependentOnRequest(
            '@course/management.twig',
            [
                'folder'           => $folder,
                'courseImpls'      => $courseImpls,
                'componentNames'   => $componentNames,
                'folderAncestry'   => $folderAncestry,
                'returnUrlName'    => Redirect::RETURN_URL,
                'encodedReturnUrl' => Redirect::encode($this->routeGenerator->generate('courseImport.getManagement', ['folderId' => $folder->getId()])),
            ]
        );
    }


    /**
     * Deletes a Course import, along with ALL of its associated data
     *
     * @api   DELETE /course/import/{courseImplId}
     * @since 165
     * @param CourseImplementation\Model $courseImpl
     * @throws Base\Exception
     */
    public function importDelete(CourseImplementation\Model $courseImpl)
    {
        if (!$courseImpl instanceof CourseImplementation\Model) {
            $this->sendError('Course Import not found');
            return;
        }

        // Will delete subsidiary Units, Assessments, Responses + Feedbacks
        try {
            if (!$this->courseImplementationMapper->delete($courseImpl)) {
                $this->sendError('Unable to delete Course Import');
                return;
            }
        } catch (MapperException $e) {
            $this->sendError('Unable to delete Course Import: ' . $e->getMessage());
            return;
        }

        // Delete any CourseOutline components that display this CourseImport
        try {
            $module              = Component_Definition::MODULE_HOMEPAGE;
            $folderId            = $courseImpl->getFolder()->getId();
            $class               = HomepageOutlineComponentController::class;
            $components          = $this->componentInstanceMapper->findByContextAndClass($module, $folderId, $class);
            $componentsForCourse = $components->filter(function ($component) use ($courseImpl) {
                /** @var Component_Instance $component */
                return (int)$courseImpl->getId() === (int)$component->getConfigArray()['importedCourse']['value'];
            });
            $this->componentInstanceMapper->delete($componentsForCourse);

            $this->response = $this->responseAsJson(['data' => true]);
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), [], 500);
            return;
        }
    }


    /**
     * Create a CourseOutline component for the specified CourseImpl.
     *
     * This action is available from a homepage's course management page if the CourseImport
     * is not being displayed in a CourseOutline component.
     *
     * @api   POST /course/import/{courseImplId}/restore
     * @since 165
     * @param CourseImplementation\Model $courseImpl
     * @throws MapperException
     * @throws \PDOException
     */
    public function restoreCourse(CourseImplementation\Model $courseImpl)
    {
        $componentInstance = $this->importFactory->findAvailableCourseOutlineComponent($courseImpl->getFolder()->getId());
        $componentName     = $this->importFactory->injectImportIntoComponent($courseImpl, $componentInstance);
        $this->response = $this->responseAsJson([
            'result'        => 'success',
            'componentName' => $componentName,
        ]);
    }


    /**
     * Create a set of UnitImplementations available from the specified CourseSpec
     *
     * @param int $courseSpecId
     * @return GenericCollection|UnitImplementation\Model[]
     * @throws MapperException
     */
    private function findUnitImplementationsForCourseSpecId(int $courseSpecId): GenericCollection
    {
        $unitSpecs = $this->unitSpecificationMapper->findAllByCourseId($courseSpecId);
        $unitSpecs = $unitSpecs->sortByCallback(function ($a, $b) {
            return  $b->getFolder()->getSequence() <=> $a->getFolder()->getSequence();
        });

        $unitImpls = $this->unitImplementationMapper->createCollection();
        foreach ($unitSpecs as $spec) {
            $unitImpls->add($this->unitImplementationMapper->createModel()
                                                           ->setTitle($spec->getFolder()->getName())
                                                           ->setSpecification($spec));
        }
        return $unitImpls;
    }


    /**
     * Convert CSV folder ids to integer array
     *
     * @param string $folderIdCsv
     * @return int[]
     */
    private function parseFolderIds(string $folderIdCsv): array
    {
        // Explode on ',', filter out empty, then cast to int
        return array_map(function ($folderId) {
            return (int)$folderId;
        }, array_filter(explode(',', trim($folderIdCsv))));
    }


    /**
     * Does the current user have read access to this course spec?
     *
     * @param CourseSpecification\Model $spec
     * @return bool
     * @throws MapperException
     */
    private function canReadCourseSpec(CourseSpecification\Model $spec): bool
    {
        return $this->access->getFolderAccess($this->getUser(), $spec->getFolder()->getId()) >= UserAccess::ACCESS_READ;
    }


    /**
     * What permissions does the current user have on the units in this spec?
     *
     * @param CourseSpecification\Model $spec
     * @return array
     */
    private function canReadUnitSpecs(CourseSpecification\Model $spec): array
    {
        $canReadUnitSpecs = [];
        foreach ($spec->getUnits() as $unit) {
            if ($unit->isDeleted()) {
                $canReadUnitSpecs[$unit->getId()] = false;
                continue;
            }
            try {
                $canReadUnitSpecs[$unit->getId()] = $this->access->getFolderAccess($this->getUser(), $unit->getFolder()->getId()) >= UserAccess::ACCESS_READ;
            } catch (MapperException $e) {
                $canReadUnitSpecs[$unit->getId()] = false;
            }
        }
        return $canReadUnitSpecs;
    }


    /**
     * Persist a course implementation, based upon submitted form data.
     *
     * @param Values $post POST data
     *
     * @var string   $post  ['action']                                           {'import' | 'modify'}
     * @var int      $post  ['course_spec_id']
     * @var int|null $post  ['course_impl_id']
     * @var int|null $post  ['component_id']
     * @var string   $post  ['import_folders']                                   comma-separated folderIds to import to
     * @var array    $post  ['unit']  Indexed by unitSpec.id
     * @var int      $post  ['unit'][unitSpecId]['unit_spec_id']
     * @var int|null $post  ['unit'][unitSpecId]['unit_impl_id']
     * @var string   $post  ['unit'][unitSpecId]['open_date']                    UnitImpl's Open Date
     * @var string   $post  ['unit'][unitSpecId]['close_date']                   UnitImpl's Close Date
     * @var array    $post  ['unit'][unitSpecId]['a']                            UnitImpl's Indexed by Assessment.id
     * @var int      $post  ['unit'][unitSpecId]['a'][assId]['specId']
     * @var int      $post  ['unit'][unitSpecId]['a'][assId]['implId']
     * @var bool     $post  ['unit'][unitSpecId]['a'][assId]['checked']
     * @var string   $post  ['unit'][unitSpecId]['a'][assId]['dates']['publish']
     * @var string   $post  ['unit'][unitSpecId]['a'][assId]['dates']['open']
     * @var string   $post  ['unit'][unitSpecId]['a'][assId]['dates']['due']
     * @var string   $post  ['unit'][unitSpecId]['a'][assId]['dates']['date']
     *
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    private function persistCourseImplementation(Values $post): void
    {
        // Parse form data
        $action       = trim($post['action']);
        $courseSpecId = trim($post['course_spec_id']);
        $courseImplId = trim($post['course_impl_id']) === '' ? null : (int)trim($post['course_impl_id']);
        $componentId  = trim($post['component_id']);
        $folderIds    = $this->parseFolderIds($post['import_folders']);
        $unitImplData = $post['unit'];

        // Algorithm:
        //
        // 1. Create a graph of the CourseImpl that exists in DB
        // 2. Create a graph of the CourseImpl as indicated in POST data
        // 3. Traverse DB-graph
        //   3.1 if(entity not in POST-graph)
        //       3.1.1 delete entity
        // 4. Traverse POST-graph
        //   4.1 if(entity not in DB-graph)
        //       4.1.1 create entity
        //   4.2 else(entity in DB-graph)
        //       4.2.1 update entity
        //   4.3 save entity


        // On new-import, we may be creating multiple courseImpls.
        // Therefore store in array and index by ClassFolderId
        $courseSpec = null;
        try {
            $courseSpec  = $this->courseSpecificationMapper->findById($courseSpecId);
        } catch (MapperException\Deleted $e) {
            // Fail, only if creating a new import. We can still modify.
            if ('import' === $action) {
                throw new \DomainException("Course {$courseSpecId} has been deleted.");
            }
        }

        // Collect folders for unit and course pages - needed for criteria import
        $unitSpecs = [];
        foreach ($unitImplData as $unitImp) {
            $unitSpecs[] = (int)$unitImp['unit_spec_id'];
        }

        $classFolders = $this->folderMapper->findByColumn('id', $folderIds);
        $courseImpl = null;
        try {
            $courseImpl = $this->courseImplementationMapper->findById($courseImplId);
        } catch (MapperException $e) {
            // new one will be created
        }
        $courseImpls = $this->importFactory->createCourseImpls($courseSpec, $unitSpecs, $classFolders, $courseImpl);

        // Delete de-selected Units and Assessments

        // For each Class we are importing the Course to:
        foreach ($courseImpls as $classFolderId => $courseImpl) {
            // Traverse pre-existing course import, deleting any unitImpls not in POST
            // Deleting a UnitImpl automatically cascades the deletion to enclosed Assessments
            // This loop will be skipped during a new-import (CourseImpl will not yet contain Units)
            foreach ($courseImpl->getUnits() as $unitImpl) {
                $unitSpec = $unitImpl->getSpecification();

                // Did user de-select this unit?
                if (!array_key_exists($unitSpec->getId(), $unitImplData)) {
                    // Delete the unitImpl. Cascading the delete to subsidiary IAssessables
                    $this->unitImplementationMapper->delete($unitImpl);
                }
            }
        }

        // Create any un-imported UnitImpls from corresponding UnitSpec
        // Update any already-imported UnitImpls
        // Create any un-imported AssessmentImpls from corresponding AssessmentSpec
        // Update any already-imported AssessmentImpls
        foreach ($courseImpls as $classFolderId => $courseImpl) {
            // Even if an import-modify, we need to explicitly save to update timestamps (and possibly the folderId)
            $this->courseImplementationMapper->save($courseImpl);

            // For each UnitImpl in POST data
            foreach ($unitImplData as $unitPair) {
                $unitImplOpenDate  = \Alaress_Date_Format::newLocalShort($unitPair['open_date']);
                $unitImplCloseDate = \Alaress_Date_Format::newLocalShort($unitPair['close_date'])->endOfDay();

                // Retrieve UnitSpec from DB (even if deleted)
                try {
                    $unitSpec = $this->unitSpecificationMapper->findById($unitPair['unit_spec_id']);
                } catch (Deleted $e) {
                    $unitSpec = $e->getDeletedObject();
                }
                $unitImpl = $this->importFactory->importUnit($courseImpl, $unitSpec, $unitImplOpenDate, $unitImplCloseDate);

                if (is_array($unitPair['a'] ?? false)) {
                    // Create/Update AssessmentImpls
                    foreach ($unitPair['a'] as $aData) {
                        $this->parseAssessmentForm($unitImpl, $aData);
                    }
                }
            }

            // Associate the CourseImpl with a CourseOutline Component

            /* If (componentId was supplied)
             *   update that.
             * elseif (empty CourseOutline component available)
             *   use that (filter for first sequence)
             * else
             *   create a new CourseOutline component (at top of the page)
             */
            if ('import' === $action) {
                $componentInstance = null;
                // Was a componentId supplied?
                if ($componentId) {
                    $componentInstance = $this->componentInstanceMapper->findByInstanceId($componentId, $classFolderId);
                } else {
                    $componentInstance = $this->importFactory->findAvailableCourseOutlineComponent($classFolderId);
                }
                $this->importFactory->injectImportIntoComponent($courseImpl, $componentInstance);
            }
        }

        // After all assessments are imported: link/unlink lesson plans.
        // This must be done after creating all impl assessments: only then is
        // it known which linked assessments actually exist.
        foreach ($courseImpls as $courseImpl) {
            foreach ($courseImpl->getUnits() as $unitImpl) {
                foreach ($unitImpl->getAssessables() as $implAssessment) {
                    $specAssessment = $implAssessment->getSpecificationAssessment();
                    if ($specAssessment) {
                        // Replicate only if this assessment didn't already
                        // exist in the unit.
                        if (!$this->isAssessmentAlreadyInUnit($implAssessment, $unitImplData)) {
                            $this->lessonPlanActivitiesReplicator->replicate($specAssessment, $implAssessment);
                        }
                    }
                }
            }
        }
    }

    /**
     * Did this assessment already exist in the unit implementation?
     *
     * * if the implementation assessment ID was passed in POST data, then it
     *   already existed
     * * otherwise, it did not exist
     *
     * @param IAssessable $implAssessment
     * @param array       $unitImplData
     * @return bool
     */
    private function isAssessmentAlreadyInUnit(IAssessable $implAssessment, array $unitImplData): bool
    {
        foreach ($unitImplData as $unitImplDatum) {
            foreach (array_keys($unitImplDatum['a'] ?? []) as $assessmentId) {
                if ($implAssessment->getId() === (int)$assessmentId) {
                    return true;
                }
            }
        }
        return false;
    }
}
