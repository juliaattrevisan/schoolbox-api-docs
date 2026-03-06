<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Reporting\Decorator;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Reporting\Model;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Mapper\Exception;


/**
 * Class Decorator
 *
 * @package Schoolbox\Learning\Assessment\Reporting\Decorator
 */
class Decorator implements DecoratorInterface
{
    use ReportingTrait;


    /**
     * Decorator constructor.
     */
    public function __construct(
        private ?int $markType,
        private ?int $instructorMarkType,
        private ?int $gradeGroup,
        private ?int $instructorGradeGroup,
        private ?float $maxMark,
        private ?float $instructorMaxMark,
        private ?bool $feedbackCommentsEnable,
        private ?bool $supportingDataEnable,
        private ?string $supportingDataRange,
        private ?array $supportingDataCurriculumNodes,
        private ?array $supportingDataFolders,
        private ?array $supportingDataWorkTypes,
        private ?bool $supportingDataMarkbookEnable,
    ) {
    }


    /**
     * Assign values to an $assessment instance, basically hydrating it
     *
     * @param Model $assessment
     * @throws Exception
     */
    public function decorate($assessment): void
    {
        /* Set matching data types: Mark Type, Grade Group
         *
         * Ideally these would be hydrated like everything else, but for some reason,
         * I end up drowning in the Swamp if I try
         */

        /* Both variants of Mark Type
         */
        $markTypeMapper = MarkTypeMapper::getInstance();

        $markType = $markTypeMapper->createModel()->setId($this->markType);
        $instructorMarkType = $markTypeMapper->createModel()->setId($this->instructorMarkType);

        $assessment->setMarkType($markType);
        $assessment->setInstructorMarkType($instructorMarkType);

        /* Both variants of Grade Group
         */
        $gradeGroupMapper = Group\Mapper::getInstance();

        $gradingGroup = $gradeGroupMapper->createModel()->setId($this->gradeGroup);
        $instructorGradingGroup = $gradeGroupMapper->createModel()->setId($this->instructorGradeGroup);

        $assessment->setGradeGroup($gradingGroup);
        $assessment->setInstructorGradeGroup($instructorGradingGroup);

        /* Both variants of Mark Scheme which is used to convert normalised to human-readable and vice versa
         *
         * Only spawn the mark scheme after the max mark is finalized
         * (as it might have changed from what was submitted to the form)
         */
        $markSchemeFactory = new MarkSchemeFactory();
        $assessment->setMarkScheme($markSchemeFactory->spawnMarkSchemeFor($assessment));
        $assessment->setInstructorMarkScheme($markSchemeFactory->spawnInstructorMarkSchemeFor($assessment));

        /* Both variants of maximum available marks
         */
        $assessment->setMaxMark($this->maxMark);
        $assessment->setInstructorMaxMark($this->instructorMaxMark);

        /* Reporting activities never have a meaningful weighting
         */
        $assessment->setWeight(0.0);

        /* Reporting activities' feedbacks only spawn on Summative Reports for now, never the ui; this means only staff
         * would have the ability to see it. Some future feature will enable mass-release of marks, but for now, this is
         * gonna be hardcoded to staff only.
         */
        $assessment->setMarkVisibility(IAssessable::MARK_VISIBILITY_PRIVATE);

        /* Reporting-specific stuffs
         */
        $assessment->setFeedbackCommentsEnable($this->feedbackCommentsEnable);
        $assessment->setSupportingDataEnable($this->supportingDataEnable);
        $assessment->setSupportingDataRange($this->supportingDataRange);

        /* Assemble curriculum nodes
         */
        $selectedNodesCsv = $this->supportingDataCurriculumNodes;

        if (isset($selectedNodesCsv[0])) {
            $selectedNodes = explode(',', $selectedNodesCsv[0]);

            $assessment->setCurriculumNodes(CurriculumNodeMapper::getInstance()->findByColumn('id', $selectedNodes));
        }

        $assessment->setSupportingDataFolders($this->supportingDataFolders);

        /* Assemble work types' instances
         * Selection of work types is entirely optional for users: leaving this empty means we won't filter on work
         * types at all, letting everything through
         */
        if (\count($this->supportingDataWorkTypes)) {
            $assessment->setSupportingDataWorkTypes(WorkTypeMapper::getInstance()->findByColumn('id', $this->supportingDataWorkTypes));
        }

        $assessment->setSupportingDataMarkbookEnable($this->supportingDataMarkbookEnable);
    }
}
