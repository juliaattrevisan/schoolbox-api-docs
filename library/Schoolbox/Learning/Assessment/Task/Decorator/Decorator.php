<?php

/**
 * Decorator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Task\Decorator;

use \Schoolbox\Learning\Assessment\DecoratorInterface;
use \Schoolbox\Learning\Assessment\Task\Model as Task;

class Decorator implements DecoratorInterface
{
    use TaskTrait;

    /**
     * Decorator constructor.
     *
     * @param $hasSelfAssessed
     * @param $url
     */
    public function __construct($hasSelfAssessed, $url)
    {
        $this->setHasSelfAssessed($hasSelfAssessed);
        $this->setUrl($url);
    }

    /**
     * @param Task $assessment
     */
    public function decorate($assessment)
    {
        if ($this->getHasSelfAssessed()) {
            $assessment->selfAssessed();
        }
        else {
            $assessment->teacherAssessed();
        }

        $assessment->setUrl($this->getUrl());
    }
}