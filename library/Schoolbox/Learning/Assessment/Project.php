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
     * Whether a project requires a student response depends on:
     * * the summation method, and
     * * the responseRequired status of the project's sub-assessments
     *
     * If summation implies ALL, then if ANY sub-assessment requires a response, the project requires a response
     * If summation implies ANY, then if ANY sub-assessment not not require a response, the project does not require a response.
     *
     * @return int
     */
    public function getCountResponseRequired()
    {
        // Would a single sub-review indicate the student has been reviewed ?
        $respondedIfAny = $this->summator->impliesOptionality();

        // Track either count, depending on whether requires ALL or ANY sub-assessment to match
        $countResponseRequired    = 0;
        $countResponseNotRequired = 0;

        foreach($this->allParticipants as $participant) {
            $student                      = $participant->getUser();
            $studentFoundInSubAssessments = false;

            foreach ($this->assessables as $subAssessable) {

                if($subAssessable->isParticipant($student)) {
                    $studentFoundInSubAssessments = true;
                    $subParticipant               = $subAssessable->getParticipant($student);
                    $responseRequired             = $subParticipant->isResponseRequired();

                    // Does the project require all sub-assessables to be responded to, and this sub-assessment requires a response?
                    if (!$respondedIfAny && $responseRequired) {
                        // Project requires a response
                        $countResponseRequired++;
                        break;
                    }
                    // Else does the project require any sub-assessable to be responded to, and this sub-assessment does not require a response?
                    elseif ($respondedIfAny && !$responseRequired) {
                        // Project does not require a response
                        $countResponseNotRequired++;
                        break;
                    }
                }
            }

            // Logically, if student is a participant in a project, they must be a participant in at least 1 project sub-assessable
            if(!$studentFoundInSubAssessments){
                throw new \DomainException("Project (a.id: {$this->getId()}) participant (u.id: {$student->getId()}) not found in project's sub-assessments");
            }
        }

        return $respondedIfAny
            ? $this->allParticipants->count() - $countResponseNotRequired
            : $countResponseRequired;
    }



    /**
     * If a student requires marking on ANY project sub-assessment, the project requires marking.
     *
     * @return int
     */
    public function getCountFeedbackRequired()
    {
        $count = 0;

        foreach($this->allParticipants as $participant){
            $student                      = $participant->getUser();
            $studentFoundInSubAssessments = false;

            foreach ($this->assessables as $subAssessable) {

                if($subAssessable->isParticipant($student)){
                    $studentFoundInSubAssessments = true;
                    if($subAssessable->getParticipant($student)->isFeedbackRequired()){
                        $count++;
                        break;
                    }
                 }
            }

            // Logically, if student is a participant in a project, they must be a participant in at least 1 project sub-assessable
            if(!$studentFoundInSubAssessments){
                throw new \DomainException("Project (a.id: {$this->getId()}) participant (u.id: {$student->getId()}) not found in project's sub-assessments");
            }
        }
        return $count;
    }


    /**
     * Whether a project can be considered reviewComplete (ie GREEN CIRCLE) depends on:
     * * the summation method, and
     * * the reviewComplete status of the project's sub-assessments, and
     * * ALL sub-assessments not requiring feedback
     *
     * We need to examine all sub-assessments before we can conclude the project's overall state
     *
     * If any sub-assessment requires feedback, the project is NOT review-completed
     * If summation implies ALL and any sub-assessment is not review-complete, then project is not review-complete
     * If summation implies ANY and any sub-assessment is review-complete, then project is review-complete
     *
     * @return int
     */
    public function getCountReviewComplete()
    {
        // Would a single sub-assessment completion imply the project is complete ?
        $requiresAny = $this->summator->impliesOptionality();
        $requiresAll = !$requiresAny;
        $countReviewComplete = 0;

        foreach($this->allParticipants as $participant) {
            $student                      = $participant->getUser();
            $studentFoundInSubAssessments = false;
            $projectResultDetermined      = false;
            $subReviewCompleteFound       = false;

            foreach ($this->assessables as $subAssessable) {

                if($subAssessable->isParticipant($student)) {
                    $studentFoundInSubAssessments = true;
                    $subParticipant               = $subAssessable->getParticipant($student);
                    $feedbackRequired             = $subParticipant->isFeedbackRequired();
                    $reviewComplete               = $subParticipant->isReviewComplete();

                    if($reviewComplete){
                        $subReviewCompleteFound = true;
                    }

                    // Does sub-assessment require feedback ? Or does project require all to be review-complete and the sub-assessment in not review-complete?
                    if ($feedbackRequired || ($requiresAll && !$reviewComplete)) {
                        // Conclude that project is not review-complete
                        $projectResultDetermined = true;
                        break;
                    }
                }
            }

            // Logically, if student is a participant in a project, they must be a participant in at least 1 project sub-assessable
            if(!$studentFoundInSubAssessments){
                throw new \DomainException("Project (a.id: {$this->getId()}) participant (u.id: {$student->getId()}) not found in project's sub-assessments");
            }

            // Were we not able to conclude the project was not review-complete by examining the sub-assessments individually ?
            if(!$projectResultDetermined){
                // Does the project require just one sub-assessment to be review-completed, and 1 or more were review-completed?
                if($requiresAny && $subReviewCompleteFound){
                    // We can conclude the project is review-completed.
                    $countReviewComplete++;
                }
            }
        }

        return $countReviewComplete;
    }


    /**
     * This treats the project holistically as an atomic entity.
     *
     * If a project requires ALL sub-assessments to be completed, then student has only
     * submitted a response to the project once they have submitted to ALL sub-assessments.
     *
     * Else if a project requires any sub-assessment to be completed, then student has submitted once
     * they have submitted a response to ANY sub-assessment.
     *
     * @return int
     */
    public function getCountHasSubmittedResponse()
    {
        // Would a single sub-assessment completion imply the project is complete ?
        $requiresAny            = $this->summator->impliesOptionality();
        $requiresAll            = !$requiresAny;
        $countResponseSubmitted = 0;

        foreach($this->allParticipants as $participant) {
            $student                      = $participant->getUser();
            $studentFoundInSubAssessments = false;
            $projectResultDetermined      = false;
            $hasNotSubmittedSubResponse   = false;

            foreach ($this->assessables as $subAssessable) {

                if ($subAssessable->isParticipant($student)) {
                    $studentFoundInSubAssessments = true;
                    $subParticipant               = $subAssessable->getParticipant($student);
                    $hasSubmittedResponse         = $subParticipant->hasSubmittedResponse();

                    // Has student not submitted to the sub-assessment ?
                    if(!$hasSubmittedResponse){
                        $hasNotSubmittedSubResponse = true;
                    }

                    // Does project require all sub-assessment to be completed, and student didn't respond ?
                    if($requiresAll && !$hasSubmittedResponse){
                        // We can conclude they have not responded to the project
                        $projectResultDetermined = true;
                        break;
                    }
                    // Else does project require any, and student has responded ?
                    elseif ($requiresAny && $hasSubmittedResponse) {
                        // We can conclude they have responded to the project
                        $projectResultDetermined = true;
                        $countResponseSubmitted++;
                        break;
                    }
                }
            }

            // Logically, if student is a participant in a project, they must be a participant in at least 1 project sub-assessable
            if(!$studentFoundInSubAssessments){
                throw new \DomainException("Project (a.id: {$this->getId()}) participant (u.id: {$student->getId()}) not found in project's sub-assessments");
            }

            // Were we unable to determine a result by examining the sub-assessments individually ?
            if(!$projectResultDetermined){
                // Does project require all, and we didn't find a sub-assessment that the student did not respond to ?
                if($requiresAll && !$hasNotSubmittedSubResponse){
                    // We can conclude the student has responded to the project
                    $countResponseSubmitted++;
                }
            }
        }
        return $countResponseSubmitted;
    }

    /**
     * This treats the project holistically as an atomic entity.
     *
     * If a project requires ALL sub-assessments to be completed, then teacher has only
     * given feedback to the project once they have given feedback to ALL sub-assessments.
     *
     * Else if a project requires any sub-assessment to be completed, then teacher has given feedback once
     * they have given feedback to ANY sub-assessment.
     *
     * @return int
     */
    public function getCountHasPublishedFeedback()
    {
        // Would a single sub-assessment completion imply the project is complete ?
        $requiresAny            = $this->summator->impliesOptionality();
        $requiresAll            = !$requiresAny;
        $countPublishedFeedback = 0;

        foreach($this->allParticipants as $participant) {
            $student                      = $participant->getUser();
            $studentFoundInSubAssessments = false;
            $projectResultDetermined      = false;
            $hasNotPublishedFeedback      = false;

            foreach ($this->assessables as $subAssessable) {

                if ($subAssessable->isParticipant($student)) {
                    $studentFoundInSubAssessments = true;
                    $subParticipant               = $subAssessable->getParticipant($student);
                    $hasPublishedFeedback         = $subParticipant->hasPublishedFeedback();

                    // Has student not received published feedback for the sub-assessment ?
                    if(!$hasPublishedFeedback){
                        $hasNotPublishedFeedback = true;
                    }

                    // Does project require all sub-assessment to be completed, and student hasn't received published feedback ?
                    if($requiresAll && !$hasPublishedFeedback){
                        // We can conclude they have not received published feedback for the project
                        $projectResultDetermined = true;
                        break;
                    }
                    // Else does project require any, and student has received published feedback for a sub-assessment ?
                    elseif ($requiresAny && $hasPublishedFeedback) {
                        // We can conclude they have received published feedback to the project
                        $projectResultDetermined = true;
                        $countPublishedFeedback++;
                        break;
                    }
                }
            }

            // Logically, if student is a participant in a project, they must be a participant in at least 1 project sub-assessable
            if(!$studentFoundInSubAssessments){
                throw new \DomainException("Project (a.id: {$this->getId()}) participant (u.id: {$student->getId()}) not found in project's sub-assessments");
            }

            // Were we unable to determine a result by examining the sub-assessments individually ?
            if(!$projectResultDetermined){
                // Does project require all, and we didn't find a sub-assessment that the student did not received published feedback for ?
                if($requiresAll && !$hasNotPublishedFeedback){
                    // We can conclude the student has received published feedback for the project
                    $countPublishedFeedback++;
                }
            }
        }
        return $countPublishedFeedback;
    }

    /**
     * This treats the project holistically as an atomic entity.
     *
     * A project has a mark if any of its sub-assessments have marks.
     *
     * @return int
     */
    public function getCountHasMark()
    {
        $countHasMark = 0;

        foreach($this->allParticipants as $participant) {
            $student                      = $participant->getUser();
            $studentFoundInSubAssessments = false;

            foreach ($this->assessables as $subAssessable) {

                if ($subAssessable->isParticipant($student)) {
                    $studentFoundInSubAssessments = true;
                    $subParticipant               = $subAssessable->getParticipant($student);
                    $hasMark                      = $subParticipant->hasMark();

                    // Does student have a mark for the sub-assessment ?
                    if($hasMark){
                        $countHasMark++;
                        break;
                    }
                }
            }

            // Logically, if student is a participant in a project, they must be a participant in at least 1 project sub-assessable
            if(!$studentFoundInSubAssessments){
                throw new \DomainException("Project (a.id: {$this->getId()}) participant (u.id: {$student->getId()}) not found in project's sub-assessments");
            }
        }
        return $countHasMark;
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
