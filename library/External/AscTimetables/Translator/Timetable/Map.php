<?php

/**
 * Map function - converts an XML node from ASC Timetables XML export to a more
 * understandable intermediary format.
 *
 * Mapped values are used in the creation of the timetable.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class External_AscTimetables_Translator_Timetable_Map extends IteratorIterator
{
    /**
     * @return array
     */
    public function current()
    {
        /** @var Alaress\Xml\State $current */
        $current = $this->getInnerIterator()->current();
        $result  = new SimpleXMLElement($current->getReader()->readOuterXml());
        $attrs   = $result->attributes();
        if ($current->matches('//cards/card')) {
            return [
                'type'         => 'card',
                'lessonId'     => (string)$attrs->lessonid,
                'classroomIds' => explode(',', (string)$attrs->classroomids),
                'day'          => (int)$attrs->day + 1,
                'period'       => (string)$attrs->period,
            ];
        } elseif ($current->matches('//lessons/lesson')) {
            return [
                'type'         => 'lesson',
                'id'           => (string)$attrs->id,
                'termId'       => (string)$attrs->termsdefid,
                'subjectId'    => (string)$attrs->subjectid,
                'teacherIds'   => explode(',', (string)$attrs->teacherids),
                'classIds'     => explode(',', (string)$attrs->classids),
            ];
        } elseif ($current->matches('//subjects/subject')) {
            return [
                'type'         => 'subject',
                'id'           => (string)$attrs->id,
                'code'         => (string)$attrs->short,
                'name'         => (string)$attrs->name,
            ];
        } elseif ($current->matches('//classrooms/classroom')) {
            return [
                'type'         => 'classroom',
                'id'           => (string)$attrs->id,
                'code'         => (string)$attrs->short,
                'name'         => (string)$attrs->name,
            ];
        } elseif ($current->matches('//classes/class')) {
            return [
                'type'         => 'class',
                'id'           => (string)$attrs->id,
                'code'         => (string)$attrs->short,
                'classroomIds' => explode(',', (string)$attrs->classroomids),
                'teacherId'    => (string)$attrs->teacherid,
            ];
        } elseif ($current->matches('//teachers/teacher')) {
            return [
                'type'         => 'teacher',
                'id'           => (string)$attrs->id,
                'firstname'    => (string)$attrs->firstname,
                'lastname'     => (string)$attrs->lastname,
                'short'        => (string)$attrs->short,
            ];
        }
        // Fall through without matching - return nothing
        return [];
    }
}
