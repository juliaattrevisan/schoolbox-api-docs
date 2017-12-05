<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Web\Controller as Base;
use Aura\Di\Exception\ServiceNotFound;
use Component_DefinitionMapper;
use Component_Instance;
use Component_InstanceMapper;
use RangeException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Course\Config\Model as CourseConfigModel;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted as MapperExceptionDeleted;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Browser\Resources\TreeBuilder;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Controller as FolderController;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use \Schoolbox\Learning\Assessment\Component\Homepage\Builder\Controller as CourseBuilderController;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;

class Controller extends Base\Base
{
    /** @var FolderMapper */
    private $folderMapper;

    /** @var SubmissionBoxMapper */
    private $submissionBoxMapper;

    /** @var TaskMapper */
    private $taskMapper;

    /**  @var CourseSpecificationMapper */
    private $courseSpecificationMapper;

    /** @var AssessmentMapperProxy */
    private $assessmentMapperProxy;

    /** @var UnitSpecificationMapper */
    private $unitSpecificationMapper;

    /** @var UnitImplementationMapper */
    private $unitImplementationMapper;

    /** @var CourseImplementationMapper */
    private $courseImplementationMapper;

    /** @var Component_DefinitionMapper */
    private $componentDefinitionMapper;

    /** @var Component_InstanceMapper */
    private $componentInstanceMapper;

    /** @var HomepageConfigMapper */
    private $homepageConfigMapper;

    /** @var  HomepageTemplateMapper */
    private $homepageTemplateMapper;

    /** @var CourseConfigMapper */
    private $courseConfigMapper;

    /** @var ErrorMapper */
    private $errorMapper;

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var UserMapper */
    private $userMapper;

    /** @var Access */
    private $access;

    /** @var AssessmentMapper */
    private $assessmentMapper;

    /** @var CurriculumNodeMapper */
    private $curriculumNodeMapper;


    /**
     * Generates a Unit and Course for a pair of folders.
     *
     * A single call to this method may create a Unit, a Course or both. Note that we DO enforce a
     * parent-child relationship between a Unit's folder and the Course's folder.
     *
     * While to the User it appears like folders have been converted, under the hood this is achieved
     * by associating the specified folders with a Unit and/or Course respectively.
     *
     * Does not permit moving an existing Unit to a new Course.
     *
     * @api POST /course/convert/:courseFolderId/:unitFolderId
     * @level 165
     *
     * @param Folder $courseFolder
     * @param Folder $unitFolder
     */
    public function convertFolders(Folder $courseFolder, Folder $unitFolder)
    {
        $errors = [];

        // Is the course folder not the parent of the unit folder?
        if($unitFolder->getParent()->getId() != $courseFolder->getId()){
            $errors[] = "Course folder (id: {$courseFolder->getId()}) is not the parent of Unit folder (id: {$unitFolder->getId()})";
            $this->returnJson($errors, 400);
            return;
        }

        $course = $courseFolder->getCourseSpecification();
        $unit = $unitFolder->getUnitSpecification();

        $courseExists = $course instanceof CourseSpecification && $course->getId();
        $unitExists = $unit instanceof UnitSpecification && $unit->getId();

        // Do the folders already have a Unit and Course attached ?
        if($courseExists && $unitExists){
            $errors[] = "Course (id: {$course->getId()}) and Unit (id: {$unit->getId()}) already linked.";
            $this->returnJson($errors, 400);
            return;
        }

        // Does Unit already exist and belong to different Course?
        if($unitExists && !$courseExists){
            $errors[] = "Unit (id: {$unit->getId()}) already exists in Course (id: {$unit->getCourse()->getId()})";
            $this->returnJson($errors, 400);
            return;
        }

        // Is the proposed unit folder the same as the course folder?
        if($unitFolder->getId() == $courseFolder->getId()){
            $errors[] = 'Cannot convert the same folder into both a Unit and a Course. FolderId: ' . $courseFolder->getId();
        }

        // Check proposed Course
        if(!$courseExists){
            // Does user have WRITE+ access to Course Folder?
            $courseFolderAccess = $this->getAccess()->getFolderAccess($this->getUser(), $courseFolder->getId());
            if($courseFolderAccess < UserAccess::ACCESS_WRITE){
                $errors[] = 'Course Folder permission error: '.$courseFolder->getId();
            }

            // Does the proposed Course Folder already have a Unit
            $courseFolderUnit = $courseFolder->getUnitSpecification();
            if($courseFolderUnit instanceof UnitSpecification && $courseFolderUnit->getId()){
                $errors[] = 'Proposed Course folder already has a Unit: ' . $courseFolderUnit->getId();
            }
        }

        // Check the proposed Unit
        if(!$unitExists){
            // Does user have WRITE+ access to the Unit folder?
            $unitFolderAccess = $this->getAccess()->getFolderAccess($this->getUser(), $unitFolder->getId());
            if($unitFolderAccess < UserAccess::ACCESS_WRITE){
                $errors[] = 'Unit Folder permission error: '.$unitFolder->getId();
            }

            // Does the proposed Unit Folder already have a Course ?
            $unitFolderCourse = $unitFolder->getCourseSpecification();
            if($unitFolderCourse instanceof CourseSpecification && $unitFolderCourse->getId()){
                $errors[] = 'Proposed Unit folder already has a Course: ' . $unitFolderCourse->getId();
            }
        }

        if(count($errors)){
            $this->returnJson($errors, 400);
            return;
        }

        // No errors. Proceed.

        // Create Course if required
        if(!$courseExists){
            $course = $this->getCourseSpecificationMapper()->createModel();
            $course->setFolder($courseFolder);
        }

        // Create Unit if required
        if(!$unitExists){
            $unit = $this->getUnitSpecificationMapper()->createModel();
            $unit->setFolder($unitFolder);
        }

        // Link Course and Unit
        $unit->setCourse($course);
        $course->getUnits()->add($unit);

        // And save
        $response = [];
        if(!$courseExists){
            $this->getCourseSpecificationMapper()->save($course);
            $response['course'] = $course->getId();
        }

        // Create Unit if required
        if(!$unitExists){
            $this->getUnitSpecificationMapper()->save($unit);
            $response['unit'] = $unit->getId();
        }

        $this->getResponse()->content->set($this->buildJsonOutput($response));
    }

