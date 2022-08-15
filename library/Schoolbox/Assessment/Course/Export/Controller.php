<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Export;

use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Overwriter;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Controller for exporting assessments into courses.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param AssessmentDataSource $assessmentDataSource Required for updating existing assessment
     * @param Replicator           $replicator           Required for replicating assessments
     * @param Overwriter           $overwriter           Required for overwriting assessments
     */
    public function __construct(
        private AssessmentDataSource $assessmentDataSource,
        private Replicator $replicator,
        private Overwriter $overwriter,
    ) {
    }

    /**
     * Post `$assessment` into `$unitSpec`.
     *
     * If `$assessment` exists within an implementation of `$unitSpec`:
     * associate `$assessment` with `$unitSpec`.
     *
     * This will always create a new assessment within `$unitSpec`, regardless
     * of whether one already exists.
     *
     * @api POST /course/export/unitspecs/{unitSpecId}/assessment/{assessmentId}
     * @since 22.1
     *
     * @param UnitSpec\Model $unitSpec
     * @param IAssessable    $assessment
     * @return void
     */
    public function postAssessmentIntoUnit(
        UnitSpec\Model $unitSpec,
        IAssessable $assessment,
    ): void {
        $newAssessment = $this->copyAssessmentToContext($assessment, $unitSpec);

        // TODO return the same format as the assessment API?
        $this->response->content->set(
            $this->buildJsonOutput([
                'id'    => $newAssessment->getId(),
                'title' => $newAssessment->getTitle(),
            ]),
        );
    }


    /**
     * Put `$assessment` into `$unitSpec`.
     *
     * If `$assessment` has a spec assessment which is within `$unitSpec`,
     * attempt to override the existing spec assessment with whatever is in
     * `$assessment`: otherwise, this should return an error.
     *
     * If the spec assessment has been deleted: should it be restored or a new
     * one created? not sure.
     *
     * @api PUT /course/export/unitspecs/{unitSpecId}/assessment/{assessmentId}
     * @since 22.1
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function putAssessmentIntoUnit(
        IAssessable $assessment,
    ): void {
        $updatedSpecAssessment = $this->overwriteAssessment(
            $assessment,
            $assessment->getSpecificationAssessment(),
        );

        // TODO return the same format as the assessment API?
        $this->response->content->set(
            $this->buildJsonOutput([
                'id'    => $updatedSpecAssessment->getId(),
                'title' => $updatedSpecAssessment->getTitle(),
            ]),
        );
    }

    /**
     * Adds a copy of `$assessment` to the given assessment context `$context`,
     * and returns the copy.
     *
     * Also sets the copy as the spec assessment of `$assessment`.
     *
     * @param IAssessable       $assessment The assessment to copy
     * @param AssessmentContext $context    The destination to copy
     *                                      `$assessment` into
     * @return IAssessable The newly created copy of `$assessment` within
     *                     `$context`.
     * @throws MapperException
     */
    private function copyAssessmentToContext(
        IAssessable $assessment,
        AssessmentContext $context,
    ): IAssessable {
        $newAssessment = $this->replicator->replicateInNewContext(
            $assessment,
            $context,
            $this->getUser(),
        );

        // Associate new assessment with old assessment's context
        $baseContext = $assessment->getBaseContext();
        if (
            $baseContext instanceof UnitImplementation\Model
            && $context->getId() === $baseContext->getSpecification()->getId()
        ) {
            $assessment->setSpecificationAssessment($newAssessment);
            $this->assessmentDataSource->save($assessment, $this->getUser());
        }

        // TODO copy project activities

        return $newAssessment;
    }

    /**
     * Overwrite `$specAssessment` with `$assessment`.
     *
     * @param IAssessable $assessment     The assessment to copy
     * @param IAssessable $specAssessment The assessment to overwrite
     * @return IAssessable
     */
    private function overwriteAssessment(
        IAssessable $assessment,
        IAssessable $specAssessment,
    ): IAssessable {
        // TODO copy project activities

        return $this->overwriter->overwrite(
            $assessment,
            $specAssessment,
            $this->getUser(),
        );
    }
}
