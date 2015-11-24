<?php
/**
 * Translator from Lahore's ASC Timetables XML export to Schoolbox CSV
 * timetable import format.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_External_Lahore_Translator_Timetable extends Synchronise_External_Lahore_Translator_Base
{
    /**
     * Translates the input XML file to a timetable structure.
     *
     * @return Iterator|array[]
     */
    public function translateXmlToArray()
    {
        $reducer = new Synchronise_External_Lahore_Translator_Timetable_Reduce();
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
            new Synchronise_External_Lahore_Translator_Timetable_Map(
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

        // Each campus/term combo gets this import once
        // (For Lahore, there only ought to be one combo)
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
                    $subject       = $result['subjects'][$subjectId];
                    $resultSubject = [
                        'Class Code'  => $subject['code'],
                        'Class Title' => $subject['name'],
                    ];

                    // Probably don't need classes at all, but if I do it's to get teachers and rooms
                    // Pluck out classes from lesson
                    foreach ($lesson['classIds'] as $class) {
                        if (isset($result['classes'][$class])) {
                            $classroomIds = array_merge($result['classes'][$class]['classroomIds'], $classroomIds);
                            $teacherIds[] = $result['classes'][$class]['teacherId'];
                        }
                    }

                    // Add an entry for every teacher
                    foreach (array_unique($teacherIds) as $teacherId) {
                        if (!isset($result['teachers'][$teacherId])) {
                            continue;
                        }
                        $resultTeacher = [
                            'Teacher Code'      => $result['teachers'][$teacherId]['short'],
                            'Teacher Firstname' => $result['teachers'][$teacherId]['firstname'],
                            'Teacher Lastname'  => $result['teachers'][$teacherId]['lastname'],
                        ];

                        // Pluck out period from days in card
                        foreach ($card['days'] as $day) {
                            $resultPeriod = [
                                'Period Day'   => $day,
                                'Period Title' => $card['period'],
                            ];

                            // Pluck out everything else
                            $endResult[] = array_merge(
                                $resultTerm,
                                $resultPeriod,
                                $resultSubject,
                                $resultTeacher
                            );
                        }
                    }
                }
            }
        }
        return $endResult;

//        echo "<?php\n";
//        var_export($result);
//        echo ";\n";

//        $fields = [
//            'Term Campus'       => 'The campus to import a timetable for (must already be imported)',
//            'Term Title'        => 'The name of the term (must already be imported)',
//            'Period Day'        => 'The day number that this period is on (must already be imported)',
//            'Period Title'      => 'The name of this period (must already be imported)',
//            'Class Code'        => 'The code for the class',
//            'Class Title'       => 'The display name for the class',
//            'Class Room'        => '(optional) The room that this class is in',
//            'Class Description' => '(optional) A description for this class',
//            'Teacher Code'      => 'The code for the teacher of this class (must correspond to a teacher\'s external ID)',
//        ];

//        $endResult = [];
//        if ($result['termStart'] instanceof Alaress_Date_Format) {
//
//            // Get all timetable cycles, and use that to set term vars.
//
//            $termStart  = $result['termStart']->getDatabaseDate();
//            $termFinish = clone $result['termStart'];
//            $termFinish = $termFinish->addYears(1)->getDatabaseDate();
//            foreach (\Schoolbox\CampusMapper::getInstance()->findAll() as $campus) {
//                /** @var \Schoolbox\Campus $campus */
//                foreach ($result['periods'] as $period) {
//                    foreach (range(1, $result['dayCount']) as $day) {
//                        $endResult[] = [
//                            'Term Campus'   => $campus->getExtTimetableCode(),
//                            'Term Title'    => $result['termTitle'],
//                            'Term Start'    => $termStart,
//                            'Term Finish'   => $termFinish,
//                            'Term Offset'   => 0,
//                            'Period Day'    => $day,
//                            'Period Title'  => 'Day '.$day,
//                            'Period Start'  => $period['start'],
//                            'Period Finish' => $period['finish'],
//                        ];
//                    }
//                }
//            }
//        }
//        return $endResult;
    }
}
