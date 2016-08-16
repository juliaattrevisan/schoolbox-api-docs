<?php
/**
 * Project
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessable;

use \Schoolbox\Collection;
use \Schoolbox\Learning\Summator\ISummator;

class Project extends Base implements IAssessable
{

    /**
     * @var Collection
     */
    protected $assessables;

    /**
     * Summation strategy
     *
     * @var ISummator
     */
    protected $summator;

    /**
     * Project constructor.
     *
     * @param ISummator $summator
     */
    public function __construct(ISummator $summator)
    {
        $this->setAssessables(new Collection());
        $this->setSummator($summator);
    }

    /**
     * Calculate the final mark for this assessable
     *
     * Projects use Summator strategies to determine final marks
     * for a Collection of Assessable instances
     */
    public function calculateMark()
    {
        return $this->getSummator()->summate($this->getAssessables());
    }

    /* Getters/Setters
     */

    /**
     * @return Collection
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param Collection $assessables
     */
    public function setAssessables($assessables)
    {
        $this->assessables = $assessables;
    }

    /**
     * Get summation strategy
     *
     * @return ISummator
     */
    public function getSummator()
    {
        return $this->summator;
    }

    /**
     * Set a summation strategy
     *
     * @param ISummator $summator
     */
    public function setSummator($summator)
    {
        $this->summator = $summator;
    }
}