<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant\Test;

use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\ISummator;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\Project;

/**
 * Demo data helpers for tests.
 */
class DemoData
{
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
