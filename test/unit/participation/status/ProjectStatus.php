<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\ISummator;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\Project;

require_once 'DueWorkStatus.php';
require_once 'QuizStatus.php';
require_once 'TaskStatus.php';

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
class ProjectStatus extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider_requiresResponse_optionalSubAssessments
     * @param Participant $participant
     * @param bool $isResponseRequired
     */
    public function test_requiresResponse_optionalSubAssessments(Participant $participant, $isResponseRequired)
    {
        $expected = $isResponseRequired;
        $actual = $participant->isResponseRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($expected ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_requiresResponse_mandatorySubAssessments
     * @param Participant $participant
     * @param bool $isResponseRequired
     */
    public function test_requiresResponse_mandatorySubAssessments(Participant $participant, $isResponseRequired)
    {
        $expected = $isResponseRequired;
        $actual = $participant->isResponseRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($expected ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_requiresFeedback
     * @param Participant $participant
     * @param bool $isFeedbackRequired
     */
    public function test_requiresFeedback(Participant $participant, $isFeedbackRequired)
    {
        $actual = $participant->isFeedbackRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($isFeedbackRequired ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
        $this->assertEquals($isFeedbackRequired, $actual, $message);
    }

    /**
     * @dataProvider provider_reviewComplete_optionalSubAssessments
     * @param Participant $participant
     * @param bool        $isReviewComplete
     */
    public function test_reviewComplete_optionalSubAssessments(Participant $participant, $isReviewComplete)
    {
        $actual = $participant->isReviewComplete();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($isReviewComplete ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
        $this->assertEquals($isReviewComplete, $actual, $message);
    }

    /**
     * @dataProvider provider_reviewComplete_mandatorySubAssessments
     * @param Participant $participant
     * @param bool        $isReviewComplete
     */
    public function test_reviewComplete_mandatorySubAssessments(Participant $participant, $isReviewComplete)
    {
        $actual = $participant->isReviewComplete();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($isReviewComplete ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
        $this->assertEquals($isReviewComplete, $actual, $message);
    }

    /**
     * @dataProvider provider_participants
     * @param Participant $participant
     */
    public function test_mutual_exclusive_states_ifAllSame(Participant $participant)
    {
        /** @var Project $project */
        $project = $participant->getAssessable();

        // Only test the projects which have all sub-assessments in the same state.
        foreach($project->getAssessables() as $subAssessment){
            if($subAssessment->isParticipant($participant->getUser())){
                $subParticipant = $subAssessment->getParticipant($participant->getUser());
                $subResponseRequired = $subParticipant->isResponseRequired();
                $subFeedbackRequired = $subParticipant->isFeedbackRequired();
                $subReviewComplete   = $subParticipant->isReviewComplete();

                if($subResponseRequired != $subFeedbackRequired || $subFeedbackRequired != $subReviewComplete){
                    return;
                }
            }
        }

        $countStates = 0;
        if($participant->isResponseRequired()){
            $countStates++;
        }
        if($participant->isFeedbackRequired()){
            $countStates++;
        }
        if($participant->isReviewComplete()){
            $countStates++;
        }

        $message = "User: {$participant->getUser()->getFirstname()}, Expected count: 1, Actual count: $countStates.";
        $this->assertEquals(1, $countStates, $message);
    }

    // ================= Data Providers =================

    public function provider_requiresResponse_optionalSubAssessments()
    {
        // Only 'Maximum' implies optionality
        $project = self::getProject(new Maximum());

        return [
            [$project->getParticipant(self::getDemoStudent('A')), true],
            [$project->getParticipant(self::getDemoStudent('B')), true],
            [$project->getParticipant(self::getDemoStudent('C')), false],
            [$project->getParticipant(self::getDemoStudent('D')), false],
            [$project->getParticipant(self::getDemoStudent('E')), false],
            [$project->getParticipant(self::getDemoStudent('F')), false],
            [$project->getParticipant(self::getDemoStudent('G')), false],
            [$project->getParticipant(self::getDemoStudent('H')), false],
            [$project->getParticipant(self::getDemoStudent('I')), false],
            [$project->getParticipant(self::getDemoStudent('J')), false],
            [$project->getParticipant(self::getDemoStudent('K')), false],
            [$project->getParticipant(self::getDemoStudent('L')), false],
            [$project->getParticipant(self::getDemoStudent('M')), false],
            [$project->getParticipant(self::getDemoStudent('N')), false],
            [$project->getParticipant(self::getDemoStudent('O')), true],
            [$project->getParticipant(self::getDemoStudent('P')), false],
            [$project->getParticipant(self::getDemoStudent('Q')), false],
            [$project->getParticipant(self::getDemoStudent('R')), false],
        ];
    }

    public function provider_requiresResponse_mandatorySubAssessments()
    {
        // 'Minimum', 'Average', 'WeightedAverage' all imply assessments are not expected to be optional
        $project = self::getProject(new Minimum());

        return [
            [$project->getParticipant(self::getDemoStudent('A')), true],
            [$project->getParticipant(self::getDemoStudent('B')), true],
            [$project->getParticipant(self::getDemoStudent('C')), false],
            [$project->getParticipant(self::getDemoStudent('D')), true],
            [$project->getParticipant(self::getDemoStudent('E')), true],
            [$project->getParticipant(self::getDemoStudent('F')), true],
            [$project->getParticipant(self::getDemoStudent('G')), false],
            [$project->getParticipant(self::getDemoStudent('H')), true],
            [$project->getParticipant(self::getDemoStudent('I')), false],
            [$project->getParticipant(self::getDemoStudent('J')), false],
            [$project->getParticipant(self::getDemoStudent('K')), false],
            [$project->getParticipant(self::getDemoStudent('L')), false],
            [$project->getParticipant(self::getDemoStudent('M')), false],
            [$project->getParticipant(self::getDemoStudent('N')), false],
            [$project->getParticipant(self::getDemoStudent('O')), true],
            [$project->getParticipant(self::getDemoStudent('P')), false],
            [$project->getParticipant(self::getDemoStudent('Q')), false],
            [$project->getParticipant(self::getDemoStudent('R')), false],
        ];
    }

    public function provider_requiresFeedback()
    {
        // Only 'Maximum' implies optionality
        $project = self::getProject();

        return [
            [$project->getParticipant(self::getDemoStudent('A')), false],
            [$project->getParticipant(self::getDemoStudent('B')), false],
            [$project->getParticipant(self::getDemoStudent('C')), true],
            [$project->getParticipant(self::getDemoStudent('D')), true],
            [$project->getParticipant(self::getDemoStudent('E')), false],
            [$project->getParticipant(self::getDemoStudent('F')), true],
            [$project->getParticipant(self::getDemoStudent('G')), false],
            [$project->getParticipant(self::getDemoStudent('H')), true],
            [$project->getParticipant(self::getDemoStudent('I')), true],
            [$project->getParticipant(self::getDemoStudent('J')), true],
            [$project->getParticipant(self::getDemoStudent('K')), false],
            [$project->getParticipant(self::getDemoStudent('L')), false],
            [$project->getParticipant(self::getDemoStudent('M')), false],
            [$project->getParticipant(self::getDemoStudent('N')), true],
            [$project->getParticipant(self::getDemoStudent('O')), false],
            [$project->getParticipant(self::getDemoStudent('P')), true],
            [$project->getParticipant(self::getDemoStudent('Q')), false],
            [$project->getParticipant(self::getDemoStudent('R')), true],
        ];
    }

    public function provider_reviewComplete_optionalSubAssessments()
    {
        // Only 'Maximum' implies optionality
        $project = self::getProject(new Maximum());

        return [
            [$project->getParticipant(self::getDemoStudent('A')), false],
            [$project->getParticipant(self::getDemoStudent('B')), false],
            [$project->getParticipant(self::getDemoStudent('C')), false],
            [$project->getParticipant(self::getDemoStudent('D')), false],
            [$project->getParticipant(self::getDemoStudent('E')), true],
            [$project->getParticipant(self::getDemoStudent('F')), false],
            [$project->getParticipant(self::getDemoStudent('G')), true],
            [$project->getParticipant(self::getDemoStudent('H')), false],
            [$project->getParticipant(self::getDemoStudent('I')), false],
            [$project->getParticipant(self::getDemoStudent('J')), false],
            [$project->getParticipant(self::getDemoStudent('K')), true],
            [$project->getParticipant(self::getDemoStudent('L')), true],
            [$project->getParticipant(self::getDemoStudent('M')), true],
            [$project->getParticipant(self::getDemoStudent('N')), false],
            [$project->getParticipant(self::getDemoStudent('O')), false],
            [$project->getParticipant(self::getDemoStudent('P')), false],
            [$project->getParticipant(self::getDemoStudent('Q')), true],
            [$project->getParticipant(self::getDemoStudent('R')), false],
        ];
    }

    public function provider_reviewComplete_mandatorySubAssessments()
    {
        // 'Minimum', 'Average', 'WeightedAverage' all imply assessments are not expected to be optional
        $project = self::getProject(new Minimum());

        return [
            [$project->getParticipant(self::getDemoStudent('A')), false],
            [$project->getParticipant(self::getDemoStudent('B')), false],
            [$project->getParticipant(self::getDemoStudent('C')), false],
            [$project->getParticipant(self::getDemoStudent('D')), false],
            [$project->getParticipant(self::getDemoStudent('E')), false],
            [$project->getParticipant(self::getDemoStudent('F')), false],
            [$project->getParticipant(self::getDemoStudent('G')), true],
            [$project->getParticipant(self::getDemoStudent('H')), false],
            [$project->getParticipant(self::getDemoStudent('I')), false],
            [$project->getParticipant(self::getDemoStudent('J')), false],
            [$project->getParticipant(self::getDemoStudent('K')), true],
            [$project->getParticipant(self::getDemoStudent('L')), true],
            [$project->getParticipant(self::getDemoStudent('M')), true],
            [$project->getParticipant(self::getDemoStudent('N')), false],
            [$project->getParticipant(self::getDemoStudent('O')), false],
            [$project->getParticipant(self::getDemoStudent('P')), false],
            [$project->getParticipant(self::getDemoStudent('Q')), true],
            [$project->getParticipant(self::getDemoStudent('R')), false],
        ];
    }


    /**
     * @return Participant[][]
     */
    public function provider_participants()
    {
        $data = [];
        foreach(self::getProject(new Minimum())->getAllParticipants() as $participant){
            $data[] = [$participant];
        }
        foreach(self::getProject(new Maximum())->getAllParticipants() as $participant){
            $data[] = [$participant];
        }
        foreach(self::getProject(new WeightedAverage())->getAllParticipants() as $participant){
            $data[] = [$participant];
        }
        foreach(self::getProject(new Average())->getAllParticipants() as $participant){
            $data[] = [$participant];
        }
        return $data;
    }

    // ================= Demo Data Helpers ==============
    /**
     * A cache of User objects, indexed by the first letter of their name, capitalised, eg 'A' for 'Andy', 'B' for Barry
     *
     * @var User[]|GenericCollection<User>
     */
    private static $studentCache;

    /**
     * A cache of User objects, indexed by the first letter of their name, capitalised, eg 'T' for 'Trevor'
     *
     * @var User[]|GenericCollection<User>
     */
    private static $teacherCache;


    /**
     * Generates a Project with the following sub-assessments:
     *
     * * self-assessable task
     * * non-self-assessable task
     * * quiz
     * * dueWork
     *
     * * User A
     * * * Task1   - responseRequired
     * * * Task2   - responseRequired
     * * * Quiz    - responseRequired
     * * * DueWork - responseRequired
     * * User B
     * * * Task1   - responseRequired
     * * * Task2   - responseRequired
     * * * Quiz    - responseRequired
     * * * DueWork - responseRequired
     * * User C
     * * * Task1   - feedbackRequired
     * * * Task2   - reviewComplete
     * * * Quiz    - feedbackRequired
     * * * DueWork - feedbackRequired
     * * User D
     * * * Task1   - responseRequired
     * * * Task2   - responseRequired
     * * * Quiz    - reviewComplete
     * * * DueWork - feedbackRequired
     * * User E
     * * * Task1   - reviewComplete
     * * * Task2   - reviewComplete
     * * * Quiz    - reviewComplete
     * * * DueWork - responseRequired
     * * User F
     * * * Task1   - responseRequired
     * * * Quiz    - feedbackRequired
     * * * DueWork - reviewComplete
     * * User G
     * * * Task1   - reviewComplete
     * * * DueWork - reviewComplete
     * * User H
     * * * Task1   - responseRequired
     * * * DueWork - feedbackRequired
     * * User I
     * * * Task1   - feedbackRequired
     * * * DueWork - reviewComplete
     * * User J
     * * * DueWork - feedbackRequired
     * * User K
     * * * DueWork - reviewComplete
     * * User L
     * * * DueWork - reviewComplete
     * * User M
     * * * DueWork - reviewComplete
     * * User N
     * * * DueWork - feedbackRequired
     * * User O
     * * * DueWork - responseRequired
     * * User P
     * * * DueWork - feedbackRequired
     * * User Q
     * * * DueWork - reviewComplete
     * * User R
     * * * DueWork - feedbackRequired
     *
     * @param ISummator $summator
     * @return Project
     */
    public static function getProject(ISummator $summator = null)
    {
        $project = (new Project($summator))
            ->setId(1);

        $project->getAssessables()
            ->add(TaskStatus::getSelfAssessableTask(2))
            ->add(TaskStatus::getNonSelfAssessableTask(3))
            ->add(QuizStatus::getQuiz(4))
            ->add(DueWorkStatus::getDueWork(5));

        // Load the project participants
        foreach($project->getAssessables() as $subAssessable){
            foreach($subAssessable->getAllParticipants() as $subParticipant){
                // Does project contain this user yet?
                if(!$project->isParticipant($subParticipant->getUser())){
                    $project->getAllParticipants()->add((new Participant($subParticipant->getUser(), $project)));
                }
            }
        }

        return $project;
    }


    /**
     * @param string $userChar - a single char representing their first name, eg 'A', 'B', etc
     * @return User
     */
    public static function getDemoStudent($userChar)
    {
        if(is_null(self::$studentCache)){
            self::$studentCache = new GenericCollection(User::class);
        }

        // Use cache if possible
        if (isset(self::$studentCache[$userChar]) && self::$studentCache[$userChar] instanceof User) {
            return self::$studentCache[$userChar];
        }

        $user = (new User())->setId($userChar);
        switch ($userChar) {
            case 'A':
                $user->setFirstname('Andrew (Student)');
                break;
            case 'B':
                $user->setFirstname('Barry (Student)');
                break;
            case 'C':
                $user->setFirstname('Charlie (Student)');
                break;
            case 'D':
                $user->setFirstname('David (Student)');
                break;
            case 'E':
                $user->setFirstname('Ethan (Student)');
                break;
            case 'F':
                $user->setFirstname('Fred (Student)');
                break;
            case 'G':
                $user->setFirstname('Gary (Student)');
                break;
            case 'H':
                $user->setFirstname('Harry (Student)');
                break;
            case 'I':
                $user->setFirstname('Irwin (Student)');
                break;
            case 'J':
                $user->setFirstname('Jake (Student)');
                break;
            case 'K':
                $user->setFirstname('Kate (Student)');
                break;
            case 'L':
                $user->setFirstname('Liam (Student)');
                break;
            case 'M':
                $user->setFirstname('Mary (Student)');
                break;
            case 'N':
                $user->setFirstname('Nate (Student)');
                break;
            case 'O':
                $user->setFirstname('Olly (Student)');
                break;
            case 'P':
                $user->setFirstname('Pete (Student)');
                break;
            case 'Q':
                $user->setFirstname('Quinn (Student)');
                break;
            case 'R':
                $user->setFirstname('Rory (Student)');
                break;
            default:
                throw new InvalidArgumentException("Unknown userChar: $userChar");
        }
        self::$studentCache[$userChar] = $user;
        return $user;
    }

    /**
     * @param string $userChar - a single char representing their first name, eg 'T', etc
     * @return User
     */
    public static function getDemoTeacher($userChar)
    {
        if(is_null(self::$teacherCache)){
            self::$teacherCache = new GenericCollection(User::class);
        }

        // Use cache if possible
        if (isset(self::$teacherCache[$userChar]) && self::$teacherCache[$userChar] instanceof User) {
            return self::$teacherCache[$userChar];
        }

        $user = (new User())->setId($userChar);
        switch ($userChar) {
            case 'T':
                $user->setFirstname('Trevor (Teacher)');
                break;
            default:
                throw new InvalidArgumentException("Unknown userChar: $userChar");
        }
        self::$teacherCache[$userChar] = $user;
        return $user;
    }

}
