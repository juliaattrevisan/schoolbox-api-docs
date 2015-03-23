<?php
/**
 * TaskTypes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\Collection;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;

class TaskTypes {

    /**
     * A Collection of task types
     *
     * @var Collection
     */
    private $allTaskTypes = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->setAllTaskTypes(new Collection());

        $this->process();
    }

    /**
     * Process
     */
    private function process() {
        $workTypeMapper = new WorkTypeMapper();
        $this->setAllTaskTypes($workTypeMapper->findAll());
    }

    /**
     * Set all task types
     *
     * @param \Schoolbox\Collection $allTaskTypes
     */
    private function setAllTaskTypes($allTaskTypes)
    {
        $this->allTaskTypes = $allTaskTypes;
    }

    /**
     * Get all task types
     *
     * @return \Schoolbox\Collection
     */
    public function getAllTaskTypes()
    {
        return $this->allTaskTypes;
    }
}