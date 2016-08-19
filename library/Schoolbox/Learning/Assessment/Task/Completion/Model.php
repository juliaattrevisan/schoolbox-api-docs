<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Task\Completion;

use \Schoolbox\Learning\Assessment\AbstractResponse;

class Model extends AbstractResponse
{

    /**
     * Completions are submitted at the updatedAt value
     * in case the Completion is modified by the Student
     *
     * @return \Alaress_Date_Format
     */
    public function getSubmittedAt()
    {
        return $this->getUpdatedAt();
    }
}