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

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;


class TaskRedir extends Base {

    /**
     * Regex
     *
     * @var string
     */
    private $regex = '/task\/start\/(\d+)/';

    /**
     * @var Router
     */
    private $router;


    /**
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }


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

        if(!is_numeric($id)){
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

        try{
            $route = $this->router->generate(
                'learningAssessment.getSummary',
                [
                    'assessmentId' => $task->getAssessmentModel()->getId(),
                    'studentId'    => $user->getId(),
                ]
            );
        } catch (RouteNotFound $e){
            return '';
        }

        return $route;
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
