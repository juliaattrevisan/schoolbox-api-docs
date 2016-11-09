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

    /** @var Course\ImplementationMapperProxy */
    private $courseImplementationMapperProxy;

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
     * This GET is the start of the import process. Import a Course into a set of Folders (classes)
     *
     * @api   GET /course/import/{courseSpecId}/class{/classCode}
     * @level 160
     *
     * @param Course\Specification $courseSpec
     * @param Folder\Model         $targetClass
     */
    public function getImportForm(Course\Specification $courseSpec = null, Folder\Model $targetClass = null)
    {
        $importer = new Import();

        $context = $importer->importSelectize($courseSpec, $targetClass);

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
    public function postOwnership()
    {
        $ownerships  = $this->getRequest()->post["ownership"];
        $courseId    = trim($this->getRequest()->post["importCourse"]);

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
            'units'          => $unitImpls,
            'ownerships'     => implode(',', array_map(
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
     * This POST is the result of import stage 1. Retrieve fragment with units in the selected course, and checkboxes/dates for selection
     *
     * @api   POST /course/import/classes
     * @level 160
     */
    public function postClasses()
    {
        $homepage = trim($this->getRequest()->post["importFolders"]);
        $courseId = trim($this->getRequest()->post["importCourse"]);

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
        ]);

    }


    /**
     * This POST is the result of import stage 3. Retrieve friend with a list of tasks, quizzes
     * and due work (and relevant date entry boxes) associated with the selected course.
     *
     * @api   POST /course/import/units
     * @level 160
     *
     * @throws Unauthorized
     **/
    public function postUnits()
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

        $impls = new Collection();

        foreach ($post["unit"] as $unit) {
            if (empty($unit['import']) || $unit['import'] == 0) {
                continue;
            }

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

            try {
                // TODO in import-modify context, support overlay of implementation assessments over spec
                $spec = $this->getUnitSpecificationMapperProxy()->findById($unit['unit_spec_id']);

                $spec->setAssessables($this->getAssessmentMapper()->findAllForUnitSpecificationId($unit['unit_spec_id']));

                $impl = new Unit\Implementation();
                $impl->setOpenDate($openDate)
                     ->setDueDate($dueDate)
                     ->setSpecification($spec);

                $impls[] = $impl;
            } catch (MapperException $e) {
                $this->sendError($e->getMessage(), array_map(function ($k, $v) { return "$k = $v"; }, array_keys($unit), array_values($unit)));
                return;
            }

        }

        // Make sure at least one unit was specified
        if (empty($impls)) {
            $this->sendError('You must select at least one unit to import');
            return;
        }

        // respond with stage 4: coursework list
        $this->tpl('unitImportResults', [
            'ownerships'     => $post['ownerships'],
            'homepage'       => $post["homepage"],
            'courseHomepage' => $post["course_homepage"],
            'unitDates'      => $post["unit"],
            'units'          => $impls,
            'submitValue'    => empty($post['ownerships']) ? 'Modify' : 'Import',
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
     */
    public function postTasks()
    {
        $post = $this->getRequest()->post;

        // $post['unit'][$specId]              contains the information from which to spawn a unit instance
        // $post['unit'][$specId]['a'][$assId] contains the information from which to spawn an assessment to jam into the instance

        $courseSpec = $this->getCourseSpecificationMapperProxy()->findById($post['course_homepage'])->setUnits(new Collection()); // TODO what the actual fuck

        foreach ($post['unit'] as $unitSpecId => $unitPost) {
//          $unitImplId = $unitPost['impl_id'];

            try {
                // cache the spec
                $unitSpec = $this->getUnitSpecificationMapperProxy()->findById($unitSpecId);

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
                $this->getCourseImplementationMapperProxy()->save($ci);

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
     * @param Course\Implementation $courseImplementation
     * @throws MapperException
     */
    public function importModify(Course\Implementation $courseImplementation)
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
    public function setAssessmentMapper($assessmentMapper)
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
    public function setCourseImplementationFactory($unitImplementationFactory)
    {
        $this->courseImplementationFactory = $unitImplementationFactory;
        return $this;
    }


    /**
     * @return Course\ImplementationMapperProxy
     */
    public function getCourseImplementationMapperProxy()
    {
        return $this->courseImplementationMapperProxy;
    }


    /**
     * @param Course\ImplementationMapperProxy $courseImplementationMapperProxy
     * @return static
     */
    public function setCourseImplementationMapperProxy($courseImplementationMapperProxy)
    {
        $this->courseImplementationMapperProxy = $courseImplementationMapperProxy;
        return $this;
    }

}
