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
use Schoolbox\Assessment\Course\TaskMapper;
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
    private $regex = '/task\/start\/(\d+)/';

    /**
     * Distill a url from a Task instance
     *
     * @param string $url
     * @return string
     */
    public function decode($url)
    {
        // Try to decode URL using this decoder's regex
        $id = $this->isThisDecoder($url);

        if(false === $id){
            return '';
        }

        /** @var User $user */
        $user = $_SESSION['user'];

        try {
            /** @var Task $task */
            $task = TaskMapper::getInstance()->findById($id);
        } catch (MapperException $e){
            return '';
        }

        // Just redirect user to assessment summary page
        return "/learning/assessments/{$task->getAssessmentModel()->getId()}/{$user->getId()}";
    }


    /**
     * Get the regex to use when deciding which decoder to use
     *
     * @return string
     */
    protected function getRegex()
    {
        return $this->regex;
    }
}
