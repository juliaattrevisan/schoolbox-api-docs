<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\Completion;
use \InvalidArgumentException;
use \RangeException;

class CompletionTest extends \PHPUnit_Framework_TestCase
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
    public function test_getHumanReadable_IncompleteExplicit($normalised)
    {
        $response = null;
        $expected = Completion::HUMAN_READABLE_INCOMPLETE;

        $markScheme = new Completion();
        $actual = $markScheme->getHumanReadableMark($normalised, $response);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider provider_normalised
     * @param mixed $normalised
     */
    public function test_getHumanReadable_StudentCompleted($normalised)
    {
        $response = new TaskCompletion();
        $expected = Completion::HUMAN_READABLE_STUDENT_COMPLETED;

        $markScheme = new Completion();
        $actual = $markScheme->getHumanReadableMark($normalised, $response);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider provider_normalised
     * @param mixed $normalised
     */
    public function test_getHumanReadable_TeacherCompleted($normalised)
    {
        $response = new TaskCompletion();
        $response->getAllFeedback()->add(new TaskFeedback());
        $expected = Completion::HUMAN_READABLE_TEACHER_COMPLETED;

        $markScheme = new Completion();
        $actual = $markScheme->getHumanReadableMark($normalised, $response);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider provider_normalised
     * @param mixed $normalised
     */
    public function test_getNormalised_IncompleteImplicit($normalised)
    {
        $expected = null;

        $markScheme = new Completion();
        $actual = $markScheme->getNormalisedMark($normalised);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider provider_normalised
     * @param mixed $normalised
     */
    public function test_getNormalised_IncompleteExplicit($normalised)
    {
        $response = null;
        $expected = null;

        $markScheme = new Completion();
        $actual = $markScheme->getNormalisedMark($normalised, $response);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @dataProvider provider_normalised
     * @param mixed $normalised
     */
    public function test_getNormalised_StudentCompleted($normalised)
    {
        $response = new TaskCompletion();
        $expected = null;

        $markScheme = new Completion();
        $actual = $markScheme->getNormalisedMark($normalised, $response);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @dataProvider provider_humanReadable
     * @param mixed $normalised
     */
    public function test_getNormalised_TeacherCompleted($normalised)
    {
        $response = new TaskCompletion();
        $response->getAllFeedback()->add(new TaskFeedback());
        $expected = null;

        $markScheme = new Completion();
        $actual = $markScheme->getNormalisedMark($normalised, $response);
        $this->assertEquals($expected, $actual);
    }

    // ==================== Data Providers ====================

    public function provider_normalised()
    {
        return [
            [null],
            [-1],
            [10],
            [50],
            [200],
            ['   '],
            ['some random string'],
            [IMarkScheme::NORMALISED_MARK_MIN],
            [IMarkScheme::NORMALISED_MARK_MAX],
        ];
    }

    public function provider_humanReadable()
    {
        return [
            [Completion::HUMAN_READABLE_INCOMPLETE],
            [Completion::HUMAN_READABLE_STUDENT_COMPLETED],
            [Completion::HUMAN_READABLE_TEACHER_COMPLETED],
        ];
    }


}