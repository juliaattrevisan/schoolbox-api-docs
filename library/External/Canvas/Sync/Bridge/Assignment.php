<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Quoter\Mysql\Quoter;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeLevelGroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Mapper as GradeLevelMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Quiz;
use Schoolbox\UserMapper;

use function array_filter;
use function array_key_exists;
use function array_map;
use function explode;

/**
 * Responsible for providing the link between a Canvas Assignment/Quiz to Schoolbox SubmissionBox/Quiz Spec
 */
class Assignment
{
    // These are Canvas `quiz_dim`.`quiz_type` enums
    public const QUIZ_TYPE_PRACTICE      = 'practice_quiz';
    public const QUIZ_TYPE_ASSIGNMENT    = 'assignment';
    public const QUIZ_TYPE_GRADED_SURVEY = 'graded_survey';
    public const QUIZ_TYPE_SURVEY        = 'survey';

    public const MARK_COMPLETE = 'complete';
    public const MARK_INCOMPLETE = 'incomplete';

    protected array $gradeGroups = [];

    /** @var GenericCollection|SubmissionBox\MarkType[] */
    protected $markTypes;

    /** @var GenericCollection|WorkType\Model[] */
    protected $workTypes;

    /**
     * @param GenericCollection|SubmissionBox\MarkType[] $markTypes
     * @param GenericCollection|WorkType\Model[]         $workTypes
     */
    public function __construct(GenericCollection $markTypes, GenericCollection $workTypes)
    {
        $this->markTypes    = $markTypes->shallowClone()->indexBy('slug');
        $this->workTypes    = $workTypes->shallowClone()->indexBy('name');
    }

    /**
     * Returns whether to sync canvas quizzes
     * @return bool
     */
    public function syncCanvasQuizzes(): bool
    {
        return true;
    }

    /**
     * @param float|null $pointsPossible
     * @return float|null
     */
    public function getMaxMark(?float $pointsPossible): ?float
    {
        return $pointsPossible ?? 0.0; // TODO think about this some more, and upgrade to documented constant
    }

    /**
     * @param float|null $pointsPossible
     * @return float|null
     */
    public function getInstructorMaxMark(?float $pointsPossible): ?float
    {
        // For now, instructor max mark is always the same as the learner max mark
        return $this->getMaxMark($pointsPossible);
    }

    /**
     * @param GradeLevelGroupMapper $gradeLevelGroupMapper
     * @param GradeLevelMapper      $gradeLevelMapper
     * @param UserMapper            $userMapper
     * @return void
     */
    public function prepareLetterGrades(GradeLevelGroupMapper $gradeLevelGroupMapper, GradeLevelMapper $gradeLevelMapper, UserMapper $userMapper): void
    {
        // do nothing
    }

    /**
     * Returns a predicate for finding a suitable question type
     *
     * @return Builder
     * @throws InvalidColumnException
     */
    public function getQuizQuestionTypePredicate(): Builder
    {
        return (new Builder(new Quoter(), ''))
            ->equals('assess_luQuestionType.filename', 'short')
            ->equals('assess_luQuestionType.availability', Quiz\Model::AVAILABILITY_ASSESS);
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
     * Returns work type with the given name, if exists
     * @param string $workTypeName
     * @return WorkType\Model|null
     */
    public function getSubmissionBoxWorkType(string $workTypeName): ?WorkType\Model
    {
        return $this->workTypes->offsetGet($workTypeName);
    }


    /**
     * Returns the weight to assign to Submission Boxes
     *
     * @return float
     */
    public function getSubmissionBoxWeight(): float
    {
        return 1;
    }


    /**
     * Returns the weight to assign to Quizzes
     *
     * @return float
     */
    public function getQuizWeight(): float
    {
        return 1;
    }

    /**
     * Returns the work type to assign to this quiz type
     *
     * @param string $quizType
     * @return null|WorkType\Model
     */
    public function getQuizWorkType(string $quizType): ?WorkType\Model
    {
        $config = [
            self::QUIZ_TYPE_PRACTICE      => 11,
            self::QUIZ_TYPE_ASSIGNMENT    => 11,
            self::QUIZ_TYPE_SURVEY        => 11,
            self::QUIZ_TYPE_GRADED_SURVEY => 11,
        ];
        if (!array_key_exists($quizType, $config)) {
            return null;
        }
        $workTypeId = $config[$quizType];
        foreach ($this->workTypes as $workType) {
            if ($workType->getId() === $workTypeId) {
                return $workType;
            }
        }
        return null;
    }

    /**
     * For now: quizzes will only ever have the "Total Mark" mark type, as they
     * were prior to the Learnosity changes.
     *
     * @return SubmissionBox\MarkType
     */
    public function getQuizMarkType(): SubmissionBox\MarkType
    {
        return $this->markTypes[SubmissionBox\MarkType::SLUG_TOTAL];
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
            default:
                return $this->markTypes[SubmissionBox\MarkType::SLUG_NONE];
        }
    }

    /**
     * Returns a translated mark type for instructors
     *
     * @param string|null $gradingType
     * @param float|null  $pointsPossible
     * @return SubmissionBox\MarkType
     */
    public function getInstructorMarkType(?string $gradingType, ?float $pointsPossible): SubmissionBox\MarkType
    {
        // For now, instructor mark type is always the same as the learner mark type
        return $this->getMarkType($gradingType, $pointsPossible);
    }

    /**
     * @param string|null $gradingType
     * @return SubmissionBox\GradeLevel\Group\Model|null
     */
    public function getGradeGroup(?string $gradingType): ?SubmissionBox\GradeLevel\Group\Model
    {
        return null;
    }

    /**
     * @param string|null $gradingType
     * @return SubmissionBox\GradeLevel\Group\Model|null
     */
    public function getInstructorGradeGroup(?string $gradingType): ?SubmissionBox\GradeLevel\Group\Model
    {
        // For now, instructor grade group is always the same as the learner grade group
        return $this->getGradeGroup($gradingType);
    }

    /**
     * Returns whether a canvas assignment's submissionTypes indicates it can be submitted online
     *
     * @param string|array $submissionTypes
     * @return bool
     * @throws InvalidArgumentException
     */
    public function getAcceptOnline(string|array $submissionTypes): bool
    {
        if (is_string($submissionTypes)) {
            $submissionTypes = array_filter(array_map('trim', explode(',', $submissionTypes)));
        }
        foreach ($submissionTypes as $submissionType) {
            if ($this->acceptsOnline($submissionType)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Returns whether a canvas assignment's submissionTypes indicates it can be submitted online multiple times
     *
     * @param string|array $submissionTypes
     * @return bool
     * @throws InvalidArgumentException
     */
    public function getAcceptMultiple(string|array $submissionTypes): bool
    {
        if (is_string($submissionTypes)) {
            $submissionTypes = array_filter(array_map('trim', explode(',', $submissionTypes)));
        }
        foreach ($submissionTypes as $submissionType) {
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
            case 'student_annotation':
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
            case 'student_annotation':
                return true;
        }
        throw new InvalidArgumentException("Unrecognised submissionType: '$submissionType'");
    }
}
