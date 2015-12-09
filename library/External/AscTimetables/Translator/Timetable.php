<?php
/**
 * Translator from ASC Timetables XML export to Schoolbox CSV timetable import.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
            'terms'      => [],
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
                        '//termsdefs/termsdef',
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
            /** @var \Schoolbox\Campus $campus */
            foreach ($result['terms'] as $term) {
                $resultTerm = [
                    'Term Campus' => $campus->getExtTimetableCode(),
                    'Term Title'  => $term['name'],
                ];
                // Delve into each card, and pluck out its period day and title
                foreach ($result['cards'] as $card) {

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
                    $teacherIds = $lesson['teacherIds'];

                    // Pluck out subject from lesson
                    $subjectId = $lesson['subjectId'];
                    if (!isset($result['subjects'][$subjectId])) {
                        continue;
                    }
                    $subject = $result['subjects'][$subjectId];

                    // Probably don't need classes at all, but if I do it's to get teachers and rooms
                    // Pluck out classes from lesson
                    foreach ($lesson['classIds'] as $class) {
                        if (isset($result['classes'][$class])) {
                            $classes[] = $result['classes'][$class];
//                            $classroomIds = array_merge($result['classes'][$class]['classroomIds'], $classroomIds);
                        }
                    }

                    // Add an entry for every teacher/classCode
                    foreach (array_filter(array_unique($teacherIds)) as $teacherId) {
                        if (!isset($result['teachers'][$teacherId])) {
                            continue;
                        }
                        $resultTeacher = [
                            'Teacher Code'      => $result['teachers'][$teacherId]['short'],
                            'Teacher Firstname' => $result['teachers'][$teacherId]['firstname'],
                            'Teacher Lastname'  => $result['teachers'][$teacherId]['lastname'],
                        ];

                        // Go thru each class entry for this lesson, and add its classroom.
                        foreach ($classes as $class) {
                            $resultClass = [
                                'Class Code'        => $subject['code'].' '.$class['code'],
                                'Class Title'       => $subject['name'].' '.$class['code'],
                                'Class Room'        => '',
                                'Class Description' => '',
                            ];
                            // Try adding a classroom ID later?
//                        }
//
//                        // Add an entry for every classroom
//                        // (Allow a single blank entry, for classes with no classroom)
//                        $classroomIds = array_filter(array_unique($classroomIds));
//                        if (empty($classroomIds)) {
//                            $classroomIds[] = '';
//                        }
//                        foreach ($classroomIds as $classroomId) {
//                            $resultClassroom = [
//                                'Class Room' => isset($result['classrooms'][$classroomId])
//                                    ? $result['classrooms'][$classroomId]['code']
//                                    : '',
//                            ];

                            // Pluck out period from days in card
                            foreach ($card['days'] as $day) {
                                $resultPeriod = [
                                    'Period Day'   => $day,
                                    'Period Title' => $card['period'],
                                ];

                                // We finally have a result
                                $endResult[] = array_merge(
                                    $resultTerm,
                                    $resultPeriod,
                                    $resultClass,
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
