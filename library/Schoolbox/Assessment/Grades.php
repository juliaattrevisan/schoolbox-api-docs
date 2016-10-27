<?php
/**
 * @author    Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment;

use Alaress_Date_Format;
use Schoolbox\Learning\Quiz\Model;
use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;
use Schoolbox\Learning\Quiz\Mapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class Grades
{

    protected static $instance;

    /**
    * Retrieves the singleton instance of this class.
    *
    * @return static
    */
    public static function getInstance()
    {
        $class_name = get_called_class();
        if (!isset(self::$instance[$class_name])) {
            self::$instance[$class_name] = new static();
        }
        return self::$instance[$class_name];
    }

    /**
    * Accepts an array of users, and returns their grades for the given subject.
    * Mapper-based replacement of SubmissionReturn::getStudentMarksForCategory()
    *
    * @param Collection<User> $students
    * @param Folder $subject
    * @param int $year
    * @param int $semester
    * @param array $outputFormats
    * @return array
    */
    public function getStudentGradesForSubject(
        $students,
        $subject,
        Alaress_Date_Format $from = null,
        Alaress_Date_Format $to = null,
        $outputFormats = array('float')
    ) {

        $params = array(
            'from' => $from,
            'to'   => $to,
        );

        $assessmentsSeen = array(
            'sb' => array(),
            'oa' => array()
        );
        $marks = array();

        foreach ($students as $user) {
            /** @var User $user */

            // get all OA marks for this student in this category
            $marks['oa'][$user->getId()] = AttemptMapper::getInstance()->getStudentMarks(
                $subject->getId(),
                $user->getId(),
                $params
            );

            foreach ($marks['oa'][$user->getId()] as $oa => $assessmentResults) {
                $assessmentsSeen['oa'][$oa] = true;
            }
            unset($oa);
            unset($assessmentResults);

            // get all SB marks for this student in this category
            $marks['sb'][$user->getId()] = ReturnedWorkMapper::getInstance()->getStudentMarks(
                $subject->getId(),
                $user->getId(),
                $params
            );

            foreach ($marks['sb'][$user->getId()] as $sb => $assessmentResults) {
                $assessmentsSeen['sb'][$sb] = true;
            }
            unset($sb);
            unset($assessmentResults);

        }
        unset($user);

        $weightArray = array();

        // retrieve the objects based on those seen...if any were seen at all
        if (count($assessmentsSeen['sb'])) {
            $sbCollection = SubmissionBoxMapper::getInstance()->findByColumn('id', array_keys($assessmentsSeen['sb']));
            $weightArray['sb'] = array_combine($sbCollection->getColumnValues('id'), $sbCollection->getColumnValues('weight'));
        } else {
            $sbCollection = new Collection();
        }

        if (count($assessmentsSeen['oa'])) {
            $oaCollection = Mapper::getInstance()->findByColumn('assess_id', array_keys($assessmentsSeen['oa']));
            $weightArray['oa'] = array_combine($oaCollection->getColumnValues('id'), $oaCollection->getColumnValues('weight'));
        } else {
            $oaCollection = new Collection();
        }

        $assessments = new Collection();
        $assessments->merge($sbCollection)->merge($oaCollection);

        // flatten the weight array
        $assessmentWeights = array();
        foreach ($weightArray as $type => $ws) {
            foreach ($ws as $id => $w) {
                $assessmentWeights["$type-$id"] = $w;
            }
            unset($w);
            unset($id);
        }
        unset($ws);
        unset($type);
        // get rid of this, we don't need it anymore
        unset($weightArray);

        // sort all the things by their respective due-date fields
        $assessments->sortByCallback(function($a, $b) {
            $x = $a instanceof SubmissionBox ? $a->getDueDate()->getUnixTimestamp() : $a->getFinish()->getUnixTimestamp();
            $y = $b instanceof SubmissionBox ? $b->getDueDate()->getUnixTimestamp() : $b->getFinish()->getUnixTimestamp();
            return $x == $y ? 0 : ($x > $y ? 1 : -1);
        });


        /* Every student's grade average is uniquely calculated, based on the assessable items for which they have
		 * received feedback. If they have not received a mark, or received a 'Not assessed', the mark should not
		 * bear any impact on their average.
		 *
		 * It also has to comprehend grade error codes: 'Absent' == 0, 'Not Assessed' == NULL and 'Not Submitted' == 0
		 */

        $weightedGrades = array();
        $applicableWeightsArray = array();

        foreach ($marks as $type => $student) {
            foreach ($student as $userId => $assessmentResults) {
                foreach ($assessmentResults as $assessmentId => $studentMarkInAssessment) {
/* we currently have:
					 *  - $type = "oa" | "sb"
					 *  - $userId = student ID
					 *  - $assessmentId = the numeric ID of the assessment in question
					 *  - $studentMarkInAssessment = array containing ['mark']
					 */

                    $normMark = $studentMarkInAssessment['mark'];

                    // we can disregard null marks
                    if (is_null($normMark)) {
                        continue;
                    }

                    $workId = "$type-$assessmentId";
                    $workWeight = $assessmentWeights[$workId];

                    // add the weight of this work to the applicability of the student
                    $applicableWeightsArray[$userId][$workId] = $workWeight;

                    // add the weighted mark of this work to the student
                    $weightedGrades[$userId][$workId] = $normMark * $workWeight / 100;

                }
            }
        }

        // it's considered good manners to clean up after oneself..
        unset($studentMarkInAssessment);
        unset($assessmentId);
        unset($normMark);
        unset($workId);
        unset($workWeight);
        unset($assessmentResults);
        unset($userId);
        unset($student);
        unset($type);

        $returnGrades = array();

        foreach ($weightedGrades as $userId => $works) {
            $userWeightSum = array_sum($applicableWeightsArray[$userId]);
            if ($userWeightSum > 0) {
                foreach ($works as $workId => $weightedMark) {
                    $returnGrades[$userId]['grades'][$workId] = $weightedMark / $userWeightSum * 100;
                }

                $avgSum = array_sum($works) / $userWeightSum * 100;
            }

            // how do you want this data presented?
            $availableFormats = array('float', 'int', 'letter');

            foreach ($availableFormats as $f) {
                if (in_array($f, $outputFormats)) {
                    switch($f) {
                        case 'float':
                            $returnGrades[$userId]['avg'] = $avgSum;
                            break;
                        case 'int':
                            $returnGrades[$userId]['avg-int'] = (int)($avgSum / 10);
                            break;
                        case 'letter':
                            $returnGrades[$userId]['avg-letter'] = \Marksheet_Factory::build(
                                'letter',
                                new SubmissionBox()
                            )->convertRawMark($avgSum);
                            break;
                    }
                }
            }
        }

        unset($avgSum);
        unset($weightedMark);
        unset($workId);
        unset($works);
        unset($userId);

        return $returnGrades;

    }
}
