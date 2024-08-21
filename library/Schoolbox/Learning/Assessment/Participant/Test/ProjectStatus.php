<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Tests that the various is*() state property methods work for Project Participants
 *
 * Also ensures the states are mutually exclusive
 *
 * @see Participant::isResponseRequired()
 * @see Participant::isFeedbackRequired()
 * @see Participant::isReviewComplete()
 * @see Participant::hasSubmittedResponse()
 * @see Participant::hasPublishedFeedback()
 */
class ProjectStatus extends TestCase
{
    /**
     * @param Participant $participant
     * @param bool        $isResponseRequired
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerRequiresResponseOptionalSubAssessments')]
    public function testRequiresResponseOptionalSubAssessments(Participant $participant, $isResponseRequired)
    {
        $expected = $isResponseRequired;
        $actual = $participant->isResponseRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @param Participant $participant
     * @param bool        $isResponseRequired
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerRequiresResponseMandatorySubAssessments')]
    public function testRequiresResponseMandatorySubAssessments(Participant $participant, $isResponseRequired)
    {
        $expected = $isResponseRequired;
        $actual = $participant->isResponseRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @param Participant $participant
     * @param bool        $isFeedbackRequired
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerRequiresFeedback')]
    public function testRequiresFeedback(Participant $participant, $isFeedbackRequired)
    {
        $actual = $participant->isFeedbackRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($isFeedbackRequired ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($isFeedbackRequired, $actual, $message);
    }

    /**
     * @param Participant $participant
     * @param bool        $isReviewComplete
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReviewCompleteOptionalSubAssessments')]
    public function testReviewCompleteOptionalSubAssessments(Participant $participant, $isReviewComplete)
    {
        $actual = $participant->isReviewComplete();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($isReviewComplete ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($isReviewComplete, $actual, $message);
    }

    /**
     * @param Participant $participant
     * @param bool        $isReviewComplete
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReviewCompleteMandatorySubAssessments')]
    public function testReviewCompleteMandatorySubAssessments(Participant $participant, $isReviewComplete)
    {
        $actual = $participant->isReviewComplete();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($isReviewComplete ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($isReviewComplete, $actual, $message);
    }

    /**
     * @param Participant $participant
     * @throws UnexpectedValueException If unable to merge collections
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerParticipants')]
    public function testMutualExclusiveStatesIfAllSame(Participant $participant)
    {
        /** @var Project $project */
        $project = $participant->getAssessable();

        // Only test the projects which have all sub-assessments in the same state.
        foreach ($project->getAssessables() as $subAssessment) {
            if ($subAssessment->isParticipant($participant->getUser())) {
                $subParticipant = $subAssessment->getParticipant($participant->getUser());
                $subResponseRequired = $subParticipant->isResponseRequired();
                $subFeedbackRequired = $subParticipant->isFeedbackRequired();
                $subReviewComplete = $subParticipant->isReviewComplete();

                if ($subResponseRequired !== $subFeedbackRequired || $subFeedbackRequired !== $subReviewComplete) {
                    $this->markTestSkipped('Data set does not have all sub assessment in the same state, skipped');
                }
            }
        }

        $countStates = 0;
        if ($participant->isResponseRequired()) {
            $countStates++;
        }
        if ($participant->isFeedbackRequired()) {
            $countStates++;
        }
        if ($participant->isReviewComplete()) {
            $countStates++;
        }

