<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Alaress\Web\Controller as Base;
use Alaress\Authorizer\Failure\Unauthorized;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Course;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use \Schoolbox\Learning\Assessment\Component\Homepage\Outline\Controller as HomepageOutlineComponentController;
use \Component_InstanceMapper;
use \Component_ConfigDefinition;
use \Component_ConfigDefinitionMapper;
use \Component_ConfigMapper;

class Controller extends Base\Base
{
    const IMPORT_STAGE_COURSE_AND_CLASSES = 1;
    const IMPORT_STAGE_OWNERSHIPS = 2;
    const IMPORT_STAGE_UNITS = 3;
    const IMPORT_STAGE_ASSESSMENTS = 4;

    /** @var CourseSpecificationMapper */
    private $courseSpecificationMapper;

    /** @var CourseImplementationMapper */
    private $courseImplementationMapper;

    /** @var UnitSpecificationMapper */
    private $unitSpecificationMapper;

    /** @var UnitImplementationMapper */
    private $unitImplementationMapper;

    /** @var FolderMapper */
    private $folderMapper;


    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var Access */
    private $access;

    /** @var UserMapper */
    private $userMapper;

    /** @var AssessmentMapper */
    private $assessmentMapper;

    /** @var AssessmentMapperProxy */
    private $assessmentMapperProxy;

    /** @var Course\ImplementationFactory */
    private $courseImplementationFactory;

    /** @var Component_InstanceMapper */
    private $componentInstanceMapper;

    /** @var \Component_DefinitionMapper */
    private $componentDefinitionMapper;

    /** @var Component_ConfigDefinitionMapper */
    private $componentConfigDefinitionMapper;

    /** @var Component_ConfigMapper */
    private $componentConfigInstanceMapper;

    /** @var ErrorMapper */
    private $errorMapper;

    /**
     * @var ImportFactory
     */
    private $importFactory;

    /**
     * Just a raw template render
     *
     * @param string $template
     * @param array $data
     */
    protected function tpl($template, $data = [])
    {
        parent::tpl('course', $template, $data);
    }

    /**
     * This GET is the start of the import process.
     *
     * Import a Course into a CourseOutline Component on a Class page
     *
     * @api   GET /course/import/{courseSpecId}/class/{classCode}/{componentId}
     * @level 160
     *
     * @param CourseSpecification $courseSpec
     * @param Folder              $targetClass
     * @param int                 $componentId The component instance this action was initiated from
     */
    public function getImportToComponentForm(CourseSpecification $courseSpec, Folder $targetClass, $componentId)
    {
        // Clean up URL if logical null $componentId supplied
        if (!$componentId) {
            $data = [
                'courseSpecId' => $courseSpec->getId(),
                'classCode'    => $targetClass->getId(),
            ];
            $location = $this->getRouter()->generate("courseImport.getImportToClassForm", $data);
            $this->getResponse()->redirect->to($location);
            return;
        }

        $this->getImportForm($courseSpec, $targetClass, $componentId);
    }

    /**
     * This GET is the start of the import process. Import a Course into a set of Folders (classes)
     *
     * @api   GET /course/import/{courseSpecId}/class{/classCode}
     * @level 160
     *
     * @param CourseSpecification $courseSpec
     * @param Folder|null         $targetClass
     */
    public function getImportToClassForm(CourseSpecification $courseSpec, Folder $targetClass = null)
    {
        $this->getImportForm($courseSpec, $targetClass, null);
    }

    /**
     * This GET is the start of the import process. Import a Course into a set of Folders (classes)
     *
     * If both a CourseSpec + Folder are specified, populate stages 1 + 2 and jump straight to stage 2.
     *
     * @param CourseSpecification $courseSpec Will be null if set to '0' in URL
     * @param Folder|null         $targetClass
     * @param int|null            $componentId The component instance this action was initiated from
     */
    protected function getImportForm(CourseSpecification $courseSpec, Folder $targetClass = null, $componentId)
    {
        // Ensure that if present, the component instance is an extant CourseOutline
        $componentInstance = null;
        if ($componentId) {
            try {
                $componentInstance = $this->getComponentInstanceMapper()->findById($componentId);
                $definitionId = $componentInstance->getDefinitionId();
                $componentDefinition = $this->getComponentDefinitionMapper()->findById($definitionId);
                if ($componentDefinition->getClass() != HomepageOutlineComponentController::class) {
                    $msg = 'Not a Course-Outline component';
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND, null, $msg);
                }
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
            }
        }

        // If the URL specifies a CourseSpec and Import destination (ie a Folder), then skip to stage 2
        $initialStage = $courseSpec && $targetClass
            ? self::IMPORT_STAGE_OWNERSHIPS
            : self::IMPORT_STAGE_COURSE_AND_CLASSES;

