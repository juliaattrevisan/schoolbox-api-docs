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
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
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
            /** @var User $user */
            $user = $_SESSION['user'];
            try {

                // This should populate unit instance, task, component and all that too.
                $task = Task\InstanceMapper::getInstance()->findById($id);

                switch ($task->getType()) {

                    // redirect to due work item
                    // When the due work item is complete, mark task as complete.
                    case Task::TYPE_DUE_WORK:
                        return '/submission/submissionHistory/'.$task->getSubmissionBox()->getId().'/'.$user->getId();
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
                        $component      = Component_InstanceMapper::getInstance()->findByInstanceId($component_id);

                        // Only proceed to marking if the logged in user is actually in this folder

                        if (!$task->getRequiresConfirmation()) {

                            $folder = FolderMapper::getInstance()->findById($component->getIdentifier());

                            foreach (FolderMapper::getInstance()->findForUser($user) as $userFolder) {
                                if ($userFolder->getId() == $folder->getId()) {
                                    Task\CompletionMapper::getInstance()->markAsCompleted(
                                        $task->getId(),
                                        $user->getId()
                                    );
                                    break;
                                }
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