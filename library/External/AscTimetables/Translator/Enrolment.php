
<?php

/**
 * Translator from ASC Timetables XML export to Schoolbox CSV enrolment import.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class External_AscTimetables_Translator_Enrolment extends External_AscTimetables_Translator_Base
{
    /**
     * Translates the input XML file to a timetable structure.
     *
     * @return Iterator|array[]
     */
    public function translateXmlToArray()
    {
        $reducer = new External_AscTimetables_Translator_Enrolment_Reduce();
        $result  = [];
        foreach (
            new External_AscTimetables_Translator_Enrolment_Map(
                new Alaress\Xml\Filter\Path(
                    $this->iterator,
                    [
                        '//studentsubjects/studentsubject',
                        '//subjects/subject',
                    ]
                )
            ) as $row
        ) {
            $result = $reducer($result, $row);
        }

        // Import each class's students
        $endResult = [];
        foreach ($result as $subject) {
            foreach ($subject['students'] as $studentCode) {
                $endResult[] = [
                    'Class Code'   => $subject['code'],
                    'Student Code' => $studentCode,
                ];
            }
        }
        return $endResult;
    }
}