    /**
     * Convenience method for returning JSON output
     *
     * @param string|array $message
     * @param int $code
     */
    protected function returnJson($message, $code = 200)
    {
        $this->getResponse()->status->set($code);
        if($this->getRequest()->isXhr()){
            $this->getResponse()->content->set($this->buildJsonOutput($message));
        } else {
            die(json_encode($message));
        }
    }

    /**
     * Saves the sequence of Assessments in specified UnitSpecification
     *
     * @api /course/unitspecs/{id}/sequence
     * @level 165
     *
     * @var int[] $_POST['sequence'] AssessmentIds, indexed by sequence
     */
    public function saveAssessmentSequence()
    {
        $assessmentSequence = $this->getRequest()->post->get('sequence');
        $this->returnJson($this->getAssessmentMapper()->sequenceAssessments($assessmentSequence));
    }


    /**
     * Just a raw template render
     *
     * @param string $template
     * @param array $data
     */
    protected function courseTpl($template, $data = [])
    {
        $this->tpl('course', $template, $data, 'course');
    }

    /**
     * Retrieve the Course Builder component instance. If it doesn't exist, create it, then return that
     *
     * @param Folder $unitFolder
     * @return Component_Instance
     */
    protected function getCourseBuilderInstance(Folder $unitFolder)
    {
        // confirm that there's a Course Builder component
        $folderId = $unitFolder->getId();
        $componentClass = CourseBuilderController::class;
        $instances = $this->getComponentInstanceMapper()->findByContextAndClass('homepage', $folderId, $componentClass);

        if (count($instances) == 0) {
            // no instance found - give me a new one
            return $this->injectNewComponent($unitFolder);
        }

        // instance found - use that
        return $instances->first();
    }


    /**
     * @param Folder $folder
     * @param string $controllerClass
     * @return Component_Instance
     */
    protected function injectNewComponent(Folder $folder, $controllerClass = CourseBuilderController::class)
    {
        // Doesn't exist. We need that to exist. Make it exist.
        $definition = $this->getComponentDefinitionMapper()->findOneByColumn('class', $controllerClass);

        // invent a new component
        $newInstance = $this->getComponentInstanceMapper()
            ->createModel()
            ->setModule('homepage')
            ->setIdentifier($folder->getId())
            ->setDefinitionId($definition->getId())
            ->setSequence(0)
            ->setPosition('top');

        // save it
        $this->getComponentInstanceMapper()->save($newInstance);

        // reapply the sequencing so the homepage doesn't break
        $this->getComponentInstanceMapper()->cascadeSequence($folder->getId(), 'top');

        return $newInstance;
    }


