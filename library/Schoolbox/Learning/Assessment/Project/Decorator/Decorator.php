<?php

/**
 * Decorator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Project\Decorator;

use \Schoolbox\Learning\Assessment\DecoratorInterface;
use \Schoolbox\Learning\Assessment\Project;

class Decorator implements DecoratorInterface
{
    use ProjectTrait;

    /**
     * @var int
     */
    protected $markType;

    /**
     * Decorator constructor.
     *
     * @param int $markType
     */
    public function __construct($markType)
    {
        $this->setMarkType($markType);
    }

    /**
     * @param Project $assessment
     */
    public function decorate($assessment)
    {
        /* TODO: Maybe replace these old classes?
         */
        $markType = new \Schoolbox\Assessment\Component\SubmissionBox\MarkType();
        $markType->setId($this->getMarkType());
        $assessment->setMarkType($markType);

        $markSchemeFactory = new \Schoolbox\Learning\MarkScheme\Factory();
        $markScheme = $markSchemeFactory->spawnMarkSchemeFor($assessment);
        $assessment->setMarkScheme($markScheme);
    }

    /**
     * @return int
     */
    protected function getMarkType()
    {
        return $this->markType;
    }

    /**
     * @param int $markType
     * @return $this
     */
    protected function setMarkType($markType)
    {
        $this->markType = $markType;
        return $this;
    }
}
