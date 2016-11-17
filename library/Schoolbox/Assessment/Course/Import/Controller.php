<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Alaress\Web\Controller as Base;
use Alaress\Authorizer\Failure;
use Alaress\Authorizer\Failure\Unauthorized;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\Unit;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\Config;
use Schoolbox\Resource;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Assessment\IAssessable;

class Controller extends Base\Base
{
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

    /** @var Assessment\DataModel\MapperProxy */
    private $assessmentMapper;

    /** @var Course\ImplementationFactory */
    private $courseImplementationFactory;

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
        if(!$componentId){
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
        $courseOutlineComponent = null;
        if($componentId){
            try{
                $courseOutlineComponent = \Component_InstanceMapper::getInstance()->findById($componentId);
                $COURSE_OUTLINE_DEFINITION_ID = 66;
                if($courseOutlineComponent->getDefinitionId() != $COURSE_OUTLINE_DEFINITION_ID){
                    $msg = 'Not a Course-Outline component';
                    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND, null, $msg);
                }
            } catch (MapperException $e){
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
            }
        }

        $initialStage = $courseSpec && $targetClass ? 2 : 1;

        $context = [
            'action'       => 'import',
            'initialStage' => $initialStage,
            'courseSpec'   => $courseSpec,
            'componentId'  => $courseOutlineComponent ? $courseOutlineComponent->getId() : null,
            'folder'       => $targetClass,
            'destinations' => $targetClass ?
                [
                    'id'   => $targetClass->getId(),
                    'name' => $targetClass->getTitle()
                ] : [],
            'ownershipCandidates' => 2 == $initialStage ? $this->getPossibleOwnerships([$targetClass]) : [],
        ];

