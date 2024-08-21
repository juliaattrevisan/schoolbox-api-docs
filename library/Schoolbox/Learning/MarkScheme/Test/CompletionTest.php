<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\User;

class CompletionTest extends TestCase
{
    /**
     * @param mixed $normalised
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_normalised')]
    public function test_getHumanReadable_IncompleteImplicit($normalised)
    {
        $expected = Completion::HUMAN_READABLE_INCOMPLETE;

        $markScheme = new Completion();
        $actual = $markScheme->getHumanReadableMark($normalised);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @param mixed $normalised
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_normalised')]
    public function test_getHumanReadable_IncompleteAsNull($normalised)
    {
        $expected = Completion::HUMAN_READABLE_INCOMPLETE;

        $markScheme = new Completion();
        $actual = $markScheme->getHumanReadableMark($normalised, null);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @param mixed $normalised
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_normalised')]
    public function test_getHumanReadable_studentMarkedResponseIncomplete($normalised)
    {
        $response = $this->getSelfAssessedResponseMarkedAsIncomplete();
        $markScheme = new Completion();

        $expected = Completion::HUMAN_READABLE_INCOMPLETE;
        $actual = $markScheme->getHumanReadableMark($normalised, $response);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @param mixed $normalised
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_normalised')]
    public function test_getHumanReadable_studentMarkedResponseComplete($normalised)
    {
        $response = $this->getSelfAssessedResponseMarkedAsComplete();
        $markScheme = new Completion();

        $expected = Completion::HUMAN_READABLE_COMPLETE;
        $actual = $markScheme->getHumanReadableMark($normalised, $response);

        $this->assertEquals($expected, $actual);
    }


    /**
     * @param mixed $normalised
     * @throws \InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_normalised')]
    public function test_getHumanReadable_teacherMarkedResponseIncomplete($normalised)
    {
        $response = $this->getTeacherAssessedResponseMarkedAsIncomplete();
        $markScheme = new Completion();

        $expected = Completion::HUMAN_READABLE_INCOMPLETE;
        $actual = $markScheme->getHumanReadableMark($normalised, $response);

        $this->assertEquals($expected, $actual);
    }


    /**
     * @param mixed $normalised
     * @throws \InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_normalised')]
    public function test_getHumanReadable_teacherMarkedResponseComplete($normalised)
    {
        $response = $this->getTeacherAssessedResponseMarkedAsComplete();
        $markScheme = new Completion();

        $expected = Completion::HUMAN_READABLE_COMPLETE;
        $actual = $markScheme->getHumanReadableMark($normalised, $response);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @param mixed $humanReadable
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_humanReadable_noResponse')]
    public function test_getNormalised_noResponse($humanReadable)
    {
        $expected = null;
        $markScheme = new Completion();
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @param mixed $humanReadable
     * @param TaskResponse $response
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_humanReadable_withResponse')]
    public function test_getNormalised_withResponse($humanReadable, TaskResponse $response = null)
    {
        if ($response && $response->isMarkedComplete()) {
            $expected = Completion::NORMALISED_MARK_MAX;
        } else {
            $expected = Completion::NORMALISED_MARK_MIN;
        }

        $markScheme = new Completion();
        $actual = $markScheme->getNormalisedMark($humanReadable, $response);
        $this->assertEquals($expected, $actual);
    }



    // ==================== Data Providers ====================

    /**
     * @return int[][]
     */
    public function provider_normalised_valid(): array
    {
        return [
            [0],
            [10],
            [50],
            [99],
            [100],
            [IMarkScheme::NORMALISED_MARK_MIN],
            [IMarkScheme::NORMALISED_MARK_MAX],
        ];
    }

    /**
     * @return mixed[][]
     */
    public function provider_normalised_invalid(): array
    {
        return [
            [null],
            [''],
            ['   '],
            [-1],
            [101],
            ['a random string'],
            [[1]],
        ];
    }

    /**
     * @return mixed[][]
     */
    public function provider_normalised(): array
    {
        return array_merge($this->provider_normalised_valid(), $this->provider_normalised_invalid());
    }

    /**
     * @return string[][]
     */
    public function provider_humanReadable_valid(): array
    {
        return [
            [Completion::HUMAN_READABLE_INCOMPLETE],
            [Completion::HUMAN_READABLE_COMPLETE],
            [Completion::HUMAN_READABLE_COMPLETE],
        ];
    }

    /**
     * @return mixed[][]
     */
    public function provider_humanReadable_invalid(): array
    {
        return [
            [null],
            ['  '],
            [33],
            ['An unknown string'],
        ];
    }

    /**
     * @return mixed[][]
     */
    public function provider_humanReadable_noResponse(): array
    {
        return array_merge($this->provider_humanReadable_valid(), $this->provider_humanReadable_invalid());
    }


    /**
     * @return array
     * @throws \InvalidArgumentException
     */
    public function provider_humanReadable_withResponse(): array
    {
        $humanReadableValues = array_merge($this->provider_humanReadable_valid(), $this->provider_humanReadable_invalid());
        $responses = [
            null,
            $this->getSelfAssessedResponseMarkedAsIncomplete(),
            $this->getSelfAssessedResponseMarkedAsComplete(),
            $this->getTeacherAssessedResponseMarkedAsIncomplete(),
            $this->getTeacherAssessedResponseMarkedAsComplete(),
        ];

        $data = [];

        foreach ($humanReadableValues as $humanReadable) {
            foreach ($responses as $response) {
                $data[] = [$humanReadable[0], $response];
            }
        }
        return $data;
    }


    // Private helpers for data providers

    /**
     * @return TaskResponse
     */
    private function getSelfAssessedResponseMarkedAsIncomplete(): TaskResponse
    {
        $student = (new User())->setId(1);

        return (new TaskResponse())
            ->setId(1)
            ->setSubmittedBy($student)
            ->setSubmittedOnBehalfOf($student)
            ->setMarkedComplete(false);
    }

    /**
     * @return TaskResponse
     */
    private function getSelfAssessedResponseMarkedAsComplete(): TaskResponse
    {
        $student = (new User())->setId(1);

        return (new TaskResponse())
            ->setId(1)
            ->setSubmittedBy($student)
            ->setSubmittedOnBehalfOf($student)
            ->setMarkedComplete(true);
    }


    /**
     * @return TaskResponse
     * @throws \InvalidArgumentException
     */
    private function getTeacherAssessedResponseMarkedAsIncomplete(): TaskResponse
    {
        $student = (new User())->setId(1);
        $teacher = (new User())->setId(2);

        $response = (new TaskResponse())
            ->setId(1)
            ->setSubmittedBy($teacher)
            ->setSubmittedOnBehalfOf($student)
            ->setMarkedComplete(false);

        $feedback = (new TaskFeedback())
            ->setResponse($response);

        $response->getAllFeedback()->add($feedback);

        return $response;
    }


    /**
     * @return TaskResponse
     * @throws \InvalidArgumentException
     */
    private function getTeacherAssessedResponseMarkedAsComplete(): TaskResponse
    {
        return $this->getTeacherAssessedResponseMarkedAsIncomplete()->setMarkedComplete(true);
    }
}
