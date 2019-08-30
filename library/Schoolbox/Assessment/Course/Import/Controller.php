<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Web\Controller as Base;
use Component_ConfigDefinition;
use Component_ConfigDefinitionMapper;
use Component_ConfigMapper;
use Component_Definition;
use Component_Instance;
use Component_InstanceMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Component\Homepage\Outline\Controller as HomepageOutlineComponentController;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
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

    /** @var Component_ConfigDefinitionMapper */
    private $componentConfigDefinitionMapper;

    /** @var Component_ConfigMapper */
    private $componentConfigInstanceMapper;

    /** @var Replicator */
    private $replicator;

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
        Component_ConfigDefinitionMapper $componentConfigDefinitionMapper,
        Component_ConfigMapper $componentConfigInstanceMapper,
        Access $access,
        ImportFactory $importFactory,
        Replicator $replicator,
        Assessment\MapperProxy $assessmentMapperProxy
    ) {
        $this->unitSpecificationMapper         = $unitSpecificationMapper;
        $this->unitImplementationMapper        = $unitImplementationMapper;
        $this->folderMapper                    = $folderMapper;
        $this->courseSpecificationMapper       = $courseSpecificationMapper;
        $this->courseImplementationMapper      = $courseImplementationMapper;
        $this->componentInstanceMapper         = $componentInstanceMapper;
        $this->componentConfigDefinitionMapper = $componentConfigDefinitionMapper;
        $this->componentConfigInstanceMapper   = $componentConfigInstanceMapper;
        $this->access                          = $access;
        $this->importFactory                   = $importFactory;
        $this->replicator                      = $replicator;
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
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getImport(
        ?CourseSpecification\Model $courseSpec,
        ?Folder\Model $targetClass,
        ?Component_Instance $component
    ): void {
        // If the URL specified both a CourseSpec or Import destination (ie a Folder), then skip to step 2
        $initialStage = $courseSpec && $targetClass ? self::IMPORT_STAGE_UNITS : self::IMPORT_STAGE_COURSE_AND_CLASSES;
        $data         = [
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
            'encodedReturnUrl' => $this->getRequest()->query->get(Redirect::RETURN_URL),
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
        $this->tpl('course', 'import', $data);
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
     * @throws \Alaress\Web\Controller\Exception
     */
    public function postClasses()
    {
        $courseSpecId    = trim($this->getRequest()->post['course_spec_id']);
        $targetFolderIds = $this->parseFolderIds($this->getRequest()->post['import_folders']);
        $componentId     = trim($this->getRequest()->post['component_id']);

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

        // respond with stage 2: units
        $this->tpl('course', 'unitImportForm', [
            'action'           => 'import',
            'courseSpecId'     => $courseSpecId,
            'folderIds'        => $targetFolderIds,
            'componentId'      => $componentId,
            'unitsImpls'       => $this->findUnitImplementationsForCourseSpecId($courseSpecId),
            'canReadUnitSpecs' => $this->canReadUnitSpecs($courseSpec),
        ]);
    }


    /**
     * Search function. Retrieve a list of Folders that match the string in $_GET['query']
     *
     * Searches by both name and class code
     *
     * @api   GET /course/import/classes
     * @since 160
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function searchClasses()
    {
        $query          = $this->getRequest()->query->get('query');
        $folders        = (new Destinations($this->folderMapper))->findImportable($query);

        /* Swap keys for selectize compatibility -_-
         */
        $data = [];

        /** @var Folder\Model[] $folders */
        foreach ($folders as $folder) {

            // Ignore folders with CourseSpecs
            if ($folder->getCourseSpecification() instanceof CourseSpecification\Model) {
                continue;
            }

            // Ignore folders with UnitSpecs
            if ($folder->getUnitSpecification() instanceof UnitSpecification\Model) {
                continue;
            }

            // Make sure the user has access to the folder before we return it
            if ($this->access->getFolderAccess($this->getUser(), $folder->getId()) < UserAccess::ACCESS_WRITE) {
                continue;
            }

            $data[] = [
                'id'   => $folder->getId(),
                'name' => $folder->getTitle(),
            ];
        }

        $this->getResponse()->content->set($this->buildJsonOutput($data));
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
     * @throws Unauthorized
     **@throws \Alaress\Web\Controller\Exception
     * @throws \DomainException
     **/
    public function postUnits()
    {
        $post = $this->getRequest()->post;

        $action           = trim($post['action']);
        $encodedReturnUrl = trim($post[Redirect::RETURN_URL]);
        $courseSpecId     = trim($post['course_spec_id']);
        $targetFolderIds  = $this->parseFolderIds($this->getRequest()->post['import_folders']);
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
        try {

            $countImportedUnits = 0;
            /** @var array $units */
            foreach ($units as $idx => $unit) {
                if (isset($unit['import'])) {
                    $countImportedUnits++;
                    $openDate  = \Alaress_Date_Format::newLocalShort($unit['open_date']);
                    $closeDate = \Alaress_Date_Format::newLocalShort($unit['close_date']);
                    if ($openDate->gt($closeDate)) {
                        $this->sendError('The open date must be before the close date');
                        return;
                    }
                    $units[$idx]['open_date']  = $openDate;
                    $units[$idx]['close_date'] = $closeDate;

                    $unitSpecId = $unit['unit_spec_id'];

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
                $dto = $this->importFactory->buildForNewImport($unitImpls);
            } elseif ('modify' === $action) {
                $dto = $this->importFactory->buildForImportModify($unitImpls);
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
        $this->tpl('course', 'unitImportResults', [
            'action'            => $action,
            'returnUrlName'     => Redirect::RETURN_URL,
            'encodedReturnUrl'  => $encodedReturnUrl,
            'courseSpecId'      => $courseSpecId,
            'courseImplId'      => $courseImplId,
            'componentId'       => $componentId,
            'folderIds'         => $targetFolderIds,
            'dto'               => $dto,
            'canReadCourseSpec' => $canReadCourseSpec,
            'canReadUnitSpecs'  => $canReadUnitSpecs,

        ]);
    }


    /**
     * This POST is the result of import stage 3 (final).
     * Do the actual import and create all relevant database entries.
     *
     * @api   POST /course/import/activities
     * @since 160
     * @var          $_GET  $query
     * @var string   $query [Redirect::RETURN_URL]                              Optional return URL set by caller
     * @var          $_POST $post
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
        // Parse form data
        $post         = $this->getRequest()->post;
        $action       = trim($post['action']);
        $courseSpecId = trim($post['course_spec_id']);
        $courseImplId = trim($post['course_impl_id']);
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
        /** @var CourseImplementation\Model[] $courseImpls */
        $courseImpls = [];
        $courseSpec  = $this->courseSpecificationMapper->findById($courseSpecId);

        // Collect folders for unit and course pages - needed for criteria import
        $unitSpecs = [];
        /** @var mixed[] $unitImplData */
        foreach ($unitImplData as $unitImp) {
            $unitSpecs[] = (int)$unitImp['unit_spec_id'];
        }

        $unitFolders = [];
        foreach ($courseSpec->getUnits() as $unitSpec) {
            if (in_array((int)$unitSpec->getId(), $unitSpecs, true)) {
                $unitFolders[] = $unitSpec->getFolder()->getId();
            }
        }

        $courseFolder = [$courseSpec->getFolder()->getId()];

        // Are we creating a new import?
        if ('import' === $action) {
            $courseSpec = $this->courseSpecificationMapper->findById($courseSpecId);
            foreach ($folderIds as $classFolderId) {
                $courseImpl = $this->courseImplementationMapper->createModel();
                $courseImpl->setSpecification($courseSpec);
                $courseImpl->setFolder($this->folderMapper->findById($classFolderId));
                $courseImpls[$classFolderId] = $courseImpl;

                //Save unit criteria to class page
                $allFolders = array_merge($courseFolder, $unitFolders);
                $this->copyCriteria($allFolders, $classFolderId);
            }
        } // Else we are modifying an existing import
        else {
            // We can only modify a single Course Import at a time.
            $courseImpl                                     = $this->courseImplementationMapper->findById($courseImplId);
            $courseImpls[$courseImpl->getFolder()->getId()] = $courseImpl;

            // Load UnitImplementations
            $courseImpl->setUnits($this->unitImplementationMapper->findAllByCourseId($courseImpl->getId()));

            // Load IAssessables
            foreach ($courseImpl->getUnits() as $unitImpl) {
                $unitImpl->setAssessables($this->assessmentMapperProxy->findAllByUnitImplementationIds([$unitImpl->getId()]));
            }

            //Save unit criteria to class page
            $classFolderId = $folderIds[0];
            $this->copyCriteria($unitFolders, $classFolderId);
        }

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

        try {
            // Create any un-imported UnitImpls from corresponding UnitSpec
            // Update any already-imported UnitImpls
            // Create any un-imported AssessmentImpls from corresponding AssessmentSpec
            // Update any already-imported AssessmentImpls
            foreach ($courseImpls as $classFolderId => $courseImpl) {

                // Even if an import-modify, we need to explicitly save to update timestamps (and possibly the folderId)
                $this->courseImplementationMapper->save($courseImpl);

                // For each UnitImpl in POST data
                foreach ($unitImplData as $unitPair) {
                    // Retrieve UnitSpec from DB (even if deleted)
                    try {
                        $unitSpec = $this->unitSpecificationMapper->findById($unitPair['unit_spec_id']);
                    } catch (MapperException\Deleted $e) {
                        $unitSpec = $e->getDeletedObject();
                    }
                    $unitImplOpenDate  = \Alaress_Date_Format::newLocalShort($unitPair['open_date']);
                    $unitImplCloseDate = \Alaress_Date_Format::newLocalShort($unitPair['close_date'])->endOfDay();

                    // Does the CourseImpl already contain this Unit ?
                    $unitSpecId = $unitSpec->getId();
                    $callback   = function ($unitImpl) use ($unitSpecId) {
                        /** @var UnitImplementation\Model $unitImpl */
                        return (int)$unitSpecId === (int)$unitImpl->getSpecification()->getId();
                    };
                    /** @var UnitImplementation\Model $unitImpl */
                    $unitImpl = $courseImpl->getUnits()->filter($callback)->first();

                    // Did we already delete this UnitImpl ?
                    if ($unitImpl && $unitImpl->isDeleted()) {
                        continue;
                    }

                    // If the CourseImpl does not yet contain this UnitSpec
                    if (!$unitImpl) {
                        // Create a UnitImpl from this UnitSpec
                        $unitImpl = $this->unitImplementationMapper->createModel();
                        $unitImpl->setSpecification($unitSpec);
                        $unitImpl->setCourse($courseImpl);
                        $unitImpl->setTitle($unitSpec->getFolder()->getTitle());
                        $courseImpl->getUnits()->add($unitImpl);
                    }
                    $unitImpl->setOpenDate($unitImplOpenDate);
                    $unitImpl->setDueDate($unitImplCloseDate);
                    $this->unitImplementationMapper->save($unitImpl);

                    if (is_array($unitPair['a'])) {
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
                        $componentInstance = $this->componentInstanceMapper->findByInstanceId($componentId);
                    } else {
                        $componentInstance = $this->findAvailableCourseOutlineComponent($classFolderId);
                    }
                    $this->injectImportIntoComponent($courseImpl, $componentInstance);
                }
            }
        } catch (\Exception $e) {
            $this->sendError($e->getMessage());
            return;
        }

        /* Redirect back to wherever you came from...
         * ...hopefully (...your confidence is inspiring)
         */
        $encodedReturnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL);
        if ($encodedReturnUrl) {
            $redirect = Redirect::decode($encodedReturnUrl);
        } else {
            $redirect = $this->getSession()->getHistory(2);
        }
        $this->getResponse()->content->set($this->buildJsonOutput(['redirect' => $redirect]));
    }


    /**
     * Recursive method to create/update assessments using form data
     *
     * @param AssessmentContext $context
     * @param array             $data
     * @var null|int            $data ['spec_id']    Will be null if not imported from a Spec
     * @var null|int            $data ['impl_id']    Will be null if not yet imported
     * @var null|1              $data['checked']    Did user check the item?
     * @var null|string[]       $data ['dates']
     * @var null|mixed[]        $data ['a']          If this assessment is a Project, the data for its sub-assessables
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    protected function parseAssessmentForm(AssessmentContext $context, array $data)
    {
        $specId    = isset($data['spec_id']) ? (int)$data['spec_id'] : null;
        $implId    = isset($data['impl_id']) ? (int)$data['impl_id'] : null;
        $isChecked = isset($data['checked']);
        $dateData  = $data['dates'] ?? null;
        $subData   = $data['a'] ?? null;

        // Base Case #1 - Is item unchecked, and un-imported?
        if (!$isChecked && !$implId) {
            return;
        }

        $notifier = $this->assessmentMapperProxy->getNotifier();

        if (!$implId) {
            // This is a creation from the spec
            $assessment = $this->assessmentMapperProxy->findById($specId);
            $before     = [];
        } else {
            // We have been passed the Assessment Impl
            // Retrieve AssessmentImpl from UnitImpl
            $filter     = function ($impl) use ($implId) {
                /** @var IAssessable $impl */
                return (int)$impl->getId() === $implId;
            };
            $assessment = $context->getAssessables()->filter($filter)->first();
            $before     = $notifier->dehydrate($assessment);
        }

        // Base Case #2 - Is item unselected ? (and already imported)
        if (!$isChecked) {
            $this->assessmentMapperProxy->delete($assessment);
            $notifier->notify($assessment, $this->getUser(), $before);
            return;
        }

        // Item is checked.

        // Apply dates regardless of whether it is a Spec or Impl. (If Spec, they will be cloned to the Impl)
        $assessment = $this->applyDates($assessment, $dateData);

        if (!$implId) {
            // INSERT - Create Impl from Spec
            $newAssessment = $this->replicator->replicateInNewContext($assessment, $context, $this->getUser());
            $notifier->notify($newAssessment, $this->getUser(), $before);
        } else {
            // UPDATE
            $newAssessment = null;
            $this->assessmentMapperProxy->save($assessment);
            $notifier->notify($assessment, $this->getUser(), $before);
        }

        // Recursive case
        if ($subData) {
            foreach ($subData as $subDatum) {
                /** @var Project $project */
                $project = $newAssessment ?: $assessment;
                $this->parseAssessmentForm($project, $subDatum);
            }
        }
    }


    /**
     * Apply date strings from an array to the specified Assessment
     *
     * @param IAssessable $assessment
     * @param string[]    $dates
     * @return IAssessable
     * @throws \DomainException if property isn't recognized
     */
    protected function applyDates(IAssessable $assessment, array $dates): IAssessable
    {
        // Set/Update Dates
        foreach ($dates as $property => $local) {
            $date = \Alaress_Date_Format::newLocal($local);
            switch (strtolower($property)) {
                case 'publish':
                    $assessment->setPublishAt($date);
                    break;

                case 'open':
                    $assessment->setOpenAt($date);
                    break;

                case 'due':
                    $assessment->setDueAt($date);
                    break;

                case 'close':
                    $assessment->setCloseAt($date);
                    break;

                default:
                    throw new \DomainException("Invalid date property: set{$property}At");
                    break;
            }
        }
        return $assessment;
    }


    /**
     * Attempts to find a Course Outline ComponentInstance on the specifed page
     * that is not currently linked to a CourseImport.
     *
     * @param int $folderId
     * @return Component_Instance|null
     */
    protected function findAvailableCourseOutlineComponent($folderId): ?Component_Instance
    {
        $module        = Component_Definition::MODULE_HOMEPAGE;
        $class         = HomepageOutlineComponentController::class;
        $compInstances = $this->componentInstanceMapper->findByContextAndClass($module, $folderId, $class);
        $compInstances->sortBy('sequence', 'ASC');
        foreach ($compInstances as $component) {

            // If this component has no associated Course, or the course is not valid
            if (!$component->getConfigArray()['importedCourse'] ||
                !$component->getConfigArray()['importedCourse']['value']
            ) {
                // Conclude the Component is available, and use it
                return $component;
            }
        }
        return null;
    }


    /**
     * Configures the specified component to display the specified course.
     *
     * Creates the component if none specified.
     *
     * @param CourseImplementation\Model $courseImpl
     * @param Component_Instance|null    $compInstance
     * @return string The name of the new Component
     * @throws MapperException
     * @throws \PDOException
     */
    private function injectImportIntoComponent(
        CourseImplementation\Model $courseImpl,
        ?Component_Instance $compInstance
    ): string {
        // Did we find an available component?
        if ($compInstance) {
            // Find its 'importedCourse' config
            /** @var Component_ConfigDefinition $configDefinition */
            $configDefinition = null;
            foreach ($compInstance->getConfig() as $configItem) {
                if ('importedCourse' === $configItem->getKey()) {
                    $configDefinition = $configItem;
                    break;
                }
            }
            if (!$configDefinition) {
                $configDefinition = $this->componentConfigDefinitionMapper->findByColumnList([
                    'component_definition_id' => $compInstance->getDefinitionId(),
                    'key'                     => 'importedCourse',
                ])->first();
                $configDefinition->setKey('importedCourse');
            }
            $configInstance = $this->componentConfigInstanceMapper->findByColumnList([
                'component_config_definition_id' => $configDefinition->getId(),
                'component_instance_id'          => $compInstance->getId(),
            ])->first();
            if (!$configInstance) {
                /** @var \Component_Config $configInstance */
                $configInstance = $this->componentConfigInstanceMapper->createModel();
                $configInstance->setConfigDefinitionId($configDefinition->getId());
                $configInstance->setInstanceId($compInstance->getId());
            }
            $configInstance->setValue($courseImpl->getId());
            $this->componentConfigInstanceMapper->save($configInstance);
        } // Else no available component, create one.
        else {
            $folder             = $courseImpl->getFolder();
            $configDefinition   = ['importedCourse' => $courseImpl->getId()];
            $courseOutlineClass = HomepageOutlineComponentController::class;
            $compInstance       = $this->componentInstanceMapper->injectNewComponent($folder, $configDefinition, $courseOutlineClass);
            $compInstance       = $this->componentInstanceMapper->findByInstanceId($compInstance->getId());
        }
        $config        = $compInstance->getConfigArray();
        $componentName = $config['componentname']['value'] ?: $config['componentname']['defaultValue'];
        return $componentName;
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

        $this->tpl('course', 'import', [
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
            'encodedReturnUrl' => $this->getRequest()->query->get(Redirect::RETURN_URL),
            'canReadUnitSpecs' => $this->canReadUnitSpecs($courseSpec),
        ]);
    }


    /**
     * Determine if the specified UnitSpec has already been imported to the specified CourseImpl.
     *
     * If not, create a proposed UnitImpl with no ID, and add to the CourseImpl. The user will then
     * be able to select that they wish to import the proposed UnitImpl.
     *
     * @param UnitSpecification\Model    $unitSpec
     * @param CourseImplementation\Model $courseImpl
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
     * @throws \Aura\Router\Exception\RouteNotFound
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
                if ($folderCache[$folderId] instanceof Folder\Model) {
                    break;
                }
                // Add to cache
                $folderCache[$folderId] = $folderCrumb;

                // Is this the root?
                if (0 === (int)$folderCrumb->getParent()->getId()) {
                    break;
                }
                $folderCrumb = $this->folderMapper->findCheaplyById($folderCrumb->getParent()->getId(), ['deleted' => true]);
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
                $parent = $folderCache[$parent->getParent()->getId()];
            }
            $folderAncestry[$courseImplId] = array_reverse($folderAncestry[$courseImplId]);
        }

        $this->tpl('course', 'management', [
            'folder'           => $folder,
            'courseImpls'      => $courseImpls,
            'componentNames'   => $componentNames,
            'folderAncestry'   => $folderAncestry,
            'returnUrlName'    => Redirect::RETURN_URL,
            'encodedReturnUrl' => Redirect::encode($this->getRouter()->generate('courseImport.getManagement', ['folderId' => $folder->getId()])),
        ]);
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
        if (!$this->courseImplementationMapper->delete($courseImpl)) {
            $this->sendError('Unable to delete Course Import');
            return;
        }

        // Delete any CourseOutline components that display this CourseImport
        try {
            $module              = \Component_Definition::MODULE_HOMEPAGE;
            $folderId            = $courseImpl->getFolder()->getId();
            $class               = HomepageOutlineComponentController::class;
            $components          = $this->componentInstanceMapper->findByContextAndClass($module, $folderId, $class);
            $componentsForCourse = $components->filter(function ($component) use ($courseImpl) {
                /** @var Component_Instance $component */
                return (int)$courseImpl->getId() === (int)$component->getConfigArray()['importedCourse']['value'];
            });
            $this->componentInstanceMapper->delete($componentsForCourse);

            $this->getResponse()->content->set($this->buildJsonOutput(['data' => true]));

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
        $componentInstance = $this->findAvailableCourseOutlineComponent($courseImpl->getFolder()->getId());
        $componentName     = $this->injectImportIntoComponent($courseImpl, $componentInstance);
        $this->getResponse()->content->set($this->buildJsonOutput([
                'result'        => 'success',
                'componentName' => $componentName,
            ]
        ));
    }


    /**
     * Create a set of UnitImplementations available from the specified CourseSpec
     *
     * @param int $courseSpecId
     * @return GenericCollection|UnitImplementation\Model[]
     */
    private function findUnitImplementationsForCourseSpecId(int $courseSpecId): GenericCollection
    {
        $unitSpecs = $this->unitSpecificationMapper->findAllByCourseId($courseSpecId);
        $unitImpls = $this->unitImplementationMapper->createCollection();
        foreach ($unitSpecs as $spec) {
            $unitImpls->add($this->unitImplementationMapper->createModel()
                                                           ->setTitle($spec->getFolder()->getName())
                                                           ->setSpecification($spec)
            );
        }
        return $unitImpls;
    }


    /**
     * @param int[] $sourceIds
     * @param int   $destinationId
     * @return static
     */
    private function copyCriteria(array $sourceIds, int $destinationId): self
    {
        $criteriaMapper = Mapper::getInstance();

        // Save copy of criteria with class page as parent
        foreach ($criteriaMapper->findForFolder($sourceIds) as $criteria) {
            $criteriaMapper->findOrCreateOneForFolderByKey(
                $destinationId,
                $criteria->getName(),
                $criteria->getCapability()->getName(),
                false
            );
        }

        return $this;
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
            $canReadUnitSpecs[$unit->getId()] = $this->access->getFolderAccess($this->getUser(), $unit->getFolder()->getId()) >= UserAccess::ACCESS_READ;
        }
        return $canReadUnitSpecs;
    }
}
