<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback\SubSource;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Mapper\AbstractMapperProxy;
use Schoolbox\User;

/**
 * Base class for feedback sub-sources.
 */
abstract class AbstractSubSource implements IFeedbackDataSource
{
    /**
     * @var AbstractMapperProxy
     */
    protected $mapperProxy;

    /**
     * The field of the mapper proxy's table which contains the student ID.
     *
     * @var string
     */
    protected $studentField;

    /**
     * Constructor
     *
     * @param AbstractMapperProxy $mapperProxy
     */
    public function __construct(AbstractMapperProxy $mapperProxy)
    {
        $this->mapperProxy = $mapperProxy;
    }

    /**
     * @param IAssessable $assessment
     * @param User        $student If set, only return this student's responses
     * @return Collection|IFeedback[]
     */
    public function findForAssessment(IAssessable $assessment, User $student = null)
    {
        if ($this->recognizes($assessment)) {
            // Only fetch for student if provided
            $columns = ['assessment_id' => $assessment->getId(),];
            if ($student instanceof User) {
                $columns[$this->studentField] = $student->getId();
            }

            $feedbacks = $this->mapperProxy->findByColumnList($columns);

            return $this->linkFeedbacksToAssessment($feedbacks, $assessment);
        }
        return new Collection();
    }


    /**
     * Link IFeedback back to IAssessable
     *
     * @param Collection|IFeedback[] $feedbacks
     * @param IAssessable $assessment
     * @return Collection
     */
    protected function linkFeedbacksToAssessment(Collection $feedbacks, IAssessable $assessment)
    {
        return $feedbacks->map(function ($item) use ($assessment) {
            /** @var IFeedback $item */
            return $item->setAssessable($assessment);
        });
    }

    /**
     * @param Collection|IAssessable[] $assessments
     * @param User                     $student If set, only return this student's responses
     * @return Collection|IFeedback[][]
     */
    public function findForAssessmentCollection(Collection $assessments, User $student = null)
    {
        // Find feedback which belong to any assessments this recognizes
        $recognizedIds = $assessments->filter([$this, 'recognizes'])->getPropertyValues('id');

        if (!\count($recognizedIds)) {
            return new Collection();
        }

        // Only fetch for student if provided
        $columns = ['assessment_id' => $recognizedIds,];
        if ($student instanceof User) {
            $columns[$this->studentField] = $student->getId();
        }

        // Group feedback by assessment
        /** @var Collection|IFeedback[][] $feedbacks */
        $feedbacks = $this->mapperProxy
            ->findByColumnList($columns)
            ->groupByCallback(function (IFeedback $v) {
                return $v->getAssessable()->getId();
            })
        ;

        $this->linkFeedbacksToAssessments($feedbacks, $assessments);

        return $feedbacks;
    }


    /**
     * Add assessment into each feedback
     *
     * @param Collection|IFeedback[][] $feedbacks
     * @param Collection|IAssessable[] $assessments
     * @return Collection
     */
    protected function linkFeedbacksToAssessments(Collection $feedbacks, Collection $assessments)
    {
        foreach ($assessments as $assessment) {
            if (isset($feedbacks[$assessment->getId()])) {
                foreach ($feedbacks[$assessment->getId()] as $feedback) {
                    $feedback->setAssessable($assessment);
                }
            }
        }
        return $assessments;
    }

    /**
     * @inheritdoc
     */
    public function findForStudent(User $student)
    {
        return $this->mapperProxy->findByColumnList([$this->studentField => $student->getId()]);
    }

    /**
     * @inheritdoc
     */
    public function save(IFeedback $response)
    {
        if ($this->recognizes($response->getAssessable())) {
            $this->mapperProxy->save($response);
        }
    }

    /**
     * @inheritdoc
     */
    public function delete(IFeedback $response)
    {
        if ($this->recognizes($response->getAssessable())) {
            $this->mapperProxy->delete($response);
        }
    }


    /**
     * Creates a new model appropriate to the target assessment
     *
     * @param IAssessable $target
     * @return IFeedback
     */
    public function createModel(IAssessable $target)
    {
        if ($this->recognizes($target)) {
            return $this->mapperProxy->createModel();
        }
        return null;
    }
}
