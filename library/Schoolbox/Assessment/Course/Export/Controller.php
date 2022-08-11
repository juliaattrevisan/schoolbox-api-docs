<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Export;

use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;

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
     */
    public function __construct(
        private AssessmentDataSource $assessmentDataSource,
        private Replicator $replicator,
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
        // How to handle projects?
        // Just manually I suppose?
        $newAssessment = $this->replicator->replicateInNewContext(
            $assessment,
            $unitSpec,
            $this->getUser(),
        );

        // If `$assessment` exists within an implementation of `$unitSpec`:
        // associate `$assessment` with `$unitSpec`.
        $baseContext = $assessment->getBaseContext();
        if (
            $baseContext instanceof UnitImplementation\Model
            && $unitSpec->getId() === $baseContext->getSpecification()->getId()
        ) {
            $assessment->setSpecificationAssessment($newAssessment);
            $this->assessmentDataSource->save($assessment, $this->getUser());
        }

        $this->response->content->set(
            $this->buildJsonOutput([
                'id' => $newAssessment->getId(),
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
     * @param UnitSpec\Model $unitSpec
     * @param IAssessable    $assessment
     * @return void
     */
    public function putAssessmentIntoUnit(
        UnitSpec\Model $unitSpec,
        IAssessable $assessment,
    ): void {
//        $this->replicator->replicateInNewContext(
//            $assessment,
//            $unitSpec,
//            $this->getUser(),
//        );
    }
}
