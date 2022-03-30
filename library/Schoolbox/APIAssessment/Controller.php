<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIAssessment;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

class Controller extends Base
{


    protected AssessmentMapperProxy $mapperProxy;
    /**
     * Controller constructor.
     *
     */
    public function __construct(AssessmentMapperProxy $mapperProxy)
    {
        $this->mapperProxy = $mapperProxy;
    }

    /**
     * Returns an assessment object
     *
     * @api /api/assessment/{assessmentId}
     * @link TODO:
     */
    public function apiAssessmentGet(IAssessable $assessment)
    {
        //get all assessment info for object

        $time_start = microtime(true);
        /**
         * build the whole thing
         */
        $output = $this->getAssessment($assessment);
        $time_end = microtime(true);
        $execution_time = ($time_end - $time_start) / 60;
        $this->response->content->set(
            $this->buildJsonOutput(
                array_merge(
                    ['start' => $time_start, 'end' => $time_end, 'diff' => $execution_time],
                    $output
                )
            )
        );
    }

    /**
     * dummy endpoint to grab all assignments for load testing
     */
    public function apiAssessmentGetAll()
    {
        $time_start = microtime(true);

        /**
         * grab list of all assignments in the system
         */
        $mapper = UserMapper::getInstance()->getDatabase();
        $query = $mapper->query("SELECT id from assessment");
        $output = $query->fetchAll();

        /**
         * break the system by getting all assessments and dumping contents
         */
        $assessments = [];
        $assessmentIds = [];
        foreach ($output as $item) {
            $assessmentIds[] = $item['id'];
            try {
                $assessments[] = $this->getAssessment($this->mapperProxy->findByIdForReview($item['id']));
            } catch (MapperException $e) {
                //noop
            }
        }

        $time_end = microtime(true);
        $execution_time = ($time_end - $time_start) / 60;
        $this->response->content->set(
            $this->buildJsonOutput(
                array_merge(
                    ['start' => $time_start, 'end' => $time_end, 'diff' => $execution_time],
                    $assessments
                )
            )
        );
    }

    /**
     * build the assessment object
     */
    public function getAssessment(IAssessable $assessment): array
    {
        switch (AssessmentType::objectToString($assessment)) {
            case 'quiz':
                return (new \Schoolbox\Learning\Assessment\Quiz\ModalToArray())($assessment);
            case 'dueWork':
                return (new \Schoolbox\Learning\Assessment\DueWork\ModalToArray())($assessment);
            case 'lti':
                return (new \Schoolbox\Learning\Assessment\Lti\ModalToArray())($assessment);
            case 'project':
                return (new \Schoolbox\Learning\Assessment\Project\ModalToArray())($assessment);
            case 'task':
                return (new \Schoolbox\Learning\Assessment\Task\ModalToArray())($assessment);
            default:
                return (new \Schoolbox\Learning\Assessment\ModelToArray())($assessment);
        }
    }
}
