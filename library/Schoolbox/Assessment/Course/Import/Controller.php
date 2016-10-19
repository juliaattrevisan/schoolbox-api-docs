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
use Component_Homepage_CourseOutlineController;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\Unit;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\Config;
use Schoolbox\Resource;

class Controller extends Base\Base
{

    /** @var Unit\SpecificationMapperProxy */
    private $unitSpecificationMapperProxy;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var Course\SpecificationMapperProxy */
    private $courseSpecificationMapperProxy;

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var Access */
    private $access;

    /** @var UserMapper */
    private $userMapper;


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
     * This GET is the start of the import process. Import a Course into a set of Folders (classes)
     *
     * @api   GET /course/import/{courseId}/class/{classCode}{/componentId}
     * @level 160
     *
     * @param Course\Specification $courseSpec
     * @param Folder\Model         $targetClass
     * @param int                  $componentId the ID of the component the course is to be imported into
     */
    public function getImportForm(Course\Specification $courseSpec = null, Folder\Model $targetClass = null, $componentId = null)
    {
        $importer = new Import();

        $context = array_merge($importer->importSelectize($courseSpec, $targetClass), ['componentId' => $componentId]);

        $this->tpl('import', $context);
    }

    /**
     * This POST is the result of import stage 2
     *
     * TODO: replace component ID parameter with course implementation ID?
     * TODO: figure out how this should work with course specification
     *
     * @api   POST /course/import/ownership
     * @level 160
     */
    public function importOwnership()
    {
        $ownerships  = $this->getRequest()->post["ownership"];
        $courseId    = trim($this->getRequest()->post["importCourse"]);
        $componentId = trim($this->getRequest()->post["componentId"]);

        $unitSpecs = $this->getUnitSpecificationMapperProxy()->findAllByCourseId($courseId);

        // build implementation objects from the specs
        // this may seem a little redundant, but the "new import" and "modify import" screen are one, and so require consistent data models
        $unitImplFactory = new Course\Unit\ImplementationFactory();

        $unitImpls = new Collection();

        foreach ($unitSpecs as $spec) {
            $unitImpls->add($unitImplFactory->build($spec));
        }

        // process ownership assignments
        // respond with stage 3: unit selection
        $this->tpl('unitImportForm', [
            'homepage' => implode(',', array_keys($ownerships)),
            'courseHomepage' => $courseId,
            'componentId'    => $componentId,
            'units'          => $unitImpls,
            'ownerships'     => implode(',', array_map(
                    function ($k, $v) { return "$k:$v"; }, array_keys($ownerships), array_values($ownerships))
            )
        ]);
    }


