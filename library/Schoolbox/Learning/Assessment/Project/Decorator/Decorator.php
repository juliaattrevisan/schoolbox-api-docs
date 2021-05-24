<?php

/**
 * Decorator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Project\Decorator;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Summator\Factory as SummatorFactory;

class Decorator implements DecoratorInterface
{
    use ProjectTrait;

    /**
     * @var int
     */
    protected $markType;

    /**
     * @var int
     */
    protected $gradingScheme;

    /**
     * @var string
     */
    protected $summator;

    /**
     * Decorator constructor.
     *
     * @param int $markType
     * @param int $gradingScheme
     * @param string $summator
     */
    public function __construct($markType, $gradingScheme, $summator)
    {
        $this->setMarkType($markType);
        $this->setGradingScheme($gradingScheme);
        $this->setSummator($summator);
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

        $gradingGroup = (new GradeGroup())->setId($this->getGradingScheme());
        $assessment->setGradeGroup($gradingGroup);

        $markSchemeFactory = new \Schoolbox\Learning\MarkScheme\Factory();
        $markScheme = $markSchemeFactory->spawnMarkSchemeFor($assessment);
        $assessment->setMarkScheme($markScheme);

        $assessment->setSummator((new SummatorFactory($this->getSummator()))->produce());
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

    /**
     * @return int
     */
    protected function getGradingScheme()
    {
        return $this->gradingScheme;
    }

    /**
     * @param int $gradingScheme
     * @return $this
     */
    protected function setGradingScheme($gradingScheme)
    {
        $this->gradingScheme = $gradingScheme;
        return $this;
    }

    /**
     * @return string
     */
    protected function getSummator()
    {
        return $this->summator;
    }

    /**
     * @param string $summator
     * @return $this
     */
    protected function setSummator($summator)
    {
        $this->summator = $summator;
        return $this;
    }
}
