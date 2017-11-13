<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\User;

class CompletionTest extends TestCase
{
    /**
     * @dataProvider provider_normalised
     * @param mixed $normalised
     */
    public function test_getHumanReadable_IncompleteImplicit($normalised)
    {
        $expected = Completion::HUMAN_READABLE_INCOMPLETE;

        $markScheme = new Completion();
        $actual = $markScheme->getHumanReadableMark($normalised);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @dataProvider provider_normalised
     * @param mixed $normalised
     */
    public function test_getHumanReadable_IncompleteAsNull($normalised)
    {
        $expected = Completion::HUMAN_READABLE_INCOMPLETE;

        $markScheme = new Completion();
        $actual = $markScheme->getHumanReadableMark($normalised, null);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @dataProvider provider_normalised
     * @param mixed $normalised
     */
    public function test_getHumanReadable_studentMarkedResponseIncomplete($normalised)
    {
        $response = $this->getSelfAssessedResponseMarkedAsIncomplete();
        $markScheme = new Completion();

        $expected = Completion::HUMAN_READABLE_INCOMPLETE;
        $actual = $markScheme->getHumanReadableMark($normalised, $response);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider provider_normalised
     * @param mixed $normalised
     */
    public function test_getHumanReadable_studentMarkedResponseComplete($normalised)
    {
        $response = $this->getSelfAssessedResponseMarkedAsComplete();
        $markScheme = new Completion();

        $expected = Completion::HUMAN_READABLE_COMPLETE;
        $actual = $markScheme->getHumanReadableMark($normalised, $response);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider provider_normalised
     * @param mixed $normalised
     */
    public function test_getHumanReadable_teacherMarkedResponseIncomplete($normalised)
    {
        $response = $this->getTeacherAssessedResponseMarkedAsIncomplete();
        $markScheme = new Completion();

        $expected = Completion::HUMAN_READABLE_INCOMPLETE;
        $actual = $markScheme->getHumanReadableMark($normalised, $response);

        $this->assertEquals($expected, $actual);
    }


    /**
     * @dataProvider provider_normalised
     * @param mixed $normalised
     */
    public function test_getHumanReadable_teacherMarkedResponseComplete($normalised)
    {
        $response = $this->getTeacherAssessedResponseMarkedAsComplete();
        $markScheme = new Completion();

        $expected = Completion::HUMAN_READABLE_COMPLETE;
        $actual = $markScheme->getHumanReadableMark($normalised, $response);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider provider_humanReadable_noResponse
     * @param mixed $humanReadable
     */
    public function test_getNormalised_noResponse($humanReadable)
    {
        $expected = null;
        $markScheme = new Completion();
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider provider_humanReadable_withResponse
     * @param mixed $humanReadable
     * @param TaskResponse $response
     */
    public function test_getNormalised_withResponse($humanReadable, TaskResponse $response = null)
    {
        if($response && $response->isMarkedComplete()){
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
    public function provider_normalised_valid()
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
    public function provider_normalised_invalid()
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
    public function provider_normalised()
    {
        return array_merge($this->provider_normalised_valid(), $this->provider_normalised_invalid());
    }

    /**
     * @return string[][]
     */
    public function provider_humanReadable_valid()
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
    public function provider_humanReadable_invalid()
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
    public function provider_humanReadable_noResponse()
    {
        return array_merge($this->provider_humanReadable_valid(), $this->provider_humanReadable_invalid());
    }


    /**
     * @return mixed[][]
     */
    public function provider_humanReadable_withResponse()
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

        foreach($humanReadableValues as $humanReadable){
            foreach($responses as $response){
                $data[] = [$humanReadable[0], $response];
            }
        }
        return $data;
    }


    // Private helpers for data providers

    /**
     * @return TaskResponse
     */
    private function getSelfAssessedResponseMarkedAsIncomplete()
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
    private function getSelfAssessedResponseMarkedAsComplete()
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
     */
    private function getTeacherAssessedResponseMarkedAsIncomplete()
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
     */
    private function getTeacherAssessedResponseMarkedAsComplete()
    {
        return $this->getTeacherAssessedResponseMarkedAsIncomplete()->setMarkedComplete(true);

    }
}
