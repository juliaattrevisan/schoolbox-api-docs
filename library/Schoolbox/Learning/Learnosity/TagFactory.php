<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Schoolbox\Learning\Assessment\IAssessable;

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
     * Manufacture a complete set of tags, using the provided $assessment
     * hierarchy to magically determine the tags. An improperly hydrated
     * $assessment may yield unintended outcomes though, so use mindfully
     *
     * @param IAssessable $assessment
     * @return array|array[]
     */
    public function produceFromAssessment(IAssessable $assessment): array
    {
        /* Arbitrarily define a default, just in case there's some random
         * edge case that isn't accounted in the if/else clause below.
         */
        $stack = [
            $this->produceForSchoolCode()
        ];

        /* If we're in a course specification, regardless of being
         * in a Unit or a Project within a unit:
         *     * supply a School Code tag
         *     * supply a Course Spec tag
         *     * supply a Course Unit Spec tag
         *
         * This occurs when you're creating courses themselves
         */
        if ($assessment->getContext()->isSpecificationContext()) {
            /* Quiz specifications can exist in either a Unit or a Project
             * Do an instanceof check to find out which
             */
            if ($assessment->getContext() instanceof \Schoolbox\Learning\Assessment\Project) {
                /** @var \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model $baseContext */
                $baseContext = $assessment->getBaseContext();

                return $this->produce(
                    $baseContext->getCourse()->getUuid(),
                    $baseContext->getUuid(),
                    null
                );
            }

            /** @var \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model $context */
            $context = $assessment->getBaseContext();

            return $this->produce(
                $context->getCourse()->getUuid(),
                $context->getUuid(),
                null
            );
        }

        /* Quizzes from an Imported Unit: contexts are considered Implementations
         * Quizzes made directly in a homepage: contexts are considered Implementations
         *
         * The only way to tell is to do a data type inspection? Really?
         */
        if ($assessment->getContext()->isImplementationContext()) {
            if ($assessment->getContext() instanceof \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model) {
                /* If we're in a course implementation:
                 *     * supply a School Code tag
                 *     * supply a Course Spec tag
                 *     * supply a Course Unit Spec tag
                 *     * supply a Folder Code tag
                 *
                 * This occurs when you try to edit an already-imported quiz
                 * from the Course Outline component in a homepage
                 */
                return $this->produce(
                    $assessment->getContext()->getSpecification()->getCourse()->getUuid(),
                    $assessment->getContext()->getSpecification()->getUuid(),
                    $assessment->getContext()->getFolder()->getCodeString()
                );
            }

            if ($assessment->getContext() instanceof \Schoolbox\Resource\Folder\Model) {
                /* If we're in a homepage:
                 *     * supply a School Code tag
                 *     * supply a Folder Code tag
                 *
                 * This occurs when you're using the Quizzes component directly
                 * in a homepage, and not in any Course workflow
                 */
                return $this->produce(
                    null,
                    null,
                    $assessment->getContext()->getCodeString()
                );
            }
        }

        /* Not any of the above? Return the school code tag mandatorily
         * This might happen due to poor hydration, stub instances, etc
         */
        return $stack;
    }


    /**
     * Manufacture a complete set of tags, using the args provided.
     * This allows you to manufacture them arbitrarily
     *
     * @param string|null $courseSpecUuid
     * @param string|null $courseUnitSpecUuid
     * @param string|null $classCode
     * @return array
     */
    public function produce(
        ?string $courseSpecUuid,
        ?string $courseUnitSpecUuid,
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
        $stack[] = $this->produceForCourseUnitSpec($courseUnitSpecUuid);
        $stack[] = $this->produceForClassCode($classCode);

        /* Remove nulls
         */
        return array_values(array_filter($stack));
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
    private function produceForCourseSpec(?string $uuid): ?array
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
     * Produce a tag for the given Course Unit Specification UUID
     *
     * @param string|null $uuid
     * @return string[]|null
     */
    private function produceForCourseUnitSpec(?string $uuid): ?array
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
     * Produce a tag for the given Class Code
     *
     * @param string|null $classCode
     * @return string[]|null
     */
    private function produceForClassCode(?string $classCode): ?array
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
