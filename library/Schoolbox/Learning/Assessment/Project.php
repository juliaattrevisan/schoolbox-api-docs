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

use InvalidArgumentException;
use \Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
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
     * Project containers never show
     *
     * @param User $user
     * @return bool
     */
    public function canCreateResponse(User $user)
    {
        return false;
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
     * Check the dates set on this model are valid
     * @throws InvalidArgumentException if the dates are not validly set
     * @return void
     */
    public function assertValidDates()
    {
        /* Don't check dates if in course */
        if ($this->getBaseContext() instanceof UnitSpecification) {
            return;
        }

        $this->assertDateSequence([
            'publish' => $this->getPublishAt(),
            'due'     => $this->getDueAt()
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getFamilyName()
    {
        return 'project';
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

    /**
     * Projects have no concept of mark visibility - marks are always visible.
     *
     * @param User $viewingUser
     * @return bool
     */
    public function isFeedbackVisibleToUser(User $viewingUser)
    {
        return true;
    }

    /**
     * Clones this object in preparation for persisting as a copy of this object.
     *
     * @return static
     */
    public function cloneForCopy()
    {
        /** @var Project $clone */
        $clone = parent::cloneForCopy();
        $clonedAssessables = new GenericCollection(IAssessable::class);
        foreach($clone->getAssessables() as $assessable){
            $clonedAssessables->add($assessable->cloneForCopy());
        }
        $clone->setAssessables($clonedAssessables);
        return $clone;
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
     * @throws InvalidArgumentException
     * @return static
     */
    public function setContext(AssessmentContext $context = null)
    {
        if($context instanceof Project){
            throw new InvalidArgumentException('For now, a Project cannot reside inside another Project');
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

    /**
     * Retrieves the parent context of this project
     *
     * @return AssessmentContext
     */
    public function getBaseContext()
    {
        return $this->getContext();
    }

    /**
     * If alias close to due as these are not seperate
     * @return \Alaress_Date_Format
     */
    public function getCloseAt() {
        return $this->getDueAt();
    }


}
