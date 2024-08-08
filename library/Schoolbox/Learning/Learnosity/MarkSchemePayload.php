<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use DebugBar\DebugBarException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;

/**
 * Takes a Mark Scheme and any assorted Grade Group info
 * and compiles it into a minimal flat array struct
 */
class MarkSchemePayload
{
    /**
     * Constructor.
     */
    public function __construct(
        private \Schoolbox\GenericCollection $submissionStates,
        private MarkType $markType,
        private ?Model $gradeGroup
    ) {
    }


    /**
     * Convert Mark Scheme and Grade Groups into an array
     *
     * Grade Groups are only relevant for the Letter Grade Mark Scheme;
     * if you've chosen some other mark scheme, Grade Groups will be empty.
     * An $assessment always knows about the last Grade Group that it saved,
     * even when you didn't use it.
     *
     * @return array
     * @throws DebugBarException
     */
    public function convert(): array
    {
        $struct = [
            'submissionStates' => [],
            'type' => [
                'name' => $this->markType->getName(),
                'slug' => $this->markType->getSlug()
            ],
            'levels' => null
        ];

        /* Cram these boys in too, for frontend usage
         */
        foreach ($this->submissionStates as $submissionState) {
            $struct['submissionStates'][] = [
                'code' => $submissionState->getCode(),
                'displayAs' => $submissionState->getDisplayAs(),
                'normaliseAs' => $submissionState->getNormaliseAs(),
            ];
        }

        /* Only supply grade groups if we needed to use them
         */
        if ($this->gradeGroup && MarkType::SLUG_LETTER === $this->markType->getSlug()) {
            $struct['levels'] = [];

            foreach ($this->gradeGroup->getGradeLevels() as $level) {
                $struct['levels'][] = [
                    'lowerBound' => $level->getLowerBound(),
                    'rawGrade' => $level->getRawGrade(),
                    'caption' => $level->getCaption()
                ];
            }
        }

        return $struct;
    }
}
