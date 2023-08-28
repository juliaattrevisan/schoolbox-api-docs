<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

/**
 * Generates a set of Tag tuples, along with predefined Tag Types,
 * for various possibilities of assessment context (course spec,
 * course impl, homepage, etc)
 *
 * Since tag assignation replaces whatever's previously there, you
 * end up having to generate the entire set correctly each time
 * you change it.
 */
class TagFactory
{
    /**
     * $schoolCode is mandatory to prevent one school seeing another's data
     *
     * @param string $schoolCode
     * @throws \DomainException
     */
    public function __construct(private string $schoolCode)
    {
        /* This should never happen, but if it's improperly configured,
         * Die Catastrophically
         */
        if ('' === trim($schoolCode)) {
            throw new \DomainException('School code not configured correctly; please check admin settings');
        }
    }


    /**
     * Manufacture a complete set of tags
     *
     * @param string|null $courseSpecUuid
     * @param string|null $courseImplUuid
     * @param string|null $courseUnitSpecUuid
     * @param string|null $courseUnitImplUuid
     * @param string|null $classCode
     * @return array
     */
    public function produce(
        ?string $courseSpecUuid,
        ?string $courseImplUuid,
        ?string $courseUnitSpecUuid,
        ?string $courseUnitImplUuid,
        ?string $classCode
    ): array {
        /* Always have a school code tag
         */
        $stack = [
            [
                'type' => 'schoolCode',
                'name' => $this->schoolCode
            ]
        ];

        /* Everything below here is optional depending on which context
         * a quiz is spawned. Context determines which combination of
         * tags will be generated.
         */
        if ($courseSpecUuid) {
            $stack[] = [
                'type' => 'courseSpec',
                'name' => $courseSpecUuid
            ];
        }

        if ($courseImplUuid) {
            $stack[] = [
                'type' => 'courseImpl',
                'name' => $courseImplUuid
            ];
        }

        if ($courseUnitSpecUuid) {
            $stack[] = [
                'type' => 'courseUnitSpecUuid',
                'name' => $courseUnitSpecUuid
            ];
        }

        if ($courseUnitImplUuid) {
            $stack[] = [
                'type' => 'courseUnitImplUuid',
                'name' => $courseUnitImplUuid
            ];
        }

        if ($classCode) {
            $stack[] = [
                'type' => 'classCode',
                'name' => $classCode
            ];
        }

        return $stack;
    }
}

// new TagGenerator($this->getConfigLoader()->get('site_school_code'));