    /**
     * Form to create/modify a course specification
     *
     * Course.id will be null if it is a create.
     *
     * @api   GET /course/modify/{id}
     * @level 160
     *
     * @param CourseSpecification $courseSpec The course being created/modified
     * @throws Deleted
     */
    public function modifyCourse(CourseSpecification $courseSpec)
    {
        // Is course deleted ?
        if($courseSpec->isDeleted()){
            throw new NotFound();
        }

        $courseSpec->setUnits($this->getUnitSpecificationMapper()->findAllByCourseId($courseSpec->getId()));

        // Is this an UPDATE ?
        if($courseSpec->getId()){
            // Rehydrate course folder so that curriculum codes exist
            $courseSpec->setFolder($this->getFolderMapper()->findById($courseSpec->getFolder()->getId()));

            // Need to hydrate parent to populate the 'Faculty/Subject' selector
            $courseSpec->getFolder()->setParent($this->getFolderMapper()->findById($courseSpec->getFolder()->getParent()->getId()));
        }

        /**
         * Folder permissions. Indexed by folder.id in all cases.
         *
         * @var int[] $folderAccess
         */
        $folderAccess = [];

        /**
         * Encoded return URL to send to create/update assessment page to enable return to
         * specific unitSpec in specific courseSpec. Indexed by unitSpec.id
         *
         * @var string[] $encodedReturnUrls
         */
        $encodedReturnUrls = [];

        $folderAccess[$courseSpec->getFolder()->getId()] = $this->getAccess()->getFolderAccess($this->getUser(), $courseSpec->getFolder()->getId());

        foreach($courseSpec->getUnits() as $unitSpec){
            $unitSpecId = $unitSpec->getId();
            $folderId = $unitSpec->getFolder()->getId();
            $options = ['order' => ['sequence' => 'ASC']];
            $assessments = $this->getAssessmentMapperProxy()->findAllByUnitSpecificationId($unitSpecId, $options);
            $unitSpec->setAssessables($assessments);
            $folderAccess[$folderId] = $this->getAccess()->getFolderAccess($this->getUser(), $folderId);
            $encodedReturnUrls[$unitSpecId] = $this->getReturnUrl($courseSpec->getId(), $unitSpec->getId());
        }

        $this->courseTpl('course', [
            'courseSpec'        => $courseSpec,
            'curriculumEnabled' => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
            'folderAccess'      => $folderAccess,
            'encodedReturnUrls' => $encodedReturnUrls,
            'isValidFacultyRoot'=> $this->courseSpecificationMapper->isConfiguredFacultyRootValid(),
        ]);
    }


    /**
     * Form to create a new course
     *
     * @api   GET /course/new{/:name}
     * @level 160
     * @param string|null $name
     */
    public function newCourse($name = null)
    {
        $course = $this->getCourseSpecificationMapper()->createModel();
        $folder = $this->getFolderMapper()->createModel();
        $folder->setCourseSpecification($course);
        $course->setFolder($folder);
        $folder->setName($name);
        $this->modifyCourse($course);
    }

    /**
     * Accept the create formdata
     *
     * @api   POST /course/new
     * @level 160
     *
     * @var string   $_POST['name']             name to give to new course's folder
     * @var int      $_POST['parent']           id of the folder to create the new course's folder under
     * @var string[] $_POST['curriculumCodes']  1st item is comma-separated integers. Saves to 'curriculum_node_for_category' table
     */
    public function createCourse()
    {
        $post = $this->getRequest()->post;

        // Get the default permissions to give to Course Folders
        $rolePermissions = [];
        foreach ($this->getCourseConfigMapper()->findByColumn('page', 'course') as $courseConfig) {
            /** @var CourseConfigModel $courseConfig */
            $rolePermissions[$courseConfig->getRoleId()] = $courseConfig->getPermission();
        }

        try {
            $this->validateCourseData(null, trim($post['name']), trim($post['parent']));

            $templateID = $this->getConfigLoader()->get('course_builder_default_course_page_template');

            try {
                $template = HomepageTemplateMapper::getInstance()->findOneByColumn('id', $templateID);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $template = null;
            }

            // Create new CourseFolder
            $courseFolder = $this->persistFolder([
                'name'            => trim($post['name']),
                'parent'          => trim($post['parent']),
                'curriculumCodes' => explode(',', $post['curriculumCodes'][0]),
                'inherit'         => $this->getConfigLoader()->get('course_builder_default_course_page_inherit'),
                'template'        => $template,
                'group'           => $rolePermissions,
                'recursive'       => false, // LEAVE THIS
                'code'            => null,  // LEAVE THIS
            ]);

            $courseSpec = $this->getCourseSpecificationMapper()->createModel();
            $courseSpec->setFolder($courseFolder);
            $courseFolder->setCourseSpecification($courseSpec);

            $this->getCourseSpecificationMapper()->save($courseSpec);
        } catch (Base\Exception $e) {
            $this->sendError($e);
            return;
        }


        if ($this->getRequest()->isXhr()) {
            // respond to XHR with JSON
            $this->getResponse()->content->set($this->buildJsonOutput([
                'id'              => $courseSpec->getId(),
                'urlModifyCourse' => $this->getRouter()->generate('course.updateCourse', ['id' => $courseSpec->getId()]),
                'urlNewUnit'      => $this->getRouter()->generate('course.newUnit', ['id' => $courseSpec->getId()]),
            ]));

        } else {
            // respond to synchronous POST with redirect
            $this->getResponse()->redirect->afterPost($this->getRouteIfAuthorized('course.courseList'));
        }
    }