        $this->tpl('import', [
            'action'              => 'import',
            'initialStage'        => $initialStage,
            'courseSpec'          => $courseSpec,
            'componentId'         => $componentInstance ? $componentInstance->getId() : null,
            'folder'              => $targetClass,
            'destinations'        => $targetClass ?
                [
                    'id'   => $targetClass->getId(),
                    'name' => $targetClass->getTitle()
                ] : [],
            'ownershipCandidates' => self::IMPORT_STAGE_OWNERSHIPS == $initialStage
                ? $this->getPossibleOwnerships([$targetClass]) :
                [],
        ]);
    }


    /**
     * This POST is the result of import stage 1.
     *
     * Receives the CourseSpec, Component, + target Classes to import into.
     * Returns a list of users who may own the generated Assessments for each target Class
     *
     * @api   POST /course/import/classes
     * @level 160
     */
    public function postClasses()
    {
        $courseSpecId = trim($this->getRequest()->post['course_spec_id']);
        $targetFolderIds = explode(',', trim($this->getRequest()->post['import_folders']));
        $componentId = trim($this->getRequest()->post['component_id']);

        try {
            $courseSpec = $this->getCourseSpecificationMapper()->findById($courseSpecId);

        } catch (MapperException $e) {
            // the nominated "course" is not actually a course...
            $this->sendError('The selected source is not a course');
            return;
        }

        if (empty($targetFolderIds) && empty($courseSpecId)) {
            // They didn't select anything

            $this->sendError('You must select at least one class and a course to import before you can proceed');
            return;

        } elseif (empty($targetFolderIds)) {
            // They didn't select a class code

            $this->sendError('You must select at least one class before you can proceed');
            return;

        } elseif (empty($courseSpecId)) {
            // They didn't select a course to import

            $this->sendError('You must select a course to import before you can proceed');
            return;
        }

        $folders = $this->getFolderMapper()->findByColumn('id', $targetFolderIds);

        $possibleOwnerships = $this->getPossibleOwnerships($folders);


        // Everything kosher, proceed with the mage
        // respond with stage 2: ownership
        $this->tpl('import/ownership', [
            'courseSpec'          => $courseSpec,
            'targetFolders'       => $folders,
            'componentId'         => $componentId,
            'ownershipCandidates' => $possibleOwnerships,
        ]);
    }

    /**
     * Generate sets of possible owners for a set of Folders.
     *
     * @param \Traversable|Folder[] $folders
     * @return \User[][]|GenericCollection<Collection<User>> indexed by folderId
     */
    protected function getPossibleOwnerships($folders)
    {
        $possibleOwnerships = new GenericCollection(Collection::class);

        foreach ($folders as $folder) {
            $usersWithWritePerm = $this->getUserAccessMapper()->findUsersInCategory(
                $folder, Role::STAFF, UserAccess::ACCESS_WRITE
            );
            $possibleOwnerships->setOffset($folder->getId(), $usersWithWritePerm);

            if (count($possibleOwnerships->getOffset($folder->getId())) == 0) {
                // if nobody else was returned, jam the session user in there.
                $possibleOwnerships->getOffset($folder->getId())->add($this->getUser());
            }
        }
        return $possibleOwnerships;
    }


    /**
     * This POST is the result of import stage 2 (ie only called during new-import)
     *
     * Receives Assessment ownership selections.
     * Returns a HTML fragment containing a list of unit implementations.
     *
     * @api   POST /course/import/ownership
     * @level 160
     */
    public function postOwnership()
    {
        $ownerships = $this->getRequest()->post["ownership"];
        $courseSpecId = trim($this->getRequest()->post["course_spec_id"]);
        $componentId = trim($this->getRequest()->post["component_id"]);

        // build implementation objects from the specs
        $unitSpecs = $this->getUnitSpecificationMapper()->findAllByCourseId($courseSpecId);
        $unitImpls = new Collection();
        foreach ($unitSpecs as $spec) {
            $unitImpls->add((new UnitImplementation())
                ->setTitle($spec->getFolder()->getName())
                ->setSpecification($spec));
        }

        // process ownership assignments
        // respond with stage 3: unit selection
        $this->tpl('unitImportForm', [
            'action'       => 'import',
            'courseSpecId' => $courseSpecId,
            'componentId'  => $componentId,
            'unitsImpls'   => $unitImpls,
            'ownerships'   => implode(',', array_map(
                    function ($k, $v) {
                        return "$k:$v";
                    }, array_keys($ownerships), array_values($ownerships))
            )
        ]);
    }


    /**
     * Search function. Retrieve a list of Folders that match the string in $_GET['query']
     *
     * Searches by both name and class code
     *
     * @api   GET /course/import/classes
     * @level 160
     */
    public function searchClasses()
    {
        $query = $this->getRequest()->query->get('query');
        $userGroupsOnly = false;
        $getCourse = true;
        $getUnit = true;
        $folders = $this->getFolderMapper()->searchByName($query, $userGroupsOnly, $getCourse, $getUnit);

        /* Swap keys for selectize compatibility -_-
         */
        $data = [];

        /** @var Folder[] $folders */
        foreach ($folders as $folder) {

            // Ignore folders with CourseSpecs
            if ($folder->getCourseSpecification() instanceof CourseSpecification) {
                continue;
            }

            // Ignore folders with UnitSpecs
            if ($folder->getUnitSpecification() instanceof UnitSpecification) {
                continue;
            }

            // Make sure the user has access to the folder before we return it
            if ($this->getAccess()->getFolderAccess($this->getUser(), [$folder->getId()]) < UserAccess::ACCESS_WRITE) {
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
     * @level 160
     *
     * @var $_POST   $post
     * @var string   $post['action']                {'import'|'modify'}
     * @var int      $post['course_spec_id']
     * @var int      $post['course_impl_id']
     * @var int      $post['component_id']
     * @var string   $post['ownerships']
     * @var array    $post['unit']
     * @var int      $post['unit'][]['unit_spec_id']
     * @var int|null $post['unit'][]['unit_impl_id']
     * @var string   $post['unit'][]['open_date']
     * @var string   $post['unit'][]['close_date']
     *
     * @throws Unauthorized
     **/
    public function postUnits()
    {
        $post = $this->getRequest()->post;

        $action = trim($post['action']);
        $courseSpecId = trim($post['course_spec_id']);
        $courseImplId = trim($post['course_impl_id']);
        $componentId = trim($post['component_id']);
        $ownerships = explode(',', $post['ownerships']);

        $units = $post["unit"];

        if (empty($ownerships)) {
            // The homepage IDs have gone missing
            $this->sendError('No class code was specified');
            return;
        }

        if (empty($courseSpecId)) {
            // The course homepage ID has gone missing
            $this->sendError('No course was specified');
            return;
        }

        // Test permissions. Must have write permission on the destination page and read permission
        // on the course we are trying to import.

        // Test course page read access
        $courseSpec = $this->getCourseSpecificationMapper()->findById($courseSpecId);
        if ($this->getAccess()->getFolderAccess($this->getUser(), [$courseSpec->getFolder()->getId()]) < READ_ACCESS) {
            $this->sendError('You do not have permission to import this course');
        }

        // Test each homepage for write access
        foreach ($ownerships as $ownership) {
            $homepage = explode(':', $ownership)[0];
            if ($this->getAccess()->getFolderAccess($this->getUser(), [$homepage]) < WRITE_ACCESS) {
                $this->sendError('You do not have permission to import this unit');
            }
        }

        // Hydrate + Validate dates
        try {
            $countImportedUnits = 0;
            foreach ($units as $idx => $unit) {
                if ($unit['import']) {
                    $countImportedUnits++;
                    $openDate = \Alaress_Date_Format::newLocalShort($unit['open_date']);
                    $closeDate = \Alaress_Date_Format::newLocalShort($unit['close_date']);
                    if ($openDate->gt($closeDate)) {
                        $this->sendError('The open date must be before the close date');
                        return;
                    }
                    $units[$idx]['open_date'] = $openDate;
                    $units[$idx]['close_date'] = $closeDate;
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


        $importFactory = $this->getImportFactory();
        $unitImpls = new GenericCollection(UnitImplementation::class);
        foreach ($units as $unit) {
            if ($unit['import']) {
                $unitImpl = new UnitImplementation();
                $unitImpl->setId($unit['unit_impl_id']); // ID will be null if UnitSpec not yet imported
                $unitSpec = new UnitSpecification();
                $unitSpec->setId($unit['unit_spec_id']);
                $unitImpl->setSpecification($unitSpec);
                $unitImpl->setOpenDate($unit['open_date']);
                $unitImpl->setDueDate($unit['close_date']);
                $unitImpls->add($unitImpl);
            }
        }

        if ('import' == $action) {
            $dto = $importFactory->buildForNewImport($unitImpls);
        } elseif ('modify' == $action) {
            $dto = $importFactory->buildForImportModify($unitImpls);
        } else {
            throw new \DomainException('Unable to determine if this is a new import or modification of prior import.');
        }

        // respond with stage 4: coursework list
        $this->tpl('unitImportResults', [
            'action'       => $action,
            'courseSpecId' => $courseSpecId,
            'courseImplId' => $courseImplId,
            'componentId'  => $componentId,
            'ownerships'   => $post['ownerships'],
            'dto'          => $dto,
        ]);
    }


    /**
     * This POST is the result of import stage 4 (final). Do the actual import
     * and create all relevant database entries.
     *
     * @api   POST /course/import/activities
     * @level 160
     * @var $_POST   $post
     * @var string   $post['action']                                           {'import' | 'modify'}
     * @var int      $post['course_spec_id']
     * @var int|null $post['course_impl_id']
     * @var int|null $post['component_id']
     * @var string   $post['ownerships']
     * @var array    $post['unit']  Indexed by unitSpec.id
     * @var int      $post['unit'][unitSpecId]['unit_spec_id']
     * @var int|null $post['unit'][unitSpecId]['unit_impl_id']
     * @var string   $post['unit'][unitSpecId]['open_date']                    UnitImpl's Open Date
     * @var string   $post['unit'][unitSpecId]['close_date']                   UnitImpl's Close Date
     * @var array    $post['unit'][unitSpecId]['a']                            UnitImpl's Indexed by Assessment.id
     * @var int      $post['unit'][unitSpecId]['a'][assId]['specId']
     * @var int      $post['unit'][unitSpecId]['a'][assId]['implId']
     * @var bool     $post['unit'][unitSpecId]['a'][assId]['checked']
     * @var string   $post['unit'][unitSpecId]['a'][assId]['dates']['publish']
     * @var string   $post['unit'][unitSpecId]['a'][assId]['dates']['open']
     * @var string   $post['unit'][unitSpecId]['a'][assId]['dates']['due']
     * @var string   $post['unit'][unitSpecId]['a'][assId]['dates']['date']
     */
    public function postAssessments()
    {
        // Parse form data
        $post = $this->getRequest()->post;
        $action = trim($post['action']);
        $courseSpecId = trim($post['course_spec_id']);
        $courseImplId = trim($post['course_impl_id']);
        $componentId = trim($post['component_id']);
        $ownerships = trim($post['ownerships']);
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
        //       4.1.2 save entity


        // On new-import, we may be creating multiple courseImpls.
        // Therefore store in array and index by ClassFolderId
        /** @var CourseImplementation[] $courseImpls */
        $courseImpls = [];

        // Are we creating a new import?
        if ('import' == $action) {
            $courseSpec = $this->getCourseSpecificationMapper()->findById($courseSpecId);
            $ownerships = explode(',', $ownerships);
            foreach ($ownerships as $ownership) {
                $courseImpl = $this->getCourseImplementationMapper()->createModel();
                $courseImpl->setSpecification($courseSpec);
                $classFolderId = explode(':', $ownership)[0];
                $courseImpl->setFolder($this->folderMapper->findById($classFolderId));
                $courseImpls[$classFolderId] = $courseImpl;
            }
        } // Else we are modifying an existing import
        else {
            // We can only modify a single Course Import at a time.
            $courseImpl = $this->getCourseImplementationMapper()->findById($courseImplId);
            $courseImpls[$courseImpl->getFolder()->getId()] = $courseImpl;

            // Load UnitImplementations
            $courseImpl->setUnits($this->getUnitImplementationMapper()->findAllByCourseId($courseImpl->getId()));

            // Load IAssessables
            foreach ($courseImpl->getUnits() as $unitImpl) {
                $unitImpl->setAssessables($this->getAssessmentMapperProxy()->findAllByUnitImplementationId($unitImpl->getId()));
            }
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
                    $this->getUnitImplementationMapper()->delete($unitImpl);
                } // Else UnitImpl still selected
                else {
                    // Delete any Assessments in the UnitImpl if de-selected by user
                    foreach ($unitImpl->getAssessables() as $assessableImpl) {

                        $assessmentImplData = $unitImplData[$unitSpec->getId()]['a'][$assessableImpl->getId()];
                        // Is this Assessment a impl or spec? (we ignore specs, nothing to delete)
                        if ($assessmentImplData['impl_id']) {
                            // Did user de-select this Assessment ?
                            if (!$assessmentImplData['checked']) {
                                $this->getAssessmentMapperProxy()->delete($assessableImpl);
                            }
                        }
                    }
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
                $this->getCourseImplementationMapper()->save($courseImpl);

                // For each UnitImpl in POST data
                foreach ($unitImplData as $unitPair) {
                    // Retrieve UnitSpec from DB (even if deleted)
                    try {
                        $unitSpec = $this->getUnitSpecificationMapper()->findById($unitPair['unit_spec_id']);
                    } catch (MapperException\Deleted $e) {
                        $unitSpec = $e->getDeletedObject();
                    }
                    $unitImplOpenDate = \Alaress_Date_Format::newLocalShort($unitPair['open_date']);
                    $unitImplCloseDate = \Alaress_Date_Format::newLocalShort($unitPair['close_date']);

                    // Does the CourseImpl already contain this Unit ?
                    $unitSpecId = $unitSpec->getId();
                    $callback = function ($unitImpl) use ($unitSpecId) {
                        /** @var UnitImplementation $unitImpl */
                        return $unitSpecId == $unitImpl->getSpecification()->getId();
                    };
                    /** @var UnitImplementation $unitImpl */
                    $unitImpl = $courseImpl->getUnits()->filter($callback)->first();

                    // Did we already delete this UnitImpl ?
                    if ($unitImpl && $unitImpl->isDeleted()) {
                        continue;
                    }

                    // If the CourseImpl does not yet contain this UnitSpec
                    if (!$unitImpl) {
                        // Create a UnitImpl from this UnitSpec
                        $unitImpl = $this->getUnitImplementationMapper()->createModel();
                        $unitImpl->setSpecification($unitSpec);
                        $unitImpl->setCourse($courseImpl);
                        $unitImpl->setTitle($unitSpec->getFolder()->getTitle());
                        $courseImpl->getUnits()->add($unitImpl);
                    }
                    $unitImpl->setOpenDate($unitImplOpenDate);
                    $unitImpl->setDueDate($unitImplCloseDate);
                    $this->getUnitImplementationMapper()->save($unitImpl);

                    // Create/Update AssessmentImpls
                    $sequence = 1;
                    foreach ($unitPair['a'] as $assessmentId => $aData) {
                        // Ignore unchecked items. ( If they were already imported, they were deleted above.)
                        if (!$aData['checked']) {
                            continue;
                        }

                        $assessmentMapperProxy = $this->getAssessmentMapperProxy();

                        // Get Spec if cloned, or null if AssessmentImpl created in UnitImpl
                        $assessmentSpec = $aData['spec_id'] ? $assessmentMapperProxy->findById($aData['spec_id']) : null;

                        // Is this a creation from the spec ?
                        if (!$aData['impl_id']) {
                            $assessmentImpl = $assessmentSpec->cloneForCopy();
                            // TODO need to implement __clone() on DueWork to clone Rubric correctly
                            // TODO unify unit implementation data and domain model so this shenanigan isn't required
                            $assessmentImpl->setContext(
                                (new Course\Unit\Implementation())->setId($unitImpl->getId())
                            );
                            $unitImpl->getAssessables()->add($assessmentImpl);
                        } // Else we have been passed the Assessment Impl
                        else {
                            // Retrieve AssessmentImpl from UnitImpl
                            $filter = function ($impl) use ($assessmentId) {
                                /** @var IAssessable $impl */
                                return $impl->getId() == $assessmentId;
                            };
                            $assessmentImpl = $unitImpl->getAssessables()->filter($filter)->first();

                        }

                        // Don't forget to set the spec assessment on the impl
                        $assessmentImpl->setSpecificationAssessment($assessmentSpec);

                        // Set/Update Dates
                        foreach ($aData['dates'] as $property => $localShort) {
                            $method = "set{$property}At";
                            if (!method_exists($assessmentImpl, $method)) {
                                $this->sendError("Invalid date property: $property");
                                return;
                            }
                            $assessmentImpl->$method(\Alaress_Date_Format::newLocalShort($localShort));
                        }
                        // Save Dates and SubObject (but this blows away Impl to Spec link!!)
                        $assessmentMapperProxy->save($assessmentImpl, $sequence++);
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
                if ('import' == $action) {
                    $componentInstance = null;
                    $compInstanceMapper = $this->getComponentInstanceMapper();
                    // Was a componentId supplied?
                    if ($componentId) {
                        $componentInstance = $compInstanceMapper->findById($componentId);
                    } else {
                        $componentInstance= $this->findAvailableCourseOutlineComponent($classFolderId);
                    }
                    $this->injectImportIntoComponent($courseImpl, $componentInstance);
                }
            }
        } catch (\PDOException $e) {
            $this->sendError($e->getMessage());
            return;
        }



        /* Redirect back to wherever you came from...
         * ...hopefully (...your confidence is inspiring)
         */
        $explicitReturnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL);
        if($explicitReturnUrl){
            $redirect = $explicitReturnUrl;
        } else {
            $redirect = $this->getSession()->getHistory(2);
        }
        $this->getResponse()->content->set($this->buildJsonOutput(['redirect' => $redirect]));
    }

    /**
     * Attempts to find a Course Outline ComponentInstance on the specifed page
     * that is not currently linked to a CourseImport.
     *
     * @param int $folderId
     * @return Component_Instance|null
     */
    protected function findAvailableCourseOutlineComponent($folderId)
    {
        $module = \Component_Definition::MODULE_HOMEPAGE;
        $class = HomepageOutlineComponentController::class;
        $compInstances = $this->getComponentInstanceMapper()->findByContextAndClass($module, $folderId, $class);
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
     * @param CourseImplementation $courseImpl
     * @param Component_Instance|null $compInstance
     * @return bool
     */
    protected function injectImportIntoComponent(CourseImplementation $courseImpl, Component_Instance $compInstance = null)
    {
        // Did we find an available component?
        if ($compInstance) {
            // Find its 'importedCourse' config
            /** @var Component_ConfigDefinition $configDefinition */
            $configDefinition = null;
            foreach($compInstance->getConfig() as $configItem){
                if('importedCourse' == $configItem->getKey()){
                    $configDefinition = $configItem;
                    break;
                }
            }
            if(!$configDefinition){
                $configDefinition = $this->getComponentConfigDefinitionMapper()->findByColumnList([
                    'component_definition_id' => $compInstance->getDefinitionId(),
                    'key'                     => 'importedCourse',
                ])->first();
                $configDefinition->setKey('importedCourse');
            }
            $configInstance = $this->getComponentConfigInstanceMapper()->findByColumnList([
                'component_config_definition_id' => $configDefinition->getId(),
                'component_instance_id'          => $compInstance->getId(),
            ])->first();
            if(!$configInstance){
                /** @var \Component_Config $configInstance */
                $configInstance = $this->getComponentConfigInstanceMapper()->createModel();
                $configInstance->setConfigDefinitionId($configDefinition->getId());
                $configInstance->setInstanceId($compInstance->getId());
            }
            $configInstance->setValue($courseImpl->getId());
            $this->getComponentConfigInstanceMapper()->save($configInstance);
        } // Else no available component, create one.
        else {
            $folder = $courseImpl->getFolder();
            $configDefinition = ['importedCourse' => $courseImpl->getId()];
            $courseOutlineClass = HomepageOutlineComponentController::class;
            $this->getComponentInstanceMapper()->injectNewComponent($folder, $configDefinition, $courseOutlineClass);
        }
        return true;
    }

    /**
     * This GET provides the import form, stepped in at stage 3 (since target and ownership cannot be changed)
     * Modify the Unit information for a previously imported Course
     *
     * @api   GET /course/import/{courseImplId}/modify
     * @level 165
     *
     * @param CourseImplementation $courseImpl
     * @throws MapperException
     */
    public function importModify(CourseImplementation $courseImpl)
    {
        $courseSpec = $this->courseSpecificationMapper->findById($courseImpl->getSpecification()->getId());
        $courseSpec->setUnits($this->getUnitSpecificationMapper()->findAllByCourseId($courseSpec->getId()));

        // Load UnitImpls
        $courseImpl->setUnits($this->getUnitImplementationMapper()->findAllByCourseId($courseImpl->getId()));

        // Add un-imported UnitSpecs to CourseImpl
        foreach ($courseSpec->getUnits() as $unitSpec) {
            // Has this UnitSpec been imported?
            foreach ($courseImpl->getUnits() as $unitImpl) {
                if ($unitImpl->getSpecification()->getId() == $unitSpec->getId()) {
                    // unitSpec already imported, do nothing
                    continue 2;
                }
            }
            // unitSpec not imported, add it to CourseImpl as available for import
            $unitImpl = new UnitImplementation();
            $unitImpl->setSpecification($unitSpec);
            $unitImpl->setTitle($unitSpec->getFolder()->getName());
            $courseImpl->getUnits()->add($unitImpl);
        }

        // Re-order UnitImpls alphabetically
        $courseImpl->setUnits($courseImpl->getUnits()->sortBy('title', 'ASC'));


        // Find the owner of any Assessment so we can display, even though it is disabled
        $owner = null;
        try {
            foreach ($courseImpl->getUnits() as $unitImpl) {
                $assessables = $this->getAssessmentMapperProxy()->findAllByUnitImplementationId($unitImpl->getId());
                foreach ($assessables as $assessable) {
                    $owner = $this->getUserMapper()->findById($assessable->getCreator()->getId());
                    break 2;
                }
            }
        } catch (MapperException $e) {
            // noop
        }
        if (!$owner) {
            $owner = $this->getUser();
        }

        $this->tpl('import', [
            'action'              => 'modify',
            'initialStage'        => self::IMPORT_STAGE_UNITS,
            'courseSpec'          => $courseSpec,
            'courseSpecId'        => $courseSpec->getId(),
            'courseImpl'          => $courseImpl,
            'folder'              => $courseImpl->getFolder(),
            'destinations'        => [
                'id'   => $courseImpl->getFolder()->getId(),
                'name' => $courseImpl->getFolder()->getTitle()
            ],
            'ownershipCandidates' => [
                $courseImpl->getFolder()->getId() => [
                    $owner,
                ],
            ],
            'unitsImpls'          => $courseImpl->getUnits(),
            'ownerships'          => "{$courseImpl->getFolder()->getId()}:{$owner->getId()}",
        ]);
    }

    /**
     * View a list of courses associated with a homepage
     *
     * @api   GET /course/import/management/{folderId}
     * @level 165
     *
     * @param int $folderId
     */
    public function getManagement($folderId)
    {
        /* Find all the Courses associated with this Folder, via imported Units
         */
        $courseImpls = $this->getCourseImplementationMapper()->findByFolderId($folderId);

        // Short cut if possible.
        $folder = $courseImpls->count()
            ? $courseImpls->first()->getFolder()
            : $this->getFolderMapper()->findById($folderId);

        $module = \Component_Definition::MODULE_HOMEPAGE;
        $class = HomepageOutlineComponentController::class;
        $components = $this->getComponentInstanceMapper()->findByContextAndClass($module, $folder->getId(), $class);

        // For each CourseImpl, determine how many components are displaying it on the homepage
        $componentCounts = [];
        foreach ($courseImpls as $courseImpl) {
            $componentCounts[$courseImpl->getId()] = 0;
        }

        foreach ($components as $component) {
            $courseImplId = $component->getConfigArray()['importedCourse']['value'];
            if ($courseImplId) {
                $componentCounts[$courseImplId]++;
            }
        }

        $this->tpl('management', [
            'folder'          => $folder,
            'courseImpls'     => $courseImpls,
            'componentCounts' => $componentCounts,
        ]);
    }


    /**
     * Deletes a Course import, along with ALL of its associated data
     *
     * @api   DELETE /course/import/{courseImplId}
     * @level 165
     * @param CourseImplementation $courseImpl
     */
    public function importDelete(CourseImplementation $courseImpl)
    {
        if (!$courseImpl instanceof CourseImplementation) {
            $this->sendError('Course Import not found');
            return;
        }

        // Will delete subsidiary Units, Assessments, Responses + Feedbacks
        if (!$this->getCourseImplementationMapper()->delete($courseImpl)) {
            $this->sendError('Unable to delete Course Import');
            return;
        }

        // Delete any CourseOutline components that display this CourseImport
        try {
            $module = \Component_Definition::MODULE_HOMEPAGE;
            $folderId = $courseImpl->getFolder()->getId();
            $class = HomepageOutlineComponentController::class;
            $components = $this->getComponentInstanceMapper()->findByContextAndClass($module, $folderId, $class);
            $componentsForCourse = $components->filter(function ($componentInstance) use ($courseImpl) {
                /** @var Component_Instance $componentInstance */
                return $courseImpl->getId() == $componentInstance->getConfigArray()['importedCourse']['value'];
            });
            $this->getComponentInstanceMapper()->delete($componentsForCourse);

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
     * @level 165
     * @param CourseImplementation $courseImpl
     */
    public function restoreCourse(CourseImplementation $courseImpl)
    {
        $componentInstance = $this->findAvailableCourseOutlineComponent($courseImpl->getFolder()->getId());
        $this->injectImportIntoComponent($courseImpl, $componentInstance);
        $this->getResponse()->content->set($this->buildJsonOutput(['result' => 'success']));
    }


    /**
     * @return UnitSpecificationMapper
     */
    public function getUnitSpecificationMapper()
    {
        return $this->unitSpecificationMapper;
    }

    /**
     * @param UnitSpecificationMapper $unitSpecificationMapper
     * @return static
     */
    public function setUnitSpecificationMapper(UnitSpecificationMapper $unitSpecificationMapper)
    {
        $this->unitSpecificationMapper = $unitSpecificationMapper;
        return $this;
    }

    /**
     * @return UnitImplementationMapper
     */
    public function getUnitImplementationMapper()
    {
        return $this->unitImplementationMapper;
    }

    /**
     * @param UnitImplementationMapper $unitImplementationMapper
     * @return static
     */
    public function setUnitImplementationMapper(UnitImplementationMapper $unitImplementationMapper)
    {
        $this->unitImplementationMapper = $unitImplementationMapper;
        return $this;
    }

    /**
     * Get folder mapper
     *
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * Set folder mapper
     *
     * @param FolderMapper $folderMapper
     */
    public function setFolderMapper(FolderMapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }


    /**
     * @return CourseSpecificationMapper
     */
    public function getCourseSpecificationMapper()
    {
        return $this->courseSpecificationMapper;
    }

    /**
     * @param CourseSpecificationMapper $courseSpecificationMapper
     * @return static
     */
    public function setCourseSpecificationMapper(CourseSpecificationMapper $courseSpecificationMapper)
    {
        $this->courseSpecificationMapper = $courseSpecificationMapper;
        return $this;
    }

    /**
     * @return UserAccessMapper
     */
    protected function getUserAccessMapper()
    {
        return $this->userAccessMapper;
    }

    /**
     * @param UserAccessMapper $userAccessMapper
     * @return static
     */
    public function setUserAccessMapper(UserAccessMapper $userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
        return $this;
    }

    /**
     * @return Access
     */
    protected function getAccess()
    {
        return $this->access;
    }


    /**
     * @param Access $access
     * @return static
     */
    public function setAccess(Access $access)
    {
        $this->access = $access;
        return $this;
    }


    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }


    /**
     * @param UserMapper $userMapper
     * @return static
     */
    public function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return AssessmentMapper
     */
    public function getAssessmentMapper()
    {
        return $this->assessmentMapper;
    }


    /**
     * @param AssessmentMapper $assessmentMapper
     * @return static
     */
    public function setAssessmentMapper(AssessmentMapper $assessmentMapper)
    {
        $this->assessmentMapper = $assessmentMapper;
        return $this;
    }


    /**
     * @return AssessmentMapperProxy
     */
    public function getAssessmentMapperProxy()
    {
        return $this->assessmentMapperProxy;
    }


    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @return static
     */
    public function setAssessmentMapperProxy(AssessmentMapperProxy $assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        return $this;
    }


    /**
     * @return Course\ImplementationFactory
     */
    public function getCourseImplementationFactory()
    {
        return $this->courseImplementationFactory;
    }


    /**
     * @param Course\ImplementationFactory $unitImplementationFactory
     * @return static
     */
    public function setCourseImplementationFactory(Course\ImplementationFactory $unitImplementationFactory)
    {
        $this->courseImplementationFactory = $unitImplementationFactory;
        return $this;
    }


    /**
     * @return CourseImplementationMapper
     */
    public function getCourseImplementationMapper()
    {
        return $this->courseImplementationMapper;
    }


    /**
     * @param CourseImplementationMapper $courseImplementationMapper
     * @return static
     */
    public function setCourseImplementationMapper(CourseImplementationMapper $courseImplementationMapper)
    {
        $this->courseImplementationMapper = $courseImplementationMapper;
        return $this;
    }

    /**
     * Get component instance mapper
     *
     * @return \Component_DefinitionMapper
     */
    protected function getComponentDefinitionMapper()
    {
        return $this->componentDefinitionMapper;
    }

    /**
     * Set component instance mapper
     *
     * @param \Component_DefinitionMapper $componentDefinitionMapper
     */
    public function setComponentDefinitionMapper($componentDefinitionMapper)
    {
        $this->componentDefinitionMapper = $componentDefinitionMapper;
    }

    /**
     * Get component instance mapper
     *
     * @return \Component_InstanceMapper
     */
    protected function getComponentInstanceMapper()
    {
        return $this->componentInstanceMapper;
    }

    /**
     * Set component instance mapper
     *
     * @param \Component_InstanceMapper $componentInstanceMapper
     */
    public function setComponentInstanceMapper($componentInstanceMapper)
    {
        $this->componentInstanceMapper = $componentInstanceMapper;
    }

    /**
     * @return ImportFactory
     */
    public function getImportFactory()
    {
        return $this->importFactory;
    }

    /**
     * @param ImportFactory $importFactory
     * @return static
     */
    public function setImportFactory(ImportFactory $importFactory)
    {
        $this->importFactory = $importFactory;
        return $this;
    }

    /**
     * @return Component_ConfigDefinitionMapper
     */
    public function getComponentConfigDefinitionMapper()
    {
        return $this->componentConfigDefinitionMapper;
    }

    /**
     * @param Component_ConfigDefinitionMapper $componentConfigDefinitionMapper
     * @return static
     */
    public function setComponentConfigDefinitionMapper(Component_ConfigDefinitionMapper $componentConfigDefinitionMapper)
    {
        $this->componentConfigDefinitionMapper = $componentConfigDefinitionMapper;
        return $this;
    }

    /**
     * @return Component_ConfigMapper
     */
    public function getComponentConfigInstanceMapper()
    {
        return $this->componentConfigInstanceMapper;
    }

    /**
     * @param Component_ConfigMapper $componentConfigInstanceMapper
     * @return static
     */
    public function setComponentConfigInstanceMapper(Component_ConfigMapper $componentConfigInstanceMapper)
    {
        $this->componentConfigInstanceMapper = $componentConfigInstanceMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    public function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }
}
