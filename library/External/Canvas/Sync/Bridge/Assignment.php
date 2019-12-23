<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\Bridge;

use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType;
use function array_filter;
use function array_map;
use function explode;
use function strtolower;

/**
 * Responsible for providing the link between a  Canvas Assignment to Schoolbox SubmissionBox Spec
 */
class Assignment
{
    // These are Canvas `quiz_dim`.`quiz_type` enums
    public const QUIZ_TYPE_PRACTICE      = 'practice_quiz';
    public const QUIZ_TYPE_ASSIGNMENT    = 'assignment';
    public const QUIZ_TYPE_GRADED_SURVEY = 'graded_survey';
    public const QUIZ_TYPE_SURVEY        = 'survey';


    /** @var string */
    private $siteLocation;

    /** @var GenericCollection<SubmissionBox\MarkType>|SubmissionBox\MarkType[] */
    private $markTypes;

    /** @var GenericCollection<WorkType\Model>|WorkType\Model[] */
    private $workTypes;


    /**
     * @param string                                                             $siteLocation
     * @param GenericCollection<SubmissionBox\MarkType>|SubmissionBox\MarkType[] $markTypes
     * @param GenericCollection<WorkType\Model>|WorkType\Model[]                 $workTypes
     */
    public function __construct(string $siteLocation, GenericCollection $markTypes, GenericCollection $workTypes)
    {
        $this->siteLocation = $siteLocation;
        $this->markTypes    = $markTypes->shallowClone()->indexBy('slug');
        $this->workTypes    = $workTypes->shallowClone()->indexBy('id');
    }


    /**
     * @param float|null $pointsPossible
     * @return float|null
     */
    public function getMaxMark(?float $pointsPossible): ?float
    {
        return $pointsPossible;
    }


    /**
     * Returns a SubmissionBox mark visibility
     *
     * 'Muted' canvas assignments can be submitted to, but grades are not visible to students, and they
     * do not receive grading notifications.
     *
     * @see https://community.canvaslms.com/docs/DOC-12961
     * @param bool $muted
     * @return int
     */
    public function getMarkVisibility(bool $muted): int
    {
        // If muted, students cannot see grades left on assignment in Canvas
        if ($muted) {
            return SubmissionBox::MARK_VISIBILITY_STAFF_ONLY;
        }
        return SubmissionBox::MARK_VISIBILITY_STAFF_STUDENT_PARENT;
    }


    /**
     * @return string
     */
    public function getSelfReviewability(): string
    {
        return SubmissionBox::SELF_REVIEWABILITY_NONE;
    }


    /**
     * Returns the work type to assign to imported Canvas Assignments
     *
     * @return null|WorkType\Model
     */
    public function getSubmissionBoxWorkType(): ?WorkType\Model
    {
        return null;
    }


    /**
     * Returns the work type to assign to this quiz type
     *
     * @param string $quizType
     * @return null|WorkType\Model
     */
    public function getQuizWorkType(string $quizType): ?WorkType\Model
    {
        switch (strtolower($this->siteLocation)) {
            // Hard-coded for now to the staloy/demo dataset
            case 'staloy':
            case 'demo':
                $staloyConfig = [
                    self::QUIZ_TYPE_PRACTICE      => 11,
                    self::QUIZ_TYPE_ASSIGNMENT    => 11,
                    self::QUIZ_TYPE_SURVEY        => 11,
                    self::QUIZ_TYPE_GRADED_SURVEY => 11,
                ];
                $workTypeId   = $staloyConfig[$quizType];
                foreach ($this->workTypes as $workType) {
                    if ($workType->getId() === $workTypeId) {
                        return $workType;
                    }
                }
                break;
        }
        return null;
    }


    /**
     * Returns a translated mark type
     *
     * @param string|null $gradingType
     * @param float|null  $pointsPossible
     * @return SubmissionBox\MarkType
     */
    public function getMarkType(?string $gradingType, ?float $pointsPossible): SubmissionBox\MarkType
    {
        switch ($gradingType) {
            case 'points':
            case 'gpa_scale':
                // Schoolbox requires 'total-mark' scheme to have max-mark set > 0.
                // It appears common to use 'gpa_scale', but leave points_possible as '0', often these appear to be peer reviewed.
                // TODO discuss with client.
                return $pointsPossible
                    ? $this->markTypes[SubmissionBox\MarkType::SLUG_TOTAL]
                    : $this->markTypes[SubmissionBox\MarkType::SLUG_NONE];
            case 'pass_fail':
                // TODO add support for completion to DueWork
//                return $this->markTypes[SubmissionBox\MarkType::SLUG_COMPLETION];
                return $this->markTypes[SubmissionBox\MarkType::SLUG_NONE];
            case 'letter_grade':
                // TODO add support for letter grades. (these require a grade group be be set)
                return $this->markTypes[SubmissionBox\MarkType::SLUG_NONE];
            case 'percent':
                return $this->markTypes[SubmissionBox\MarkType::SLUG_PERCENT];
            case 'not_graded':
            case null:
            default;
                return $this->markTypes[SubmissionBox\MarkType::SLUG_NONE];
        }
    }


    /**
     * Returns whether a canvas assignment's submissionTypes indicates it can be submitted online
     *
     * @param string $submissionTypes
     * @return bool
     * @throws InvalidArgumentException
     */
    public function getAcceptOnline(string $submissionTypes): bool
    {
        foreach (array_filter(array_map('trim', explode(',', $submissionTypes))) as $submissionType) {
            if ($this->acceptsOnline($submissionType)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Returns whether a canvas assignment's submissionTypes indicates it can be submitted online multiple times
     *
     * @param string $submissionTypes
     * @return bool
     * @throws InvalidArgumentException
     */
    public function getAcceptMultiple(string $submissionTypes): bool
    {
        foreach (array_filter(array_map('trim', explode(',', $submissionTypes))) as $submissionType) {
            if ($this->acceptsMultiple($submissionType)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Returns whether a canvas assignment's submissionType indicates it can be submitted online
     *
     * @param string $submissionType
     * @return bool
     * @throws InvalidArgumentException
     */
    private function acceptsOnline(string $submissionType): bool
    {
        switch ($submissionType) {
            case 'media_recording':
            case 'online_url':
            case 'online_upload':
            case 'online_quiz':
            case 'online_text_entry':
            case 'online_file_upload':
                return true;
            case 'discussion_topic':
            case 'external_tool':
            case 'none':
            case 'not_graded':
            case 'on_paper':
            case 'wiki_page':
                return false;
        }
        throw new InvalidArgumentException("Unrecognised submissionType: '$submissionType'");
    }


    /**
     * Returns whether a canvas assignment's submissionType indicates it can be submitted online multiple times
     *
     * @param string $submissionType
     * @return bool
     * @throws InvalidArgumentException
     */
    private function acceptsMultiple(string $submissionType): bool
    {
        if (!$this->acceptsOnline($submissionType)) {
            return false;
        }
        // For now, assume all online types can have multiple submissions. TODO check this with client
        switch ($submissionType) {
            case 'media_recording':
            case 'online_url':
            case 'online_upload':
            case 'online_quiz':
            case 'online_text_entry':
            case 'online_file_upload':
                return true;
        }
        throw new InvalidArgumentException("Unrecognised submissionType: '$submissionType'");
    }
}
