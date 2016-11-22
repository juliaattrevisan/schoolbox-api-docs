<?php
/**
 * Course component for a class.
 * Before a course is selected, it contains a selector for the course - after
 * this, it contains a list of units and tasks within the course.
 *
 * This is the Course Outline component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Component\Homepage\Outline;


use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Learning\Course\Implementation;
use Schoolbox\Learning\Course\ImplementationMapperProxy;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;
use Schoolbox\Collection;
use \Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Learning\Assessment\Redirect;
use Component_Homepage_ControllerAbstract;
use Schoolbox\User;

class Controller extends Component_Homepage_ControllerAbstract
{

    /**
     * A list of units within this course.
     *
     * @var Implementation
     */
    protected $courseImplementation;

    /** @var int[] */
    protected $accessList = [];

    /** @var Folder\Model */
    protected $originalCourse;

    /**
     * @var Collection|User
     */
    protected $userCollection;

    /**
     * A list of students who have completed the tasks within the course.
     *
     * @var Collection|Task\Completion[]
     */
    protected $completion_list;

    /**
     * A list of students for this category.
     *
     * @var array
     */
    protected $student_list = array();

    /**
     * @var bool
     */
    protected $readOnly;


    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }
        $this->readOnly  = $this->getContentPermission() < UserAccess::ACCESS_WRITE;

        // Get the list of units for this course, as well as who has actually
        // completed each unit.

        /** @var ImplementationMapperProxy $implementationProxy */
        $implementationProxy = $this->getSitePage()->getDi()->get('courseImplementationDataSource');
        /** @var AssessmentMapperProxy $assessmentProxy */
        $assessmentProxy = $this->getSitePage()->getDi()->get('assessmentDataSource');

        $implementationId = $this->getConfig('importedCourse');

        try{
            $this->courseImplementation = $implementationProxy->findById($implementationId);


            // Try to get original course folder from each unit in course.
            foreach ($this->courseImplementation->getUnits() as $unit) {

                $unitId = $unit->getSpecification()->getFolder()->getId();
                $this->accessList[$unitId] = \Schoolbox\Permission\Access\Access::getInstance()->getFolderAccess($this->getUser(), [$unitId]);

                $assessables = $assessmentProxy->findAllByUnitImplementationId($unit->getId());
                $unit->setAssessables($assessables);
            }
            foreach ($this->courseImplementation->getUnits() as $unit) {
                try {
                    $this->originalCourse = Folder\Mapper::getInstance()->findCheaplyById(
                        Folder\Mapper::getInstance()->findCheaplyById($unit->getSpecification()->getFolder()->getId())->getParent()->getId()
                    );
                    // I have a course folder, so:
                    break;
                } catch (\Schoolbox\Mapper\Exception $e) {
                    // I don't have a course folder, so:
                    continue;
                }


            }

            $this->userCollection = \Schoolbox\Permission\Access\UserAccessMapper::getInstance()->findStudentsInCategory($this->getContext());


            $this_student_id = null;
            if ($this->readOnly) {
                $this_student_id = $this->getUser()->getId();
            }
            /*$this->completion_list = Task\CompletionMapper::getInstance()->findLatestForList(
              $task_list, $this_student_id
            );*/

        } catch (MapperException $e) {
            if ($this->readOnly) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle)
    {

        //$this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? trim($this->getConfig('componentname')) : $this->getComponentName());


        $data = [
            'readOnly'  => $this->readOnly,
            'context'   => $this->getContext(),
            'course'    => $this->originalCourse,
            'courseImplementation'     => $this->courseImplementation,
            'studentCount' => count($this->userCollection),
            'componentInstance' => $this->getInstance(),
            'returnUrl' => Redirect::encode('/homepage/'.$this->getContext()->getId().'#component'.$this->getInstance()->getId())
            ];



        return $this->renderTwigTemplate(
            $templateHandle,
            '@learningAssessment/outline/component.twig',
            $data,
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );
    }
}
