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

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;

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
                        if (!$task->getRequiresConfirmation()) {
                            Task\InstanceCompletionMapper::getInstance()->markAsCompleted(
                                $task->getId(), $_SESSION['id']
                            );
                        }

                        $link = $task->getLink();

                        if (strlen($link) > 0) {
                            return $link;
                        }
                        else {
                            // Check permissions
                            /** @var UnitInstance $unit_instance */
                            $unit_instance = UnitInstanceMapper::getInstance()->findById($task->getUnitInstance()->getId());
                            // * Ensure that user is in this class
                            /** @var \Component_Instance $component */
                            $component_id   = $unit_instance->getComponent()->getId();
                            $component_list = \Component_InstanceMapper::getInstance()->findByInstanceId($component_id);
                            $component      = $component_list[$component_id];

                            return '/homepage/'.$component->getIdentifier().'/#component'.$component_id;
                        }

                        break;
                }
            }
            catch (\Schoolbox\Mapper\Exception $e) {
                return '';
            }
        }

        return '';
    }
}