    /**
     * Checks:
     * * Course Name not empty
     * * user has write access to course's folder's parent
     * * user selected a folder parent
     * * course not being created, or moved to, outside faculty root
     * * no loops in folder ancestry
     *
     * @param int|null $folderId
     * @param string   $folderName
     * @param int      $parentFolderId
     * @throws Base\Exception
     */
    protected function validateCourseData($folderId = null, $folderName, $parentFolderId)
    {
        $errors = [];
        $parentFolder = null;

        if (!$folderName) {
            $errors[] = 'give the course a name';
            $folderName = 'this course';
        }

        // Was no parent indicated? (as opposed to explicitly setting to root)
        if(!$parentFolderId && "0" !== $parentFolderId){
            $errors[] = "select a faculty or class for $folderName to live in";
        } else {
            $access = $this->getAccess()->getFolderAccess($user = $this->getUser(), $parentFolderId);
            if ($access < UserAccess::ACCESS_WRITE) {
                $errors[] = "select a Faculty / Subject for which you have write access";
            } else {
                try{
                    $parentFolder = $this->getFolderMapper()->findCheaplyById($parentFolderId);
                } catch (MapperException $e){
                    $errors[] = "select an existing faculty or class for $folderName to live in";
                }
            }
        }

        // if there were any validation problems, throw that back
        if ($errors || !$parentFolder instanceof Folder) {
            throw new Base\Exception("Could not save", $errors);
        }

        // Explicitly set to root (as opposed to not set)
        if ('0' === $parentFolderId && 0 !== $this->courseSpecificationMapper->getFacultyRootFolder()->getId()) {
            throw new Base\Exception("Courses may not be created in the root folder");
        }


        // Deeper logic tests
        try{
            // Does the folder already exist? If so, retrieve it.
            $folder = null;
            if($folderId){
                $folder = $this->getFolderMapper()->findCheaplyById($folderId);
            }

            // Is this a create, or are we moving an existing course?
            if(!$folderId || $folder->getParent()->getId() != $parentFolderId ){

                if(!$this->getCourseSpecificationMapper()->isFolderValid($parentFolder)){
                    if($folderId){
                        throw new Base\Exception("Courses may not be moved outside the faculty folder");
                    } else {
                        throw new Base\Exception("Courses may not be created outside the faculty folder");
                    }
                }
            }

            // Check we are not moving a folder to be its own parent
            if($folder){
                $folder->setParent($parentFolder);
                if(!$this->getFolderMapper()->checkParentage($folder)) {
                    throw new Base\Exception("Courses may not be moved inside themselves");
                }
            }
        } catch (BaseMapper\Exception $e) {
            throw new Base\Exception("Could not save because " . $e->getMessage());
        }
    }


    /**
     * @api   POST /course/modify/{id}
     * @level 160
     *
     * @param CourseSpecification $courseSpec
     * @var string   $_POST['name'] indexed by unitSpec.id
     * @var int      $_POST['parent'] indexed by unitSpec.id
     * @var string[] $_POST['curriculumCodes']  1st item is comma-separated integers. Saves to 'curriculum_node_for_category' table
     * @var array[]  $_POST['unit'] indexed by unitSpec.id
     * @var string   $_POST[unitSpecId]['name']
     */
    public function updateCourse(CourseSpecification $courseSpec)
    {
        $post = $this->getRequest()->post;
        $courseSpec->getFolder()->setName(trim($post['name']));
        $parentFolderId = trim($post['parent']);
        $curriculumCodes = $post['curriculumCodes'];

        try{
            $this->validateCourseData($courseSpec->getFolder()->getId(), $courseSpec->getFolder()->getName(), $parentFolderId);
            if($courseSpec->getFolder()->getParent()->getId() != $parentFolderId){
                $parentFolder = $this->getFolderMapper()->findCheaplyById($parentFolderId);
                $courseSpec->getFolder()->setParent($parentFolder);
            }
        } catch (Base\Exception $e){
            $this->sendError($e);
            return;
        }

        // Save each Unit's name
        if (is_array($post['unit'])) {
            $courseSpec->setUnits($this->getUnitSpecificationMapper()->findAllByCourseId($courseSpec->getId()));
            // save unit name changes
            foreach ($courseSpec->getUnits() as $unitSpec) {
                $unitName = $post['unit'][$unitSpec->getId()]['name'];
                if (isset($unitName)) {
                    // Re-hydrate unit folder to ensure curriculum codes etc are hydrated.
                    $unitFolder = $this->folderMapper->findById($unitSpec->getFolder()->getId());
                    $this->folderMapper->save($unitFolder->setName($unitName));
                }
            }
        } else {
            $this->sendError(new Base\Exception("Cannot create a Course without Units", ["create at least 1 unit"]));
            return;
        }

        // Save the Course
        $this->getCourseSpecificationMapper()->save($courseSpec);

        // Save the folder
        $folder = $courseSpec->getFolder();
        if(count($curriculumCodes)){
            $folder->setCurriculumNodes($this->getCurriculumNodeMapper()->findByColumn('id', $curriculumCodes));
        }
        $this->getFolderMapper()->save($folder);

        if ($this->getRequest()->isXhr()) {
            // respond to XHR with JSON
            $this->getResponse()->content->set($this->buildJsonOutput([
                'id'              => $courseSpec->getId(),
            ]));
        } else {
            // respond to synchronous POST with redirect
            $this->getResponse()->redirect->afterPost($this->getRouteIfAuthorized('course.courseList'));
        }
    }