    /**
     * Search function. Retrieve a list of Folders that match the string in $_GET['query']
     * TODO: rename this, and everything in it; it confusingly uses "import" in its noun form, rather than verb
     *
     * @api   GET /course/import/classes
     * @level 160
     */
    public function importClasses()
    {
        $importer = new Import();

        // TODO: despite its name, "importClasses" actually just performs a search based on the query string given
        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => $importer->importClasses($this->getRequest()->query->get('query'))
        ]);
    }

    /**
     * Get the Units and Tasks for a particular Course
     *
     * @api   GET /course/importResults/{courseId}
     * @level 160
     *
     * @param string $courseId
     */
    public function importResults($courseId)
    {
        $importer = new Import();

        $this->tpl('import/results', $importer->findUnitsAndTasks($courseId));
    }

    /**
     * This POST is the result of import stage 1. Retrieve fragment with units in the selected course, and checkboxes/dates for selection
     *
     * @api   POST /course/import/classes
     * @level 160
     */
    public function postClasses()
    {
        $homepage = trim($this->getRequest()->post["importFolders"]);
        $courseId = trim($this->getRequest()->post["importCourse"]);
        $componentId = trim($this->getRequest()->post["componentId"]);

        try {
            $this->getCourseSpecificationMapperProxy()->findById($courseId);

        } catch (MapperException $e) {
            // the nominated "course" is not actually a course...
            $this->sendError('The selected source is not a course');
            return;
        }

        if ($homepage == "" && $courseId == "") {
            // They didn't select anything

            $this->sendError('You must select at least one class and a course to import before you can proceed');
            return;

        } elseif ($homepage == "") {
            // They didn't select a class code

            $this->sendError('You must select at least one class before you can proceed');
            return;

        } elseif ($courseId == "") {
            // They didn't select a course to import

            $this->sendError('You must select a course to import before you can proceed');
            return;
        }

        $targetFolderIds = explode(',', $homepage);

        $folders = $this->getFolderMapper()->findByColumn('id', $targetFolderIds);

        $possibleOwnerships = [];

        foreach ($folders as $folder) {
            $possibleOwnerships[$folder->getId()] = $this->getUserAccessMapper()->findUsersInCategory(
                $folder, Role::STAFF, UserAccess::ACCESS_WRITE
            );

            if (count($possibleOwnerships[$folder->getId()]) == 0) {
                // if nobody else was returned, jam the session user in there.
                $possibleOwnerships[$folder->getId()] = new Collection([$this->getUser()]);
            }
        }

        // Everything kosher, proceed with the mage
        // respond with stage 2: ownership
        $this->tpl('import/ownership', [
            'targetFolders' => $folders,
            'importCourse' => $courseId,
            'ownershipCandidates' => $possibleOwnerships,
            'componentId' => $componentId
        ]);

    }


    /**
     * This POST is the result of import stage 3. Retrieve friend with a list of tasks, quizzes
     * and due work (and relevant date entry boxes) associated with the selected course.
     *
     * @api   POST /course/importTasks
     * @level 160
     *
     * @throws Unauthorized
     **/
    public function importTasks()
    {
        $post = $this->getRequest()->post;

        if (trim($post["homepage"]) == "") {
            // The homepage ID has gone missing
            $this->sendError('No class code was specified');
            return;
        }

        if (trim($post["course_homepage"]) == "") {
            // The course homepage ID has gone missing
            $this->sendError('No course was specified');
            return;
        }

        $homepages = explode(",", $post["homepage"]);

        // Test permissions. Must have write permission on the destination page and read permission
        // on the course we are trying to import.

        // Test course page read access
        if ($this->getAccess()->getFolderAccess($this->getUser(), [$post["course_homepage"]]) < READ_ACCESS) {
            throw new Unauthorized();
        }

        // Test each homepage for write access
        foreach ($homepages as $homepage) {
            if ($this->getAccess()->getFolderAccess($this->getUser(), [$homepage]) < WRITE_ACCESS) {
                throw new Unauthorized();
            }
        }

        $foundUnit = false;

        foreach ($post["unit"] as $unit) {
            if (empty($unit['import'])) {
                continue;
            }

            $foundUnit = true;

            try {
                // valiDate *puns* XD
                $openDate = \Alaress_Date_Format::newLocalShort($unit['open_date']);
                $dueDate  = \Alaress_Date_Format::newLocalShort($unit['due_date']);
                if ($openDate->gt($dueDate)) {
                    $this->sendError('The open date must be before the close date');
                    return;
                }
            } catch (\Exception $e) {
                $this->sendError('You must specify a valid open and close date for each unit you are importing');
                return;
            }
        }

        // Make sure at least one unit was specified
        if (!$foundUnit) {
            $this->sendError('You must select at least one unit to import');
            return;
        }

        /* Figger out when each task should open, due, late, etc
         * A superposition of Implementation Tasks over Specification Tasks
         */
        try {
            // TODO reimplement on Spec/Impl object graph
            $allTaskDates = [];

        } catch (MapperException $e) {
            $this->sendError($e->getMessage());
            return;
        }

        // respond with stage 4: coursework list
        $this->tpl('unitImportResults', [
            'ownerships'     => $post['ownerships'],
            'homepage'       => $post["homepage"],
            'componentId'    => $post["componentId"],
            'courseHomepage' => $post["course_homepage"],
            'unitDates'      => $post["unit"],
            'units'          => $allTaskDates['units'],
            'submitValue'    => empty($post['ownerships']) ? 'Modify' : 'Import',
        ]);
    }


    /**
     * TODO refactor to new object graph
     *
     * This POST is the result of import stage 4 (final). Do the actual import
     * and create all relevant database entries.
     *
     * @api   POST /course/importSave
     * @level 160
     */
    public function importSave()
    {
        $post = $this->getRequest()->post;

        // Validate dates before we save (nothing to do for tasks)

        // Validate due work

        if (isset($post["work"])) {
            foreach ($post["work"] as $work) {
                try {
                    \Alaress_Date_Format::newLocalShort($work['publish_date']);
                } catch (\Exception $e) {
                    $this->sendError('You must enter a publish date for each due work item before you can proceed.');
                    return;
                }

                try {
                    \Alaress_Date_Format::newLocalShort($work['due_date']);
                } catch (\Exception $e) {
                    $this->sendError('You must enter a due date for each due work item before you can proceed.');
                    return;
                }

                // Accepting submissions, must have the other two dates

                if ($work['submissions'] == '1') {
                    try {
                        \Alaress_Date_Format::newLocalShort($work['open_date']);
                    } catch (\Exception $e) {
                        $this->sendError('You must enter an open date for each due work item that accepts submissions before you can proceed.');
                        return;
                    }

                    try {
                        \Alaress_Date_Format::newLocalShort($work['late_date']);
                    } catch (\Exception $e) {
                        $this->sendError('You must enter a late date for each due work item that accepts submissions before you can proceed.');
                        return;
                    }
                }
            }
        }

        // Validate quizzes

        if (isset($post["quiz"])) {
            foreach ($post["quiz"] as $quiz) {
                try {
                    \Alaress_Date_Format::newLocalShort($quiz['start_date']);
                } catch (\Exception $e) {
                    $this->sendError('You must enter a start date for each quiz before you can proceed.');
                    return;
                }

                try {
                    \Alaress_Date_Format::newLocalShort($quiz['finish_date']);
                } catch (\Exception $e) {
                    $this->sendError('You must enter a finish date for each quiz before you can proceed.');
                    return;
                }
            }
        }

        // Parse homepages out of CSV format into an array

        $homepages = explode(",", $post["homepage"]);

        // Test permissions for each homepage. Must have write permission on the destination page and read permission
        // on the course we are trying to import.

        // Test course page read access

        if ($this->getAccess()->getFolderAccess($this->getUser(), [$post["course_homepage"]]) < READ_ACCESS) {
            throw new Failure\Unauthorized();
        }

        // Test each homepage for write access

        foreach ($homepages as $homepage) {
            if ($this->getAccess()->getFolderAccess($this->getUser(), [$homepage]) < WRITE_ACCESS) {
                throw new Failure\Unauthorized();
            }
        }

        /** @var \Schoolbox\User[] $owners */
        $owners = [];

        if (isset($post['ownerships']) && strlen($post['ownerships']) && strpos($post['ownerships'], ':')) {
            // unsquash ownerships
            foreach (explode(',', $post['ownerships']) as $ownership) {
                if (preg_match('/^\\d+:\\d+/', $ownership)) {
                    list ($folderId, $userId) = explode(':', $ownership);
                    try {
                        $owners[$folderId] = $this->getUserMapper()->findById($userId);
                    } catch (MapperException $e) {
                        // Couldn't find user. That's sort of okay.
                        $owners[$folderId] = null;
                    }
                }
            }
        }


        // Keep a cache of queried data

        /** @var Folder\Model[] $unitCache */
        $unitCache = [];
        /** @var Folder\Model[] $homepageCache */
        $homepageCache = [];
        /** @var Component_Instance[] $componentCache */
        $componentCache = [];

        // Delete any units that aren't selected

        foreach ($post["unit"] as $unit) {
            if ($unit["import"] != 1) {
                $unitsToDelete = $this->getUnitInstanceMapper()->findByColumnList([
                    "unit_category_id" => $unit["unit_id"],
                    "component_id" => $post["component_id"],
                    "category_id" => $post["homepage"]
                ]);
                $this->getUnitInstanceMapper()->delete($unitsToDelete);
            }
        }

        // Create course outline component on each homepage

        try {
            foreach ($homepages as $homepage) {
                // Get the homepage we are importing into and store it for later
                $homepageCache[$homepage] = $this->getFolderMapper()->findById($homepage);

                try {

                    // Updating existing course outline, just grab it from the database
                    $component = $this->getComponentInstanceMapper()->findById($post["component_id"]);

                    if ($component->getIdentifier() == $homepage
                        && $component->getModule() == 'homepage'
                    ) {
                        $componentCache[$homepage] = $component;
                    } else {
                        // Inserting new course outline, create it on the current homepage
                        $componentCache[$homepage] = $this->injectNewComponent($homepageCache[$homepage], Component_Homepage_CourseOutlineController::class);
                    }

                } catch (\Exception $e) {

                    // Inserting new course outline, create it on the current homepage
                    $componentCache[$homepage] = $this->injectNewComponent($homepageCache[$homepage], Component_Homepage_CourseOutlineController::class);
                }
            }

            // Create new unit instances in each of these pages/components

            foreach ($homepages as $homepage) {

                /* Use same derivation as previous step
                 */
                $allTaskDates = $this->determineTaskDates(
                    $post["unit"],
                    $componentCache[$homepage]->getId()
                );

                $unit_instance_list = new Collection();

                foreach ($allTaskDates['units'] as $currentUnit) {

                    /* TODO: Not sure if caching is much use here
                     * I thought Units couldn't be duped anyway
                     */
                    if (!isset($unitCache[$currentUnit['id']])) {
                        try {
                            $unitCache[$currentUnit['id']] = $this->getFolderMapper()->findById($currentUnit['id']);
                        } catch (MapperExceptionDeleted $e) {
                            // accommodate deleted units
                            $unitCache[$currentUnit['id']] = $e->getDeletedObject();
                        } catch (\Exception $e) {
                            $this->sendError('An unexpected error occurred');
                            return;
                        }
                    }

                    // Work out if we are updating an existing course, or importing a new one

                    try {

                        // Updating an existing unit

                        /** @var UnitInstance $unit_instance */
                        $unit_instance = $this->getUnitInstanceMapper()->findOneByColumnList([
                            "component_id" => $componentCache[$homepage]->getId(),
                            "category_id" => $homepageCache[$homepage]->getId(),
                            "unit_category_id" => $unitCache[$currentUnit['id']]->getId(),
                            "deleted_at" => null,
                        ]);

                        // Update the units open/due date

                        $unit_instance
                            ->setOpenDate(\Alaress_Date_Format::newLocalShort($currentUnit['open_date'])->startOfDay())
                            ->setDueDate(\Alaress_Date_Format::newLocalShort($currentUnit['due_date'])->endOfDay());

                        // Update everything associated with the unit instance

                        $taskInstances = $this->getTaskInstanceMapper()->findByColumn("course_unit_instance_id", $unit_instance->getId());

                        /** @var TaskInstance $taskInstance */
                        foreach ($taskInstances as $taskInstance) {
                            switch ($taskInstance->getType()) {
                                case Task::TYPE_DUE_WORK:

                                    // Due work

                                    $submissionBox = $this->submissionBoxMapper->findById($taskInstance->getSubmissionBox()->getId());
                                    $submissionBox->setDatesFromForm($post["work"][$taskInstance->getId()]);

                                    // Date validation:
                                    // Publish <= Open <= Due <= Late
                                    if ($submissionBox->getPublishDate()->gt($submissionBox->getOpenDate())) {
                                        $this->sendError('A task\'s open date may not be before its publish date.');
                                        return;
                                    }
                                    if ($submissionBox->getOpenDate()->gt($submissionBox->getDueDate())) {
                                        $this->sendError('A task\'s due date may not be before its open date.');
                                        return;
                                    }
                                    if ($submissionBox->getDueDate()->gt($submissionBox->getLateDate())) {
                                        $this->sendError('A task\'s late date may not be before its due date.');
                                        return;
                                    }

                                    if (isset($owners[$homepage])) {
                                        // update the owner
                                        $submissionBox->setOwner($owners[$homepage]);
                                    }

                                    $this->getSubmissionBoxMapper()->save($submissionBox);

                                    break;

                                case Task::TYPE_ONLINE_ASSESS:

                                    // Quizzes

                                    $assessment = $this->getOnlineAssessmentMapper()->findById($taskInstance->getAssessment()->getId());
                                    $assessment->setDatesFromForm($post["quiz"][$taskInstance->getId()]);

                                    // Date validation:
                                    // Start <= Finish
                                    if ($assessment->getStart()->gt($assessment->getFinish())) {
                                        $this->sendError('A task\'s finish date may not be before its start date.');
                                        return;
                                    }

                                    if (isset($owners[$homepage])) {
                                        // update the owner
                                        $assessment->setUser($owners[$homepage]);
                                    }

                                    $this->getOnlineAssessmentMapper()->save($assessment);

                                    break;
                            }
                        }

                        $this->getSubmissionBoxMapper()->findByColumn("task_instance_id", $unit_instance->getId());

                        $unit_instance_list->add($unit_instance);

                    } catch (MapperException $e) {

                        // Adding a new unit into the course.

                        $unit_instance = new UnitInstance();

                        $unit_instance
                            ->setWorkDates($post["work"])// Override due work dates in cloner
                            ->setQuizDates($post["quiz"])// Override quiz dates in cloner
                            ->setWorkOwner(isset($owners[$homepage]) ? $owners[$homepage] : $this->getUser())
                            ->setComponent($componentCache[$homepage])
                            ->setTitle($unitCache[$currentUnit['id']]->getName())
                            ->setCategory($homepageCache[$homepage])
                            ->setUnitCategory($unitCache[$currentUnit['id']])
                            ->setOpenDate(\Alaress_Date_Format::newLocalShort($currentUnit['open_date'])->startOfDay())
                            ->setDueDate(\Alaress_Date_Format::newLocalShort($currentUnit['due_date'])->endOfDay());

                        $unit_instance_list->add($unit_instance);
                    }
                }

                /* TODO: Coursework Date superposition
                 * Apply separate dates for existing and new tasks
                 */
                $this->getUnitInstanceMapper()->save($unit_instance_list);
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

        } catch (\InvalidArgumentException $e) {
            // bad dates, most likely
            $this->sendError("Invalid date given");

        } catch (\Exception $e) {
            $this->sendError($e->getMessage());
        }

    }

    /**
     * TODO refactor to new object graph
     *
     * This GET provides the import form, stepped in at stage 3 (since target and ownership cannot be changed)
     * Modify the Unit information for a previously imported Course
     *
     * @api   GET /course/import/modify/{componentId}
     * @level 160
     *
     * @param int $componentId
     */
    public function importModify($componentId)
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
            /** @var Folder\Model $folder */
            $folder = $values['folder'];
            $unitTitle = '';
            $checkable = false;
            if ($folder instanceof Folder\Model) {
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
     * @return Unit\SpecificationMapperProxy
     */
    public function getUnitSpecificationMapperProxy()
    {
        return $this->unitSpecificationMapperProxy;
    }

    /**
     * @param Unit\SpecificationMapperProxy $unitSpecificationMapperProxy
     * @return static
     */
    public function setUnitSpecificationMapperProxy($unitSpecificationMapperProxy)
    {
        $this->unitSpecificationMapperProxy = $unitSpecificationMapperProxy;
        return $this;
    }

    /**
     * Get folder mapper
     *
     * @return Folder\Mapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * Set folder mapper
     *
     * @param Folder\Mapper $folderMapper
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }


    /**
     * @return Course\SpecificationMapperProxy
     */
    public function getCourseSpecificationMapperProxy()
    {
        return $this->courseSpecificationMapperProxy;
    }

    /**
     * @param Course\SpecificationMapperProxy $courseSpecificationMapperProxy
     * @return static
     */
    public function setCourseSpecificationMapperProxy($courseSpecificationMapperProxy)
    {
        $this->courseSpecificationMapperProxy = $courseSpecificationMapperProxy;
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
    public function setUserAccessMapper($userAccessMapper)
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
    public function setAccess($access)
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
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

}
