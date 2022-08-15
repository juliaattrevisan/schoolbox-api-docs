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
use Schoolbox\Learning\Assessment\Project;
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
     * @param IAssessable       $implAssessment The assessment to copy.
     * @param AssessmentContext $context        The destination to copy
     *                                          `$implAssessment` into.
     * @return IAssessable The newly created copy of `$implAssessment` within
     *                     `$context`.
     * @throws MapperException
     */
    private function copyAssessmentToContext(
        IAssessable $implAssessment,
        AssessmentContext $context,
    ): IAssessable {
        $specAssessment = $this->replicator->replicateInNewContext(
            $implAssessment,
            $context,
            $this->getUser(),
        );

        // Associate new assessment with old assessment's context
        $baseContext = $implAssessment->getBaseContext();
        if (
            $baseContext instanceof UnitImplementation\Model
            && $context->getId() === $baseContext->getSpecification()->getId()
        ) {
            $implAssessment->setSpecificationAssessment($specAssessment);
            $this->assessmentDataSource->save($implAssessment, $this->getUser());
        }

        if ($implAssessment instanceof Project && $specAssessment instanceof Project) {
            $this->syncProjectActivities($implAssessment, $specAssessment);
        }

        return $specAssessment;
    }

    /**
     * Overwrite `$specAssessment` with `$implAssessment`.
     *
     * @param IAssessable $implAssessment The assessment to copy.
     * @param IAssessable $specAssessment The assessment to overwrite.
     * @return IAssessable The updated `$specAssessment`.
     */
    private function overwriteAssessment(
        IAssessable $implAssessment,
        IAssessable $specAssessment,
    ): IAssessable {
        $updatedSpecAssessment = $this->overwriter->overwrite(
            $implAssessment,
            $specAssessment,
            $this->getUser(),
        );

        if ($implAssessment instanceof Project && $updatedSpecAssessment instanceof Project) {
            $this->syncProjectActivities($implAssessment, $updatedSpecAssessment);
        }

        return $updatedSpecAssessment;
    }

    /**
     * Overwrite activities within `$implProject` to `$specProject`.
     *
     * If the project activity:
     * * is in `$implProject` but not `$specProject`: copy into `$specProject`
     * * is in `$implProject` and `$specProject`: overwrite in `$specProject`
     * * not in `$implProject` but in `$specProject`: remove from `$specProject`
     *
     * @param Project $implProject The project whose activities are to be copied
     * @param Project $specProject The project to copy activities into
     * @return void
     * @throws MapperException
     */
    private function syncProjectActivities(
        Project $implProject,
        Project $specProject,
    ): void {
        // Load spec activity IDs for later use
        $specActivityIds     = [];
        $implActivitySpecIds = [];
        foreach ($specProject->getAssessables() as $specActivity) {
            $specActivityIds[] = $specActivity->getId();
        }

        // Copy in `$implProject` to `$specProject` if they don't exist there:
        // otherwise, overwrite the activity in `$specProject` with the
        // `$implProject` version.
        foreach ($implProject->getAssessables() as $implActivity) {
            $specActivity = $implActivity->getSpecificationAssessment();
            if ($specActivity && in_array($specActivity->getId(), $specActivityIds, true)) {
                $this->overwriteAssessment(
                    $implActivity,
                    $specActivity,
                );
                $implActivitySpecIds[] = $specActivity->getId();
            } else {
                $newSpecActivity = $this->copyAssessmentToContext(
                    $implActivity,
                    $specProject,
                );
                $specActivityIds[]     = $newSpecActivity->getId();
                $implActivitySpecIds[] = $newSpecActivity->getId();
            }
        }

        // Now: delete any activities in `$specProject` not in `$implProject`
        $specActivityIdsNotInImpl = array_diff($specActivityIds, $implActivitySpecIds);
        foreach ($specProject->getAssessables() as $specActivity) {
            if (in_array($specActivity->getId(), $specActivityIdsNotInImpl)) {
                $this->assessmentDataSource->delete($specActivity);
            }
        }
    }
}