    /**
     * Give the encoded return URL for the given course ID
     *
     * @param int $courseId
     * @param int $unitId
     * @return string
     */
    protected function getReturnUrl($courseId, $unitId = null)
    {
        $path = $this->getRouteIfAuthorized('course.modifyCourse', ['id' => $courseId]);

        if ($unitId) {
            $path .= "#$unitId";
        }

        return Redirect::encode($path);
    }


    /**
     * Post request to spawn a new unit
     *
     * Creates:
     * * UnitSpec
     * * Folder
     * * CourseBuilder Component on the Folder
     *
     * @api   POST /course/{courseSpecId}/units/new
     * @level 160
     * @param CourseSpecification $courseSpec
     * @var string $_POST['name'] What to name the new unit
     */
    public function newUnit(CourseSpecification $courseSpec)
    {
        if(!$courseSpec->getId()){
            $this->sendError("Could not save. Course not found.");
            return;
        }
        $post = $this->getRequest()->post;
        $name = trim($post->get('name'));

        if (!$name) {
            $this->sendError('Could not create unit', ['give the unit a name']);
            return;
        }

        try {
            // Need:
            // base config
            // course config
            $rolePermissions = [];
            foreach ($this->getCourseConfigMapper()->findByColumn('page', 'unit') as $courseConfig) {
                $rolePermissions[$courseConfig->getRoleId()] = $courseConfig->getPermission();
            }

            $folder = $this->persistFolder([
                // the basics (note lack of ID and Codes)
                'name'     => $name,
                'parent'   => $courseSpec->getFolder()->getId(),

                // configurable things
                'inherit'  => $this->getConfigLoader()->get('course_builder_default_unit_page_inherit'),
                'template' => $this->getConfigLoader()->get('course_builder_default_unit_page_template'),
                'group'    => $rolePermissions,

                // leave these always off
                'recursive' => false, // LEAVE THIS
                'code'      => null,  // LEAVE THIS
            ]);

            // Persist the unit too
            $unitSpec = new UnitSpecification();
            $unitSpec->setFolder($folder);
            $unitSpec->setCourse($courseSpec);
            $this->getUnitSpecificationMapper()->save($unitSpec);

            $encodedReturnUrl = $this->getReturnUrl($courseSpec->getId(), $unitSpec->getId());

            $data = [
                'unitSpec'         => $unitSpec,
                'encodedReturnUrl' => $encodedReturnUrl,
                '_writable'        => true,
                '_expanded'        => true,
            ];
            $this->courseTpl('unit', $data);

        } catch (ServiceNotFound $e) {
            // quite simple not able to find things we need. bye bye.
            $this->sendError("Could not save because of an internal error.", [], 500);

        } catch (\Exception $e) {
            // whatever the exception, recover from it as best you can
            $this->sendError("Could not save because " . $e->getMessage());
        }
    }


    /**
     * Wrapper around Folder\Controller::persistFolder() and does some extra perms checking
     *
     * @param array  $data
     * @var int|null $data['categoryID'] If absent, this is an INSERT, if present, an UPDATE
     * @var string   $data['name']
     * @var int      $data['parent']
     * @var int[]    $postData['curriculumCodes']
     * @var bool     $data['inherit']    Whether to inherit permissions
     * @var int      $data['template']
     * @var int[]    $data['group']      Maps role to permission level
     * @var bool     $data['recursive']  If true && user is SU, applies $data['inherit'] to entire subtree
     * @var string   $data['code']       Comma-separated codes
     * @return Folder
     */
    protected function persistFolder(array $data)
    {
        /** @var FolderController $folderController */
        $folderController = $this->getController('folder');

        $folder = $folderController->persistFolder($data, $this->getUser());

        // Check that the initiating user has write access to the newly-created folder, and if not,
        // explicitly give it to them. This covers the case where the template allows you to create a folder
        // to which you don't have appropriate access
        if ($this->getAccess()->getFolderAccess($this->getUser(), $folder->getId()) < UserAccess::ACCESS_WRITE) {
            $ua = $this->getUserAccessMapper()->createModel();
            $ua->setUser($this->getUser())
                ->setAccessLevel(UserAccess::ACCESS_WRITE)
                ->setService(UserAccess::SERVICE_HOMEPAGE)
                ->setServiceIdentifier($folder->getId());

            $this->getUserAccessMapper()->save($ua);
        }

        return $folder;
    }