        $this->tpl('import', $context);
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
                    function ($k, $v) { return "$k:$v"; }, array_keys($ownerships), array_values($ownerships))
            )
        ]);
    }


    /**
     * Search function. Retrieve a list of Folders that match the string in $_GET['query']
     *
     * @api   GET /course/import/classes
     * @level 160
     */
    public function searchClasses()
    {
        $importer = new Import();

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => $importer->findMatchingClasses($this->getRequest()->query->get('query'))
        ]);
    }


    /**
     * This POST is the result of import stage 3.
     *
     * Returns a HTML fragment containing a list of Assessments, grouped by UnitImpl
     *
     * @api   POST /course/import/units
     * @level 160
     *
     * @var $_POST $post
     * @var string $post['action']   {'import'|'modify'}
     * @var int $post['course_spec_id']
     * @var int $post['course_impl_id']
     * @var int $post['component_id']
     * @var string $post['ownerships']
     * @var array $post['unit']
     * @var int $post['unit'][]['unit_spec_id']
     * @var int|null $post['unit'][]['unit_impl_id']
     * @var string $post['unit'][]['open_date']
     * @var string $post['unit'][]['close_date']
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
            foreach($units as $idx => $unit){
                if($unit['import']){
                    $countImportedUnits++;
                    $openDate = \Alaress_Date_Format::newLocalShort($unit['open_date']);
                    $closeDate  = \Alaress_Date_Format::newLocalShort($unit['close_date']);
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


        $factory = new ImportFactory();
        $args = [];
        if('import' == $action) {
            $args['unitSpecs'] = [];
            foreach ($units as $unit) {
                if ($unit['import']) {
                    $args['unitSpecs'][$unit['unit_spec_id']] = [
                        'unitSpecId' => $unit['unit_spec_id'],
                        'openDate'   => $unit['open_date'],
                        'closeDate'  => $unit['close_date'],
                    ];
                }
            }
        } elseif('modify' == $action){
            // TODO
            $unitAssessments = $factory->build(['courseImplId' => $courseImplId]);
        } else {
            throw new \DomainException('Unable to determine if this is a new import or modification of prior import.');
        }
        $unitAssessments = $factory->build($args);



        // respond with stage 4: coursework list
        $this->tpl('unitImportResults', [
            'action'          => $action,
            'courseSpecId'    => $courseSpecId,
            'courseImplId'    => $courseImplId,
            'componentId'     => $componentId,
            'ownerships'      => $post['ownerships'],
            'unitAssessments' => $unitAssessments,
        ]);
    }


    /**
     * TODO refactor to new object graph
     *
     * This POST is the result of import stage 4 (final). Do the actual import
     * and create all relevant database entries.
     *
     * @api   POST /course/import/tasks
     * @level 160
     * @var $_POST   $post
     * @var string   $post['action']                                   {'import' | 'modify'}
     * @var int      $post['course_spec_id']
     * @var int|null $post['course_impl_id']
     * @var int|null $post['component_id']
     * @var string   $post['ownerships']
     * @var array    $post['unit']  Indexed by unitSpec.id
     * @var int      $post['unit'][unitSpecId]['unit_spec_id']
     * @var int|null $post['unit'][unitSpecId]['unit_impl_id']
     * @var string   $post['unit'][unitSpecId]['open_date']            UnitImpl's Open Date
     * @var string   $post['unit'][unitSpecId]['close_date']           UnitImpl's Close Date
     * @var array    $post['unit'][unitSpecId]['a']                    UnitImpl's Indexed by Assessment.id
     * @var string   $post['unit'][unitSpecId]['a'][assId]['type']     {'spec' | 'impl'}
     * @var bool     $post['unit'][unitSpecId]['a'][assId]['checked']
     * @var string   $post['unit'][unitSpecId]['a'][assId]['publish']
     * @var string   $post['unit'][unitSpecId]['a'][assId]['open']
     * @var string   $post['unit'][unitSpecId]['a'][assId]['due']
     * @var string   $post['unit'][unitSpecId]['a'][assId]['date']
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
        $unitPairs = $post['unit'];

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
        //       4.1. 2 save entity

        // Are we creating a new import or modifying an existing?
        if('import'){
            $dbCourseImpl = new \Schoolbox\Learning\Course\DataModel\Implementation\Model();
        }


        $courseSpec = $this->getCourseSpecificationMapper()->findById($post['course_homepage'])->setUnits(new Collection()); // TODO what the actual fuck

        foreach ($post['unit'] as $unitSpecId => $unitPost) {
//          $unitImplId = $unitPost['impl_id'];

            try {
                // cache the spec
                $unitSpec = $this->getUnitSpecificationMapper()->findById($unitSpecId);

                // TODO in import-modify context, support overlay of implementation assessments over spec

            } catch (MapperException $e) {
                $this->sendError("Could not import non-existent unit [spec $unitSpecId]");
                return;
            }

            foreach ($unitPost['a'] as $assId => $assDates) {
                // get the assessment
                $a = $this->getAssessmentMapper()->findById($assId);

                // locate its unit in the cache
                $ctxId = $a->getContext()->getId();

                // verify the context matches what we were given in post
                if ($ctxId != $unitSpec->getId()) {
                    $this->sendError("Trying to import assessment $assId from incorrect unit spec $ctxId");
                    return;
                }

                // set dates
                foreach ($assDates as $property => $localShort) {
                    $method = "set{$property}at";
                    if (!method_exists($a, $method)) {
                        $this->sendError("Invalid date property: $property");
                        return;
                    }
                    // eeeeeeeiwwwww
                    $a->$method(\Alaress_Date_Format::newLocalShort($localShort));
                }

                // attach assessment to spec
                $unitSpec->getAssessables()->add($a);
            }

            // load the unit spec into our course spec
            $courseSpec->getUnits()->add($unitSpec);
        }

        // course is full loaded
        $ships = array_map(function ($v) { return explode(':', $v); }, explode(',', $post['ownerships']));
        $ships = array_combine(array_column($ships, 0), array_column($ships, 1));

        /** @var Course\Implementation[]|Collection $courseImplementations */
        $courseImplementations = new Collection();

        foreach ($ships as $folderId => $newOwnerId) {

            // convert the fully-loaded course spec into an implementation for this class
            $courseImpl = $this->getCourseImplementationFactory()->build($courseSpec, $this->getFolderMapper()->findById($folderId));

            // assign the new owner to every assessment
            foreach ($courseImpl->getUnits() as $unit) {

                // locate the dates provided in post and assign to this now-created implementation
                $unitSpecId = $unit->getSpecification()->getId();

                if (empty($post['unit'][$unitSpecId])) {
                    $this->sendError("No dates provided for unit spec: $unitSpecId");
                    return;
                }

                $dates = $post['unit'][$unitSpecId];

                $unit->setOpenDate(\Alaress_Date_Format::newLocalShort($dates['open']));
                $unit->setDueDate(\Alaress_Date_Format::newLocalShort($dates['due']));

                foreach ($unit->getAssessables() as $a) {
                    $a->setCreator($this->getUserMapper()->findById($newOwnerId));
                }
            }

            $courseImplementations->add($courseImpl);
        }

        try {
            // aaaaaand, into the DB with you!
            foreach ($courseImplementations as $ci) {
                $this->getCourseImplementationMapper()->save($ci);

                // save the assessments inside
                foreach ($ci->getUnits() as $unit) {
                    foreach ($unit->getAssessables() as $seq => $a) {
                        $this->getAssessmentMapper()->save($a, $seq + 1);
                    }
                }

                // if that all worked, spawn a component onto the page
                \Component_InstanceMapper::getInstance()->injectNewComponent($ci->getFolder(), [ 'importedCourse' => $ci->getId() ]);
            }
        } catch (\PDOException $e) {
            $this->sendError($e->getMessage());
            return;
        }

        /* Redirect back to wherever you came from...
         * ...hopefully (...your confidence is inspiring)
         */
        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => [
                'redirect' => $this->getSession()->getHistory(2)
            ]
        ]);

        return;
    }

    /**
     * TODO refactor to new object graph
     *
     * This GET provides the import form, stepped in at stage 3 (since target and ownership cannot be changed)
     * Modify the Unit information for a previously imported Course
     *
     * @api   GET /course/import/{courseImplId}/modify
     * @level 165
     *
     * @param CourseImplementation $courseImplementation
     * @throws MapperException
     */
    public function importModify(CourseImplementation $courseImplementation)
    {
        $compendium = [];

        /** @var Component_Instance $compInst */
        $compInst = $this->getComponentInstanceMapper()->findById($componentId);

        /** @var Collection|UnitInstance[] $selectedUnitInstances */
        $selectedUnitInstances = $this->getUnitInstanceMapper()->findForComponent($compInst);

        /** @var Collection $tasks */
        $tasks = $selectedUnitInstances->first()->getTasks();
        if ($tasks->count() > 0) {
            $owner = $this->getUserMapper()->findById($tasks->first()->getOwner()->getId());
        } else {
            // no tasks in this unit: use the session user
            $owner = $this->getUser();
        }

        $course = null;
        foreach ($selectedUnitInstances as $selectedUnitInstance) {
            $compendium[$selectedUnitInstance->getUnitCategory()->getId()]['selected'] = $selectedUnitInstance;
            if (!$course instanceof CourseModel) {
                try {
                    $course = $this->getUnitInstanceMapper()->findCourseForUnit($selectedUnitInstance);
                } catch (MapperException $e) {
                    // This unit doesn't actually exist - get course from next one
                    continue;
                }
            }
        }

        // After testing every unit do I still not have a course? That's an error
        if (!$course instanceof CourseModel) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_RESOURCE_NOT_FOUND);
        }

        $allUnits = $course->getUnits();

        foreach ($allUnits as $unit) {
            $compendium[$unit->getId()]['folder'] = $unit;
        }

        $unitOutput = [];

        foreach ($compendium as $id => $values) {
            /** @var UnitInstance $selected */
            $selected = $values['selected'];
            /** @var Folder $folder */
            $folder = $values['folder'];
            $unitTitle = '';
            $checkable = false;
            if ($folder instanceof Folder) {
                $unitTitle = $folder->getName();
                $checkable = true;
            } elseif ($selected instanceof UnitInstance) {
                $unitTitle = $selected->getTitle();
            }

            $unitOutput[$id] = [
                'id' => $id,
                'name' => $unitTitle,
                'checkable' => $checkable,
            ];

            if (isset($values['selected'])) {
                $unitOutput[$id]['checked'] = true;
                $unitOutput[$id]['openDate'] = $selected->getOpenDate();
                $unitOutput[$id]['dueDate'] = $selected->getDueDate();
            }
        }

        /* Find the destination for selectize
         */
        $allComponents = $this->getUnitInstanceMapper()->findByColumn('component_id', $componentId);

        if ($allComponents->count()) {
            $folderId = $allComponents->first()->getCategory()->getId();
            $destination = $this->getFolderMapper()->findCheaplyById($folderId);
        } else {
            /* TODO: Error; component never existed
             */
            $destination = $this->getFolderMapper()->createModel();
        }

        $this->tpl('import', [
            '_part3' => true,
            'units' => $unitOutput,
            'folder' => $destination,
            'sources' => $course,
            'destinations' => array_combine(['id', 'name'], $destination->findValueLabelPair()),
            'homepage' => $destination->getId(),
            'courseHomepage' => $course->getId(),
            'componentId' => $componentId,
            'owner' => $owner
        ]);
    }


    /**
     * TODO refactor to new object graph
     *
     * Deletes an import, along with ALL of its associated data
     *
     * @api   POST /import/delete
     * @level 160
     */
    public function importDelete()
    {
        $post = $this->getRequest()->post;

        if (!isset($post['componentId'])) {
            $this->sendError('Component ID not defined');
            return;
        }

        $componentId = $post['componentId'];
        $requiresDelete = false;

        try {
            /** @var Component_Instance $componentInstance */
            $componentInstance = $this->getComponentInstanceMapper()->findById($componentId);
            $requiresDelete = true;
        } catch (MapperException $e) {
            // the component doesn't exist, so just fake it
            $componentInstance = $this->getComponentInstanceMapper()->createModel()->setId($componentId);
        }

        $unitInstances = $this->getUnitInstanceMapper()->findForComponent($componentInstance);

        try {
            $this->getUnitInstanceMapper()->delete($unitInstances);

            if ($requiresDelete) {
                $this->getComponentInstanceMapper()->delete($componentInstance);
            }

            $this->getResponse()->content->set([
                'format' => 'json',
                'data' => true
            ]);


        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), [], 500);
            return;
        }

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
     * @return Assessment\DataModel\MapperProxy
     */
    public function getAssessmentMapper()
    {
        return $this->assessmentMapper;
    }


    /**
     * @param Assessment\DataModel\MapperProxy $assessmentMapper
     * @return static
     */
    public function setAssessmentMapper(Assessment\DataModel\MapperProxy $assessmentMapper)
    {
        $this->assessmentMapper = $assessmentMapper;
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

}
