<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\Translator;

use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\GenericCollection;
use function array_filter;
use function array_map;
use function explode;

/**
 * Responsible for translating a Canvas Assignment to Schoolbox SubmissionBox
 */
class Assignment
{

    /**
     * @var GenericCollection<SubmissionBox\MarkType>|SubmissionBox\MarkType[]
     */
    private $markTypes;


    /**
     * @param GenericCollection<SubmissionBox\MarkType>|SubmissionBox\MarkType[] $markTypes
     */
    public function __construct(GenericCollection $markTypes)
    {
        $this->markTypes = $markTypes->shallowClone()->indexBy('slug');
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
                return $this->markTypes[SubmissionBox\MarkType::SLUG_COMPLETION];
            case 'letter_grade':
                return $this->markTypes[SubmissionBox\MarkType::SLUG_LETTER];
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