    /**
     * Generate a list of CourseSpecs
     *
     * @api   GET /course/list
     * @level 160
     *
     * @var string[] $_GET                       AJAX GET params
     * @var string   $_GET['encodedReturnUrl']   For URL generation
     * @var int      $_GET['folder']             Folder.id for URL generation
     * @var int      $_GET['component']          ComponentInstance.id for URL generation
     */
    public function courseList()
    {
        $query = $this->getRequest()->query;
        $data = $this->getCourseData();
        $data['returnUrlKey'] = Redirect::RETURN_URL;

        if ($this->getRequest()->isXhr()) {
            $options = [
                'hasClose'         => true,
                'hasAdd'           => false,
                'hasTot'           => false,
                'linkToUnits'      => false,
                'hasBreadcrumb'    => false,
                'encodedReturnUrl' => $query->get('encodedReturnUrl'),
                'folder'           => [ 'id' => $query->get('folder') ],
                'component'        => [ 'id' => $query->get('component') ],
            ];
        } else {
            $options = [
                'hasClose'         => false,
                'hasAdd'           => true,
                'hasTot'           => true,
                'linkToUnits'      => true,
                'hasBreadcrumb'    => true,
                'encodedReturnUrl' => Redirect::encode($this->getRouter()->generate('course.courseList')),
            ];
        }
        $this->courseTpl('courseList', array_merge($data, $options));
    }


    /**
     * Generated when user types in CourseSpec search field
     * Performs a search by CourseSpec's Folder name
     *
     * @api   GET /course/list/ajax
     * @level 160
     *
     * @var string[] $_GET                       AJAX GET params
     * @var string   $_GET['search']             Search string submitted by user
     * @var string   $_GET['encodedReturnUrl']   For URL generation
     * @var bool     $_GET['hasAdd']
     * @var bool     $_GET['hasTot']
     * @var bool     $_GET['linkToUnits']
     * @var int      $_GET['folder']
     * @var int      $_GET['component']
     */
    public function courseListAjax()
    {
        $query = $this->getRequest()->query;

        $search = $query->get('search');
        $folder = $query->get('folder');

        $data = $this->getCourseData($search);

        // only offer suggestions if you're not searching
        if ($folder && !$search) {
            $folderModel = $this->getFolderMapper()->findById($folder);
            $data['suggestions'] = $this->getLinkSuggestions($folderModel);
        }

        $options = [
            'search'           => $search,
            'folder'           => [ 'id' => $folder ],
            'component'        => [ 'id' => $query->get('component') ],
            'hasAdd'           => $query->get('hasAdd', 0),
            'hasTot'           => $query->get('hasTot', 0),
            'linkToUnits'      => $query->get('linkToUnits', 0),
            'returnUrlKey'     => Redirect::RETURN_URL,
            'encodedReturnUrl' => $query->get('encodedReturnUrl'),
        ];

        $this->courseTpl('courseListResults', array_merge($data, $options));
    }

    /**
     * @param int $folderId
     * @param int $min
     * @return bool
     */
    protected function confirmAccess($folderId, $min = UserAccess::ACCESS_READ)
    {
        return $this->getAccess()->getFolderAccess($this->getUser(), $folderId) >= $min;
    }

    /**
     * List the courses. Returns a combined array of Folder instances
     * and information about any found Units and their task counts
     *
     * @param string|null $searchParam   If null, filter out courses residing outside Faculty root
     * @return array $data
     * @var Folder[] $data['lineage']
     * @var CourseSpecification[] $data['courses']
     */
    protected function getCourseData($searchParam = null)
    {
        // Use the new mapper to get the list of courses
        $allCourses = $this->getCourseSpecificationMapper()->findAllByName($searchParam, ['order' => ['f.name' => 'asc']]);

        $data = [];

        foreach ($allCourses as $currentCourse) {

            // general search ignores Courses outside Faculty
            if (null === $searchParam) {
                try {
                    if (!($this->getFolderMapper()->isDescendant($currentCourse->getFolder(), $this->getCourseSpecificationMapper()->getFacultyRootFolder()))) {
                        continue;
                    }
                } catch (MapperException $e) {
                    // can't detect if it's in faculty area because an ancestor is deleted. For our purposes, consider this deleted / irrelevant
                    continue;
                }
            }

            // confirm you at least have read access...
            if (!$this->confirmAccess($currentCourse->getFolder()->getId())) {
                continue;
            }

            /* Also store the path to this Course
             */
            try {
                $data['lineage'][$currentCourse->getId()] = $this->getLineage($currentCourse->getFolder());
            } catch (MapperException $e) {
                // somehow, against all odds, one of my ancestors has been deleted: show nothing
                continue;
            }

            // Get units for a course
            $currentCourse->setUnits($this->getUnitSpecificationMapper()->findAllByCourseId($currentCourse->getId()));

            // Remove units whose folders aren't accessible by this user
            $currentCourse->getUnits()->filter(function($unit) {
                /** @var UnitSpecification $unit */
                return $this->confirmAccess($unit->getFolder()->getId());
            });

            // Add course directly into $data
            $data['courses'][] = $currentCourse;
        }

        return $data;
    }


    /**
     * Convenience method
     *
     * @param Folder $course
     * @throws MapperException if one of the ancestors is deleted or missing somehow
     * @return Collection
     */
    protected function getLineage(Folder $course)
    {
        return $this->getAncestry()->findParentsCheaply($course, $this->getCourseSpecificationMapper()->getFacultyRootFolder());
    }


