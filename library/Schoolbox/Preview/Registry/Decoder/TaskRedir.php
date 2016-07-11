<?php
/**
 * TaskRedir
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Registry\Decoder;

use Component_InstanceMapper;
use Group;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserMapper;

class TaskRedir extends Base {

    /**
     * Regex
     *
     * @var string
     */
    protected $regex = '/task\/start\/(\d+)/';

    /**
     * Distill a url from a Task instance
     *
     * @param $url
     * @return mixed|void
     */
    public function decode($url) {
        if ($id = $this->isThisDecoder($url)) {
            try {

                // This should populate unit instance, task, component and all that too.
                $task = Task\InstanceMapper::getInstance()->findById($id);

                switch ($task->getType()) {

                    // redirect to due work item
                    // When the due work item is complete, mark task as complete.
                    case Task::TYPE_DUE_WORK:
                        return '/submission/submissionHistory/'.$task->getSubmissionBox()->getId().'/'.$_SESSION['id'];
                        break;

                    // redirect to online assessment item
                    // When it's complete, the mark task is marked as complete.
                    case Task::TYPE_ONLINE_ASSESS:
                        return '/assess/intro/'.$task->getAssessment()->getId().'/';
                        break;

                    // Mark this item as complete.
                    // Redirect to the link if there is one, otherwise redirect back to the
                    // homepage this is on.
                    default:

                        // Make sure the user is actually a student in the class (either via group/class list) before
                        // we mark it as completed.

                        /** @var UnitInstance $unit_instance */
                        $unit_instance = UnitInstanceMapper::getInstance()->findById($task->getUnitInstance()->getId());

                        /** @var \Component_Instance $component */
                        $component_id   = $unit_instance->getComponent()->getId();
                        $component_list = Component_InstanceMapper::getInstance()->findByInstanceId($component_id);
                        $component      = $component_list[$component_id];

                        $folder = FolderMapper::getInstance()->findById($component->getIdentifier());

                        $classStudentIdList = array();
                        $groupStudentIdList = array();

                        // Get students by class code

                        $config = new ConfigLoader();

                        if ($config->get('external_enable')) {
                            $codes = explode(",", $folder->getCodeString());

                            foreach ($codes as $code) {
                                $class_handler = new \External_Class();
                                $classStudentList = $class_handler->getStudents($code);

                                if (is_array($classStudentList)) {
                                    $externalIdList = array_map(
                                        function ($student) {
                                            return $student['synergy_id'];
                                        },
                                        $classStudentList
                                    );

                                    $list = UserMapper::getInstance()
                                        ->findByColumn('synergy_id', $externalIdList)
                                        ->getColumnValues('id');

                                    $classStudentIdList = array_merge($classStudentIdList, $list);
                                }
                            }
                        }

                        // Get by group involvement

                        $group_handler = new Group();
                        $groupStudentList = $group_handler->getMemberList($folder->getId());
                        if (is_array($groupStudentList)) {
                            $groupStudentIdList = array_unique(
                                array_map(
                                    function ($student) {
                                        return $student['uid'];
                                    },
                                    $groupStudentList
                                )
                            );
                        }

                        // Merge them together

                        $userCollection = array_merge($classStudentIdList, $groupStudentIdList);

                        // Only proceed to marking if the logged in user is actually in the list of users

                        if (in_array($_SESSION["id"], $userCollection)) {
                            if (!$task->getRequiresConfirmation()) {
                                Task\InstanceCompletionMapper::getInstance()->markAsCompleted(
                                    $task->getId(), $_SESSION['id']
                                );
                            }
                        }

                        $link = $task->getLink();

                        if (strlen($link) > 0) {
                            return $link;
                        }
                        else {
                            return '/homepage/'.$component->getIdentifier().'/#component'.$component_id;
                        }

                        break;
                }
            }
            catch (MapperException $e) {
                return '';
            }
        }

        return '';
    }
}