        $message = "User: {$participant->getUser()->getFirstname()}, Expected count: 1, Actual count: $countStates.";
        $this->assertEquals(1, $countStates, $message);
    }

    // ================= Data Providers =================


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised $userChar is attempted
     * @throws UnexpectedValueException If unable to merge collections
     */
    public static function providerRequiresResponseOptionalSubAssessments(): array
    {
        // Only 'Maximum' implies optionality
        $project = DemoData::getProject(new Maximum());

        return [
            [$project->getParticipant(DemoData::getDemoStudent('A')), true],
            [$project->getParticipant(DemoData::getDemoStudent('B')), true],
            [$project->getParticipant(DemoData::getDemoStudent('C')), false],
            [$project->getParticipant(DemoData::getDemoStudent('D')), false],
            [$project->getParticipant(DemoData::getDemoStudent('E')), false],
            [$project->getParticipant(DemoData::getDemoStudent('F')), false],
            [$project->getParticipant(DemoData::getDemoStudent('G')), false],
            [$project->getParticipant(DemoData::getDemoStudent('H')), false],
            [$project->getParticipant(DemoData::getDemoStudent('I')), false],
            [$project->getParticipant(DemoData::getDemoStudent('J')), false],
            [$project->getParticipant(DemoData::getDemoStudent('K')), false],
            [$project->getParticipant(DemoData::getDemoStudent('L')), false],
            [$project->getParticipant(DemoData::getDemoStudent('M')), false],
            [$project->getParticipant(DemoData::getDemoStudent('N')), false],
            [$project->getParticipant(DemoData::getDemoStudent('O')), true],
            [$project->getParticipant(DemoData::getDemoStudent('P')), false],
            [$project->getParticipant(DemoData::getDemoStudent('Q')), false],
            [$project->getParticipant(DemoData::getDemoStudent('R')), false],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised $userChar is attempted
     * @throws UnexpectedValueException If unable to merge collections
     */
    public static function providerRequiresResponseMandatorySubAssessments(): array
    {
        // 'Minimum', 'Average', 'WeightedAverage' all imply assessments are not expected to be optional
        $project = DemoData::getProject(new Minimum());

        return [
            [$project->getParticipant(DemoData::getDemoStudent('A')), true],
            [$project->getParticipant(DemoData::getDemoStudent('B')), true],
            [$project->getParticipant(DemoData::getDemoStudent('C')), false],
            [$project->getParticipant(DemoData::getDemoStudent('D')), true],
            [$project->getParticipant(DemoData::getDemoStudent('E')), true],
            [$project->getParticipant(DemoData::getDemoStudent('F')), true],
            [$project->getParticipant(DemoData::getDemoStudent('G')), false],
            [$project->getParticipant(DemoData::getDemoStudent('H')), true],
            [$project->getParticipant(DemoData::getDemoStudent('I')), false],
            [$project->getParticipant(DemoData::getDemoStudent('J')), false],
            [$project->getParticipant(DemoData::getDemoStudent('K')), false],
            [$project->getParticipant(DemoData::getDemoStudent('L')), false],
            [$project->getParticipant(DemoData::getDemoStudent('M')), false],
            [$project->getParticipant(DemoData::getDemoStudent('N')), false],
            [$project->getParticipant(DemoData::getDemoStudent('O')), true],
            [$project->getParticipant(DemoData::getDemoStudent('P')), false],
            [$project->getParticipant(DemoData::getDemoStudent('Q')), false],
            [$project->getParticipant(DemoData::getDemoStudent('R')), false],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised $userChar is attempted
     * @throws UnexpectedValueException If unable to merge collections
     */
    public static function providerRequiresFeedback(): array
    {
        // Only 'Maximum' implies optionality
        $project = DemoData::getProject();

        return [
            [$project->getParticipant(DemoData::getDemoStudent('A')), false],
            [$project->getParticipant(DemoData::getDemoStudent('B')), false],
            [$project->getParticipant(DemoData::getDemoStudent('C')), true],
            [$project->getParticipant(DemoData::getDemoStudent('D')), true],
            [$project->getParticipant(DemoData::getDemoStudent('E')), false],
            [$project->getParticipant(DemoData::getDemoStudent('F')), true],
            [$project->getParticipant(DemoData::getDemoStudent('G')), false],
            [$project->getParticipant(DemoData::getDemoStudent('H')), true],
            [$project->getParticipant(DemoData::getDemoStudent('I')), true],
            [$project->getParticipant(DemoData::getDemoStudent('J')), true],
            [$project->getParticipant(DemoData::getDemoStudent('K')), false],
            [$project->getParticipant(DemoData::getDemoStudent('L')), false],
            [$project->getParticipant(DemoData::getDemoStudent('M')), false],
            [$project->getParticipant(DemoData::getDemoStudent('N')), true],
            [$project->getParticipant(DemoData::getDemoStudent('O')), false],
            [$project->getParticipant(DemoData::getDemoStudent('P')), true],
            [$project->getParticipant(DemoData::getDemoStudent('Q')), false],
            [$project->getParticipant(DemoData::getDemoStudent('R')), true],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised $userChar is attempted
     * @throws UnexpectedValueException If unable to merge collections
     */
    public static function providerReviewCompleteOptionalSubAssessments(): array
    {
        // Only 'Maximum' implies optionality
        $project = DemoData::getProject(new Maximum());

        return [
            [$project->getParticipant(DemoData::getDemoStudent('A')), false],
            [$project->getParticipant(DemoData::getDemoStudent('B')), false],
            [$project->getParticipant(DemoData::getDemoStudent('C')), false],
            [$project->getParticipant(DemoData::getDemoStudent('D')), false],
            [$project->getParticipant(DemoData::getDemoStudent('E')), true],
            [$project->getParticipant(DemoData::getDemoStudent('F')), false],
            [$project->getParticipant(DemoData::getDemoStudent('G')), true],
            [$project->getParticipant(DemoData::getDemoStudent('H')), false],
            [$project->getParticipant(DemoData::getDemoStudent('I')), false],
            [$project->getParticipant(DemoData::getDemoStudent('J')), false],
            [$project->getParticipant(DemoData::getDemoStudent('K')), true],
            [$project->getParticipant(DemoData::getDemoStudent('L')), true],
            [$project->getParticipant(DemoData::getDemoStudent('M')), true],
            [$project->getParticipant(DemoData::getDemoStudent('N')), false],
            [$project->getParticipant(DemoData::getDemoStudent('O')), false],
            [$project->getParticipant(DemoData::getDemoStudent('P')), false],
            [$project->getParticipant(DemoData::getDemoStudent('Q')), true],
            [$project->getParticipant(DemoData::getDemoStudent('R')), false],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised $userChar is attempted
     * @throws UnexpectedValueException If unable to merge collections
     */
    public static function providerReviewCompleteMandatorySubAssessments(): array
    {
        // 'Minimum', 'Average', 'WeightedAverage' all imply assessments are not expected to be optional
        $project = DemoData::getProject(new Minimum());

        return [
            [$project->getParticipant(DemoData::getDemoStudent('A')), false],
            [$project->getParticipant(DemoData::getDemoStudent('B')), false],
            [$project->getParticipant(DemoData::getDemoStudent('C')), false],
            [$project->getParticipant(DemoData::getDemoStudent('D')), false],
            [$project->getParticipant(DemoData::getDemoStudent('E')), false],
            [$project->getParticipant(DemoData::getDemoStudent('F')), false],
            [$project->getParticipant(DemoData::getDemoStudent('G')), true],
            [$project->getParticipant(DemoData::getDemoStudent('H')), false],
            [$project->getParticipant(DemoData::getDemoStudent('I')), false],
            [$project->getParticipant(DemoData::getDemoStudent('J')), false],
            [$project->getParticipant(DemoData::getDemoStudent('K')), true],
            [$project->getParticipant(DemoData::getDemoStudent('L')), true],
            [$project->getParticipant(DemoData::getDemoStudent('M')), true],
            [$project->getParticipant(DemoData::getDemoStudent('N')), false],
            [$project->getParticipant(DemoData::getDemoStudent('O')), false],
            [$project->getParticipant(DemoData::getDemoStudent('P')), false],
            [$project->getParticipant(DemoData::getDemoStudent('Q')), true],
            [$project->getParticipant(DemoData::getDemoStudent('R')), false],
        ];
    }


    /**
     * @return Participant[][]
     * @throws InvalidArgumentException If an unrecognised $userChar is attempted
     * @throws UnexpectedValueException If unable to merge collections
     */
    public static function providerParticipants(): array
    {
        $data = [];
        foreach (DemoData::getProject(new Minimum())->getAllParticipants() as $participant) {
            $data[] = [$participant];
        }
        foreach (DemoData::getProject(new Maximum())->getAllParticipants() as $participant) {
            $data[] = [$participant];
        }
        foreach (DemoData::getProject(new WeightedAverage())->getAllParticipants() as $participant) {
            $data[] = [$participant];
        }
        foreach (DemoData::getProject(new Average())->getAllParticipants() as $participant) {
            $data[] = [$participant];
        }
        return $data;
    }
}