    /**
     * Gets the suggested course links to A FULLY HYDRATED folder
     *
     * @param Folder $folder
     * @return Collection
     */
    protected function getLinkSuggestions(Folder $folder)
    {
        // obtain curriculum codes...
        $codes = $folder->getCurriculumNodes()->getColumnValues('code');

        // find all the folders that match this code, then filter for coarseness
        return $this->getFolderMapper()->findByCurriculumCode($codes)->filter(function ($result) {
            /** @var Folder $result */
            // TODO: test this with folders and courses which have curriculum codes
            try {
                $this->getCourseSpecificationMapper()->findByFolderId($result->getId());
                return true;
            } catch (MapperException $e) {
                return false;
            }
        });
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
     * List the folders that are allowed to be course parents
     *
     * @param $folderId
     * @api   GET /course/parent
     * @level 160
     */
    public function parentList($folderId)
    {
        if (empty($folderId)) {
            $folderId = $this->getCourseSpecificationMapper()->getFacultyRootFolder()->getId();
        }

        $treeBuilder = new TreeBuilder(
            FolderMapper::getInstance(),
            $_SESSION['user'],
            'Courses'
        );

        $facultyRootFolder = $this->getCourseSpecificationMapper()->getFacultyRootFolder();
        $treeData = $treeBuilder->buildPartialTree($folderId, $facultyRootFolder->getId() ?: '#');

        $data = [
            'treedata' => json_encode($treeData),
            'folderId' => $folderId
        ];

        $this->courseTpl('picker', $data);
    }


    /**
     * Save the given template for the given folder
     *
     * @param Folder $model
     * @param int $template
     */
    protected function saveHomepageConfig(Folder $model, $template)
    {
        $confMapper = $this->getHomepageConfigMapper();
        $tplMapper = $this->getHomepageTemplateMapper();

        $homepageConfig = $confMapper->createModel()->setCategory($model);

        // if homepage is to be removed
        try {
            if ($template == HomepageTemplate::HOMEPAGE_REMOVE) {
                $confMapper->emptyHomepage($homepageConfig);
                $confMapper->delete($homepageConfig);

            } elseif ($template == HomepageTemplate::HOMEPAGE_KEEP_EXISTING) {
                // we're done. get out.
                return;

            } elseif ($template > 0) {
                // if template defined
                $homepageConfig->setTemplate($tplMapper->findById($template));

                // Save homepage config
                $confMapper->save($homepageConfig);
                $confMapper->resetToTemplate($homepageConfig);

            } else {
                throw new RangeException("Invalid template ID specified");
            }
        } catch (BaseMapper\Exception $e) {
            throw new RangeException("Invalid template ID specified", 0, $e);
        }

        // Ram it back into its parent object where it belongs
        $model->setHomepage($homepageConfig);

    }


    /**
     * Delete a course specification
     *
     * @api   POST /course/delete/{id}
     * @level 160
     *
     * @param CourseSpecification $courseSpec The course to delete
     * @param bool $deleteFolder
     */
    public function deleteCourseSpecification(CourseSpecification $courseSpec, $deleteFolder = false)
    {
        // TODO: make delete of folder and course atomic

        if($courseSpec->isDeleted()){
            $this->getResponse()->status->set(410);
        }

        // Call delete(), even if already deleted, to enable mapper hooks to fire
        // (this also cascades the deletions to constituent units and assessments)
        $this->getCourseSpecificationMapper()->delete($courseSpec);

        if($deleteFolder){
            /** @var FolderController $folderController */
            $folderController = $this->getController('folder');
            $folderController->recursiveDelete($courseSpec->getFolder()->getId(), $this->getUser());
        }

        $this->getResponse()->content->set($this->buildJsonOutput([
            'id'     => $courseSpec->getId(),
            'result' => 'success',
        ]));
    }


    /**
     * Delete a unit specification.
     *
     * Does not delete the folder the UnitSpec is linked to.
     * (Essentially we are converting a Unit back to a Folder)
     *
     * @api   POST /course/unitspecs/{id}/delete
     * @level 160
     *
     * @param UnitSpecification $unitSpec The unit spec to delete
     */
    public function deleteUnitSpecification(UnitSpecification $unitSpec)
    {
        // Does this Course exist ?
        if(!$unitSpec->getId()){
            $this->sendError('Unit not found');
            return;
        }
        if($unitSpec->isDeleted()){
            $this->getResponse()->status->set(410);
        }
        // Call delete(), even if already deleted, to enable mapper hooks to fire
        $this->getUnitSpecificationMapper()->delete($unitSpec);
        $this->getResponse()->content->set($this->buildJsonOutput([
            'id' => $unitSpec->getId(),
        ]));
    }

    /**
     * Delete a unit implementation.
     *
     * Does not delete the folder the UnitImpl is linked to.
     * (Essentially we are converting a Unit back to a Folder)
     *
     * @api   POST /course/units/{id}/delete
     * @level 160
     *
     * @param UnitImplementation $unitImpl The unit implementation to delete
     */
    public function deleteUnitImplementation(UnitImplementation $unitImpl)
    {
        // Does this Course exist ?
        if(!$unitImpl->getId()){
            $this->sendError('Unit not found');
            return;
        }
        if($unitImpl->isDeleted()){
            $this->getResponse()->status->set(410);
        }
        // Call delete(), even if already deleted, to enable mapper hooks to fire
        $this->getUnitimplementationMapper()->delete($unitImpl);
        $this->getResponse()->content->set($this->buildJsonOutput([
            'id' => $unitImpl->getId(),
        ]));
    }


    /**
     * Returns a HTTP 500 error with a specific text message
     *
     * @param $message
     */
    protected function throwError($message)
    {
        $this->getResponse()->content->set([
            'format' => 'file',
            'data' => $message
        ]);
    }

    /**
     * @return Ancestry
     */
    public function getAncestry()
    {
        return $this->getFolderMapper()->getAncestry();
    }

    /**
     * @param int $folderId
     * @return FolderInterface
     */
    protected function findFolder($folderId)
    {
        try {
            return $this->getFolder($folderId);

        } catch (MapperExceptionDeleted $e) {
            // Deleted: load the parent (recursively)
            /** @var Folder $folder */
            $folder = $e->getDeletedObject();
            return $this->findFolder($folder->getId());

        } catch (MapperException $e) {
            return $this->folderMapper->createModel()->setId(0);
        }
    }

    /**
     * @param int $folderId
     * @return FolderInterface
     * @throws MapperException
     */
    protected function getFolder($folderId)
    {
        return $this->folderMapper->findById($folderId);
    }

    /* Getters/Setters
     */

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
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * Get task mapper
     *
     * @return TaskMapper
     */
    protected function getTaskMapper()
    {
        return $this->taskMapper;
    }

    /**
     * Set task mapper
     *
     * @param TaskMapper $taskMapper
     */
    public function setTaskMapper($taskMapper)
    {
        $this->taskMapper = $taskMapper;
    }

    /**
     * Get component instance mapper
     *
     * @return Component_InstanceMapper
     */
    protected function getComponentInstanceMapper()
    {
        return $this->componentInstanceMapper;
    }

    /**
     * Set component instance mapper
     *
     * @param Component_InstanceMapper $componentInstanceMapper
     */
    public function setComponentInstanceMapper($componentInstanceMapper)
    {
        $this->componentInstanceMapper = $componentInstanceMapper;
    }

    /**
     * Get component definition mapper
     *
     * @return Component_DefinitionMapper
     */
    protected function getComponentDefinitionMapper()
    {
        return $this->componentDefinitionMapper;
    }

    /**
     * Set component definition mapper
     *
     * @param Component_DefinitionMapper $componentDefinitionMapper
     */
    public function setComponentDefinitionMapper($componentDefinitionMapper)
    {
        $this->componentDefinitionMapper = $componentDefinitionMapper;
    }

    /**
     * Set submission box mapper
     *
     * @param SubmissionBoxMapper $submissionBoxMapper
     */
    public function setSubmissionBoxMapper($submissionBoxMapper)
    {
        $this->submissionBoxMapper = $submissionBoxMapper;
    }

    /**
     * Get submission box mapper
     *
     * @return SubmissionBoxMapper
     */
    protected function getSubmissionBoxMapper()
    {
        return $this->submissionBoxMapper;
    }

    /**
     * @return HomepageConfigMapper
     */
    protected function getHomepageConfigMapper()
    {
        return $this->homepageConfigMapper;
    }


    /**
     * @param HomepageConfigMapper $homepageConfigMapper
     * @return static
     */
    public function setHomepageConfigMapper($homepageConfigMapper)
    {
        $this->homepageConfigMapper = $homepageConfigMapper;
        return $this;
    }


    /**
     * @return HomepageTemplateMapper
     */
    protected function getHomepageTemplateMapper()
    {
        return $this->homepageTemplateMapper;
    }


    /**
     * @param HomepageTemplateMapper $homepageTemplateMapper
     * @return static
     */
    public function setHomepageTemplateMapper($homepageTemplateMapper)
    {
        $this->homepageTemplateMapper = $homepageTemplateMapper;
        return $this;
    }

    /**
     * @return CourseConfigMapper
     */
    protected function getCourseConfigMapper()
    {
        return $this->courseConfigMapper;
    }

    /**
     * @param  CourseConfigMapper $courseConfigMapper
     * @return static
     */
    public function setCourseConfigMapper($courseConfigMapper)
    {
        $this->courseConfigMapper = $courseConfigMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    public function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
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
     * @return CurriculumNodeMapper
     */
    public function getCurriculumNodeMapper()
    {
        return $this->curriculumNodeMapper;
    }

    /**
     * @param CurriculumNodeMapper $curriculumNodeMapper
     * @return static
     */
    public function setCurriculumNodeMapper(CurriculumNodeMapper $curriculumNodeMapper)
    {
        $this->curriculumNodeMapper = $curriculumNodeMapper;
        return $this;
    }
}
