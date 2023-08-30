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
            $this->produceForSchoolCode()
        ];

        /* Everything below here is optional depending on which context
         * a quiz is spawned. Context determines which combination of
         * tags will be generated.
         */
        $stack[] = $this->produceForCourseSpec($courseSpecUuid);
        $stack[] = $this->produceForCourseImpl($courseImplUuid);
        $stack[] = $this->produceForCourseUnitSpec($courseUnitSpecUuid);
        $stack[] = $this->produceForCourseUnitImpl($courseUnitImplUuid);
        $stack[] = $this->produceForClassCode($classCode);

        /* Remove nulls
         */
        return array_filter($stack);
    }


    /**
     * Produce a tag for the given School Code
     * Used to limit content to that which is owned by your school
     *
     * @return array
     */
    public function produceForSchoolCode(): array
    {
        return [
            'type' => 'schoolCode',
            'name' => $this->schoolCode
        ];
    }


    /**
     * Produce a tag for the given Course Specification UUID
     *
     * @param string|null $uuid
     * @return string[]|null
     */
    public function produceForCourseSpec(?string $uuid): ?array
    {
        if ($uuid) {
            return [
                'type' => 'courseSpec',
                'name' => $uuid
            ];
        }

        return null;
    }


    /**
     * Produce a tag for the given Course Implementation UUID
     *
     * @param string|null $uuid
     * @return string[]|null
     */
    public function produceForCourseImpl(?string $uuid): ?array
    {
        if ($uuid) {
            return [
                'type' => 'courseImpl',
                'name' => $uuid
            ];
        }

        return null;
    }


    /**
     * Produce a tag for the given Course Unit Specification UUID
     *
     * @param string|null $uuid
     * @return string[]|null
     */
    public function produceForCourseUnitSpec(?string $uuid): ?array
    {
        if ($uuid) {
            return [
                'type' => 'courseUnitSpec',
                'name' => $uuid
            ];
        }

        return null;
    }


    /**
     * Produce a tag for the given Course Unit Implementation UUID
     *
     * @param string|null $uuid
     * @return string[]|null
     */
    public function produceForCourseUnitImpl(?string $uuid): ?array
    {
        if ($uuid) {
            return [
                'type' => 'courseUnitImpl',
                'name' => $uuid
            ];
        }

        return null;
    }


    /**
     * Produce a tag for the given Class Code
     *
     * @param string|null $classCode
     * @return string[]|null
     */
    public function produceForClassCode(?string $classCode): ?array
    {
        if ($classCode) {
            return [
                'type' => 'classCode',
                'name' => $classCode
            ];
        }

        return null;
    }
}
