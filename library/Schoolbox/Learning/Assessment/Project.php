<?php
/**
 * Project
 *
 * Composite Design Pattern
 *
 * This contains a Collection of IAssessables such as Due Work,
 * Quiz and Tasks.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\Summator\ISummator;

class Project extends AbstractAssessment
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
     * Number of decimal places used in a Normalised Mark value
     *
     * @var int
     */
    protected $precision = 11;

    /**
     * Project constructor.
     *
     * @param ISummator $summator
     */
    public function __construct(ISummator $summator)
    {
        /* I know Dr hates hook methods, so do this instead
         */
        parent::__construct();

        $this->setAssessables(new Collection());
        $this->setSummator($summator);
    }

    /**
     * @inheritDoc
     */
    public function getHumanReadableMark(User $user, $publishedOnly)
    {
        // TODO: Implement getHumanReadableMark() method. This method gets the output from getNormalisedMark, then pipes that into the mark scheme (which doesn't yet exist on this class)

        return 'TODO: Pass NormalisedMark value through MarkScheme';
    }

    /**
     * Calculate the final mark for this IAssessable, for a particular User
     *
     * Projects use Summator strategies to determine final marks
     * for a Collection of Assessable instances
     *
     * @inheritDoc
     */
    public function getNormalisedMark(User $user, $publishedOnly)
    {
        $val = $this->getSummator()->summate($this->getAssessables(), $user, $publishedOnly);

        if (is_null($val)) {
            return null;
        }

        return number_format($val, $this->getPrecision());
    }

    /**
     * Calculate how many IAssessable instances are in this Project
     *
     * @return int
     */
    public function numAssessables()
    {
        return $this->getAssessables()->count();
    }

    /**
     * @inheritdoc
     */
    public function numParticipants()
    {
        $denominator = 0;

        foreach ($this->getAssessables() as $currentAssessable) {
            $denominator += $currentAssessable->getAllParticipants()->count();
        }

        return $denominator;
    }

    /**
     * @inheritdoc
     */
    public function numMarked()
    {
        $num = 0;

        /** @var IAssessable $currentAssessable */
        foreach ($this->getAssessables() as $currentAssessable) {

            /** @var Participant $currentParticipant */
            foreach ($currentAssessable->getAllParticipants() as $currentParticipant) {
                if ($currentParticipant->isAssessableMarked($currentAssessable)) {
                    $num++;
                }
            }
        }

        return $num;
    }

    /**
     * @inheritdoc
     */
    public function calculatePercentageMarked()
    {
        if ($this->numAssessables()) {
            return 100 * ($this->numMarked() / $this->numParticipants());
        }

        return 0;
    }

    /**
     * @inheritdoc
     */
    public function numPublished()
    {
        $num = 0;

        /** @var IAssessable $currentAssessable */
        foreach ($this->getAssessables() as $currentAssessable) {

            /** @var Participant $currentParticipant */
            foreach ($currentAssessable->getAllParticipants() as $currentParticipant) {
                if ($currentParticipant->isAssessablePublished($currentAssessable)) {
                    $num++;
                }
            }
        }

        return $num;
    }

    /**
     * @inheritdoc
     */
    public function calculatePercentagePublished()
    {
        if ($this->numAssessables()) {
            return 100 * ($this->numPublished() / $this->numParticipants());
        }

        return null;
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getTypeName()
    {
        return 'Project';
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
     * @return self
     */
    public function setAssessables($assessables)
    {
        $this->assessables = $assessables;
        return $this;
    }

    /**
     * @return ISummator
     */
    public function getSummator()
    {
        return $this->summator;
    }

    /**
     * @param ISummator $summator
     * @return $this
     */
    public function setSummator($summator)
    {
        $this->summator = $summator;
        return $this;
    }

    /**
     * @return int
     */
    public function getPrecision()
    {
        return $this->precision;
    }
}