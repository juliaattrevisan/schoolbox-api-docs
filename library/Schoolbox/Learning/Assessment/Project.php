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
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\Summator\ISummator;
use Schoolbox\Resource\Folder\Model as Folder;


class Project extends AbstractAssessment implements AssessmentContext
{
    /**
     * @var GenericCollection|IAssessable[]
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

        $this->assessables = new GenericCollection(IAssessable::class);
        $this->setSummator($summator);
    }

    /**
     * @inheritdoc
     */
    public function canStudentCreateResponse(User $student)
    {
        // Project containers cannot have IResponses directly on them. Only their sub-assessments can.
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
        return $this->getSummator()->summate($this->assessables, $user, $publishedOnly);
    }


    /**
     * If a student requires marking on ANY project sub-assessment, the project requires marking
     *
     * @return int
     */
    public function getCountMarkingRequired()
    {
        $count = 0;

        foreach($this->allParticipants as $participant){
            foreach ($this->assessables as $subAssessable) {
                $student = $participant->getUser();
                if($subAssessable->isParticipant($student) && $subAssessable->getParticipant($student)->isMarkingRequired()){
                    $count++;
                    break;
                }
            }
        }
        return $count;
    }


    /**
     * Whether a project can be considered reviewComplete (ie GREEN CIRCLE) depends on:
     * * whether any sub-assessments require marking,
     * * the summation method, and
     * * the reviewComplete status of the project's sub-assessments
     *
     * If summation implies ALL, then all sub-assessments must be review-completed.
     * If summation implies ANY, then any sub-assessment needs be review-completed.
     *
     * @return int
     */
    public function getCountReviewComplete()
    {
        // Would a single sub-review indicate the student has been reviewed ?
        $reviewedIfAny = !$this->summator->isAggregator();

        // Depending on ALL/ANY, tracks count of reviewed or un-reviewed (avoids extra loop)
        $countReviewed   = 0;
        $countUnReviewed = 0;

        foreach($this->allParticipants as $participant) {
            foreach ($this->assessables as $subAssessable) {
                $student = $participant->getUser();
                if($subAssessable->isParticipant($student)){
                    $subParticipant    = $subAssessable->getParticipant($student);
                    $isReviewed        = $subParticipant->isReviewComplete();
                    $isMarkingRequired = $subParticipant->isMarkingRequired();

                    if($reviewedIfAny){
                        if($isReviewed)
                    }
                }
            }
        }

        return $reviewedIfAny
            ? $count
            : $this->allParticipants->count() - $count;
    }



    /**
     * Returns the number of Participants who have a published mark for this IAssessable.
     *
     * This is a student-perspective function. Has the student see they have received some grade/outcome ?
     *
     * In the case of project, a student is marked if they have received a mark for ANY
     * assessment inside the project.
     */
    public function getCountMarkedByTeacher()
    {
        $count = 0;

        foreach($this->allParticipants as $participant){
            foreach ($this->assessables as $subAssessable) {
                $student = $participant->getUser();
                if($subAssessable->isParticipant($student) && $subAssessable->getParticipant($student)->isMarkedByTeacher()){
                    $count++;
                    break;
                }
            }
        }
        return $count;
    }


    /**
     * A student is considered to have 'completed' a project if they have completed some combination of sub-assessments.
     *
     * The combination depends on the summation method:
     * * MAX: implies optionality         - student needs to complete ANY sub-assessment
     * * MIN: implies optionality         - student needs to complete ANY sub-assessments
     * * AVG: implies expected to do all  - student needs to complete ALL sub-assessment
     *
     * @return int
     */
    public function getCountResponseComplete()
    {
        // Would a single sub-completion indicate the student requires no further action ?
        $completeIfAny = !$this->summator->isAggregator();

        // Depending on ALL/ANY, tracks count of complete or incomplete (avoids extra loop)
        $count = 0;

        foreach($this->allParticipants as $participant) {
            foreach ($this->assessables as $subAssessable) {
                $student = $participant->getUser();
                if($subAssessable->isParticipant($student)){
                    $isComplete = $subAssessable->getParticipant($student)->isResponseComplete();

                    if($completeIfAny == $isComplete){
                        $count++;
                        break;
                    }
                }
            }
        }

        return $completeIfAny
            ? $count
            : $this->allParticipants->count() - $count;
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
        $clone->assessables = new GenericCollection(IAssessable::class);
        return $clone;
    }

    /* Getters/Setters
     */

    /**
     * @return GenericCollection|IAssessable[]
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param GenericCollection|IAssessable[] $assessables
     * @return self
     */
    public function setAssessables(GenericCollection $assessables)
    {
        $this->assessables->replaceWith($assessables);
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
}
