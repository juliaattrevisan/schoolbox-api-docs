<?php

/**
 * Translator from ASC Timetables XML export to Schoolbox CSV timetable import.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class External_AscTimetables_Translator_Timetable extends External_AscTimetables_Translator_Base
{
    /**
     * Translates the input XML file to a timetable structure.
     *
     * @return Iterator|array[]
     */
    public function translateXmlToArray()
    {
        $reducer = new External_AscTimetables_Translator_Timetable_Reduce();
        $result  = [
            'cards'      => [],
            'terms'      => [['name' => 'Whole Year']],
            'lessons'    => [],
            'subjects'   => [],
            'classrooms' => [],
            'classes'    => [],
            'teachers'   => [],
        ];
        foreach (
            new External_AscTimetables_Translator_Timetable_Map(
                new Alaress\Xml\Filter\Path(
                    $this->iterator,
                    [
                        '//cards/card',
                        '//lessons/lesson',
                        '//classrooms/classroom',
                        '//classes/class',
                        '//teachers/teacher',
                        '//subjects/subject',
                    ]
                )
            ) as $row
        ) {
            $result = $reducer($result, $row);
        }

        // Each campus/term combo gets this import once.
        // TODO revise this assumption when a school has multiple campuses
        $endResult = [];
        foreach (\Schoolbox\CampusMapper::getInstance()->findAll() as $campus) {
            foreach ($result['terms'] as $term) {
                $resultTerm = [
                    'Term Campus' => $campus->getExtTimetableCode(),
                    'Term Title'  => $term['name'],
                ];
                // Delve into each card, and pluck out its period day and title
                foreach ($result['cards'] as $card) {
                    // Pluck out period from card
                    $resultPeriod = [
                        'Period Day'   => $card['day'],
                        'Period Title' => $card['period'],
                    ];

                    // Pluck out classroom from card (if there is one)
                    $classroomIds = $card['classroomIds'];
                    $classes      = [];

                    // Pluck out lesson from card
                    $lessonId = $card['lessonId'];
                    if (!isset($result['lessons'][$lessonId])) {
                        continue;
                    }
                    $lesson = $result['lessons'][$lessonId];

                    // Pluck out teachers from lesson
                    // Allow an empty teacher record
                    $teacherIds = $lesson['teacherIds'];
                    if (empty($teacherIds)) {
                        $teacherIds = [''];
                    }

                    // Pluck out subject from lesson
                    $subjectId = $lesson['subjectId'];
                    if (!isset($result['subjects'][$subjectId])) {
                        continue;
                    }
                    $subject = $result['subjects'][$subjectId];

                    // Get teachers and rooms from classes
                    foreach ($lesson['classIds'] as $class) {
                        if (isset($result['classes'][$class])) {
                            $classes[] = $result['classes'][$class];
                        }
                    }

                    // Add an entry for every teacher/classCode
                    foreach (array_unique($teacherIds) as $teacherId) {
                        if (empty($teacherId)) {
                            $resultTeacher = [
                                'Teacher Code'      => '',
                                'Teacher Firstname' => '',
                                'Teacher Lastname'  => '',
                            ];
                        } elseif (isset($result['teachers'][$teacherId])) {
                            $resultTeacher = [
                                'Teacher Code'      => $result['teachers'][$teacherId]['short'],
                                'Teacher Firstname' => $result['teachers'][$teacherId]['firstname'],
                                'Teacher Lastname'  => $result['teachers'][$teacherId]['lastname'],
                            ];
                        } else {
                            continue;
                        }

                        // Go thru each class entry for this lesson, and add its classroom.
                        foreach ($classes as $class) {
                            // Certain subjects are school specific or year level specific.
                            // I can't figure out a better way to handle this other than hardcoding.
                            // TODO move this somewhere LGSPK specific
                            switch (strtolower($subject['code'])) {
                                case 'asb':
                                    $classCode = $subject['code'];
                                    break;
                                case 'urdu debates':
                                case 'gym display':
                                    $classCode = $subject['code'] . ' ' . preg_replace('/\\D/', '', $class['code']);
                                    break;
                                default:
                                    $classCode = $subject['code'] . ' ' . $class['code'];
                                    break;
                            }
                            $resultClass = [
                                'Class Code'        => $classCode,
                                'Class Title'       => $subject['name'],
                                'Class Description' => '',
                            ];
                            // Add an entry for every classroom
                            // (Allow a single blank entry, for classes with no classroom)
                            $classClassroomIds = array_filter(
                                array_unique(
                                    array_merge(
                                        $class['classroomIds'],
                                        $classroomIds
                                    )
                                )
                            );
                            if (empty($classClassroomIds)) {
                                $classClassroomIds = [''];
                            }
                            foreach ($classClassroomIds as $classroomId) {
                                // Disable classrooms for now
                                $resultClassroom = [];
//                                $resultClassroom = [
//                                    'Class Room' => isset($result['classrooms'][$classroomId])
//                                        ? $result['classrooms'][$classroomId]['code']
//                                        : '',
//                                ];

                                // We finally have a result
                                $endResult[] = array_merge(
                                    $resultTerm,
                                    $resultPeriod,
                                    $resultClass,
                                    $resultClassroom,
                                    $resultTeacher
                                );
                            }
                        }
                    }
                }
            }
        }
        return $endResult;
    }
}
