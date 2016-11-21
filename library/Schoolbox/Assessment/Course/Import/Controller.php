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

    /** @var AssessmentMapperProxy */
    private $assessmentMapperProxy;

    /** @var Course\ImplementationFactory */
    private $courseImplementationFactory;

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

        $this->tpl('import', [
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


        $importFactory = $this->getImportFactory();
        $unitImpls = [];
        foreach ($units as $unit) {
            if ($unit['import']) {
                $unitImpl = new UnitImplementation();
                $unitImpl->setId($unit['unit_impl_id']);
                $unitSpec = new UnitSpecification();
                $unitSpec->setId($unit['unit_spec_id']);
                $unitImpl->setSpecification($unitSpec);
                $unitImpl->setOpenDate($unit['open_date']);
                $unitImpl->setDueDate($unit['close_date']);
                $unitImpls[] = $unitImpl;
            }
        }

        if('import' == $action) {
            $dto = $importFactory->buildForNewImport($unitImpls);
        } elseif('modify' == $action){
            $dto = $importFactory->buildForImportModify($courseImplId, $unitImpls);
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
     * TODO refactor to new object graph
     *
     * This POST is the result of import stage 4 (final). Do the actual import
     * and create all relevant database entries.
     *
     * @api   POST /course/import/tasks
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
     * @var string   $post['unit'][unitSpecId]['a'][assId]['type']             {'spec' | 'impl'}
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
        $postedAction = trim($post['action']);
        $postedCourseSpecId = trim($post['course_spec_id']);
        $postedCourseImplId = trim($post['course_impl_id']);
        $postedComponentId = trim($post['component_id']);   // TODO - link to CourseOutline component
        $postedOwnerships = trim($post['ownerships']);
        $postedUnitImpls = $post['unit'];

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


        // On new-import, we may be creating multiple courseImpls.
        // Therefore store in array and index by ClassFolderId
        /** @var CourseImplementation[] $courseImpls */
        $courseImpls = [];

        // Are we creating a new import or modifying an existing?
        if('import' == $postedAction){
            $courseSpec = $this->getCourseSpecificationMapper()->findById($postedCourseSpecId);
            $postedOwnerships = explode(',', $postedOwnerships);
            foreach($postedOwnerships as $ownership){
                $courseImpl = $this->getCourseImplementationMapper()->createModel();
                $courseImpl->setSpecification($courseSpec);
                $classFolderId = explode(':',$ownership)[0];
                $courseImpl->setFolder($this->folderMapper->findById($classFolderId));
                $courseImpls[$classFolderId] = $courseImpl;
            }
        } else {
            // We can only modify a single Course Import at a time.
            $courseImpl = $this->getCourseImplementationMapper()->findById($postedCourseImplId);
            $courseImpls[$courseImpl->getFolder()->getId()] = $courseImpl;

            // Load UnitImplementations
            $courseImpl->setUnits($this->getUnitImplementationMapper()->findAllByCourseId($courseImpl->getId()));

            // Load IAssessables
            foreach($courseImpl->getUnits() as $unitImpl){
                $unitImpl->setAssessables($this->getAssessmentMapperProxy()->findAllForUnitImplementationId($unitImpl->getId()));
            }
        }


        // Traverse pre-existing course import, deleting any unitImpls not in POST
        // Will be skipped during new-import
        foreach($courseImpls as $classFolderId => $courseImpl){
            foreach($courseImpl->getUnits() as $unitImpl){
                $unitSpec = $unitImpl->getSpecification();

                // Did user de-select this unit?
                if(!array_key_exists($unitSpec->getId(), $postedUnitImpls)){
                    // Delete the unitImpl. Cascading the delete to subsidiary IAssessables
                    $this->getUnitImplementationMapper()->delete($unitImpl);
                }
                // Else still selected
                else {
                    // Delete any Assessments in the UnitImpl if de-selected by user
                    foreach($unitImpl->getAssessables() as $assessableImpl){

                        $postAssessmentImpl = $postedUnitImpls[$unitSpec->getId()]['a'][$assessableImpl->getId()];
                        // Is this Assessment a impl or spec? (we ignore specs, nothing to delete)
                        if($postAssessmentImpl['type'] == 'impl'){
                            // Did user de-select this Assessment ?
                            if(!$postAssessmentImpl['checked']){
                                $this->getAssessmentMapperProxy()->delete($assessableImpl);
                            }
                        }
                    }
                }
            }
        }


        // Create any un-imported UnitSpecs + AssessmentSpecs
        // Update any imported UnitImpls + AssessmentImpls
        foreach($courseImpls as $classFolderId => $courseImpl) {
            foreach ($postedUnitImpls as $unitPair) {
                try {
                    $unitSpec = $this->getUnitSpecificationMapper()->findById($unitPair['unit_spec_id']);
                } catch (MapperException\Deleted $e) {
                    $unitSpec = $e->getDeletedObject();
                }
                $unitImplOpenDate = \Alaress_Date_Format::newLocalShort($unitPair['open_date']);
                $unitImplCloseDate = \Alaress_Date_Format::newLocalShort($unitPair['close_date']);
                $postAssessments = $unitPair['a'];

                // Does the CourseImpl already contain this Unit ?
                $unitSpecId = $unitSpec->getId();
                $callback = function ($unitImpl) use ($unitSpecId) {
                    /** @var UnitImplementation $unitImpl */
                    return $unitSpecId == $unitImpl->getSpecification()->getId();
                };
                /** @var UnitImplementation $unitImpl */
                $unitImpl = $courseImpl->getUnits()->filter($callback)->first();

                // If the CourseImpl does not contain this UnitSpec
                if (!$unitImpl) {
                    // Import the UnitSpec into the CourseImpl
                    $unitImpl = $this->getUnitImplementationMapper()->createModel();
                    $unitImpl->setSpecification($unitSpec);
                    $unitImpl->setCourse($courseImpl);
                    $unitImpl->setTitle($unitSpec->getFolder()->getTitle());
                    $courseImpl->getUnits()->add($unitImpl);
                }
                $unitImpl->setOpenDate($unitImplOpenDate);
                $unitImpl->setDueDate($unitImplCloseDate);
                $unitImpl->setFolder($courseImpl->getFolder());


                // Clone  Assessment Specs
                // Update Assessment Impls
                foreach ($postAssessments as $assessmentId => $aData) {
                    // Ignore unchecked items. ( If they were already imported, they were deleted above.)
                    if (!$aData['checked']) {
                        continue;
                    }
                    // Is this a creation from the spec ?
                    if ('spec' == $aData['type']) {

                        $assessmentMapper = AssessmentMapper::getInstance();

                        $assessmentSpec = $assessmentMapper->findById($assessmentId);
                        $assessmentImpl = clone $assessmentSpec;


                        $assessmentImpl->setContextUnitImplementation($unitImpl);
                        $assessmentImpl->setContextUnitSpecification($unitSpec);
                        // TODO need to implement __clone() on DueWork to clone Rubric correctly
                        $assessmentImplDomain = $this->getAssessmentMapperProxy()->translateToDomainModel($assessmentImpl);
                    } // Else we have been passed the Assessment Impl
                    else {
                        $assessmentImplDomain = $this->getAssessmentMapperProxy()->findById($assessmentId);
                    }

                    // Update Dates

                    // set dates
                    foreach ($aData['dates'] as $property => $localShort) {
                        $method = "set{$property}at";
                        if (!method_exists($assessmentImplDomain, $method)) {
                            $this->sendError("Invalid date property: $property");
                            return;
                        }
                        $assessmentImplDomain->$method(\Alaress_Date_Format::newLocalShort($localShort));
                    }
                    $unitImpl->getAssessables()->add($assessmentImplDomain);
                }
            }
        }

        // Save each Course Import
        try{
            $this->getCourseImplementationMapper()->save($courseImpls);
            foreach($courseImpls as $classFolderId => $courseImpl) {

                // Save the Unit Imports
                $this->getUnitImplementationMapper()->save($courseImpl->getUnits());

                // For each UnitImport, save the non-deleted Assessments
                foreach ($courseImpl->getUnits() as $unitImpl) {

                    $extantAssessments = $unitImpl->getAssessables()->filter(function ($assessable) {
                        /** @var IAssessable $assessable */
                        return !$assessable->isDeleted();
                    });

                    foreach ($extantAssessments as $seq => $assessment) {
                        $this->getAssessmentMapperProxy()->save($assessment, $seq + 1);
                        $f = 1;
                    }
                }

                // Associate the CourseImpl with a CourseOutline Component
                //TODO
            }
        } catch (\PDOException $e) {
            $this->sendError($e->getMessage());
            return;
        }


        if(true){
            return;
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
                $a = $this->getAssessmentMapperProxy()->findById($assId);

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
            $courseImpls = $this->getCourseImplementationFactory()->build($courseSpec, $this->getFolderMapper()->findById($folderId));

            // assign the new owner to every assessment
            foreach ($courseImpls->getUnits() as $unit) {

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

            $courseImplementations->add($courseImpls);
        }

        try {
            // aaaaaand, into the DB with you!
            foreach ($courseImplementations as $ci) {
                $this->getCourseImplementationMapper()->save($ci);

                // save the assessments inside
                foreach ($ci->getUnits() as $unit) {
                    foreach ($unit->getAssessables() as $seq => $a) {
                        $this->getAssessmentMapperProxy()->save($a, $seq + 1);
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

        // Add un-imported UnitSpecs to CourseImpl
        foreach($courseSpec->getUnits() as $unitSpec){
            // Has this UnitSpec been imported?
            foreach($courseImpl->getUnits() as $unitImpl){
                if($unitImpl->getSpecification()->getId() == $unitSpec->getId()){
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
        try{
            foreach($courseImpl->getUnits() as $unitImpl){
                $assessables = $this->getAssessmentMapperProxy()->findAllForUnitImplementationId($unitImpl->getId());
                foreach($assessables as $assessable){
                    $owner = $this->getUserMapper()->findById($assessable->getCreator()->getId());
                    break 2;
                }
            }
        } catch (MapperException $e){
            // noop
        }
        if(!$owner) {
            $owner = $this->getUser();
        }

        $this->tpl('import', [
            'action'       => 'modify',
            'initialStage' => 3,
            'courseSpec'   => $courseSpec,
            'courseSpecId' => $courseSpec->getId(),
            'courseImpl'   => $courseImpl,
            'folder'       => $courseImpl->getFolder(),
            'destinations' => [
                'id'   => $courseImpl->getFolder()->getId(),
                'name' => $courseImpl->getFolder()->getTitle()
            ],
            'ownershipCandidates' => [
                $courseImpl->getFolder()->getId() => [
                    $owner,
                ],
            ],
            'unitsImpls'   => $courseImpl->getUnits(),
            'ownerships'   => "{$courseImpl->getFolder()->getId()}:{$owner->getId()}", // TODO is this redundant for modify?
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

}
