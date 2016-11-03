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

/* TODO: Move into Learning/Assessment/Project/Model
 */
namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;
use Schoolbox\Learning\AssessmentContext;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\Summator\ISummator;
use Schoolbox\Resource\Folder\Model as Folder;

class Project extends AbstractAssessment implements AssessmentContext
{
    /**
     * @var Collection|IAssessable[]
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
    public function __construct(ISummator $summator = null)
    {
        /* I know Dr hates hook methods, so do this instead
         */
        parent::__construct();

        $this->setAssessables(new Collection());
        $this->setSummator($summator);
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
        // Get the Assessables that the Specified User has potential marks for
        $markedAssessables = new Collection();

        foreach($this->getAssessables() as $assessable){
            foreach($assessable->getAllParticipants() as $participant){
                if($participant->getUser()->getId() == $user->getId()){
                    $isMarked = $publishedOnly
                        ? $participant->isAssessablePublished()
                        : $participant->isAssessableMarked();
                    if($isMarked){
                        $markedAssessables[$assessable->getId()] = $assessable;
                    }
                }
            }
        }

        return $this->getSummator()->summate($markedAssessables, $user, $publishedOnly);
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
            $num += $currentAssessable->numMarked();
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
                if ($currentParticipant->isAssessablePublished()) {
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
     * @return Collection|IAssessable[]
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param Collection|IAssessable[] $assessables
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
    public function setSummator(ISummator $summator = null)
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

    /**
     * @param AssessmentContext $context
     * @throws \InvalidArgumentException
     * @return static
     */
    public function setContext(AssessmentContext $context = null)
    {
        if($context instanceof Project){
            throw new \InvalidArgumentException('For now, a Project cannot reside inside another Project');
        }
        parent::setContext($context);
        return $this;
    }

    /**
     * Retrieves the folder that any Assessable objects within this object
     * belongs within.
     *
     * @return Folder
     */
    public function getFolder()
    {
        return $this->getContext()->getFolder();
    }

    /** Retreives the parent context of this project
     *
     * @return AssessmentContext
     */
    public function getBaseContext()
    {
        return $this->getContext();
    }

}
