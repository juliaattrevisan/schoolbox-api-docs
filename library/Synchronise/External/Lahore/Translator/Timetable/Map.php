<?php
/**
 * Map function - converts an XML node from Lahore's ASC Timetables XML export
 * to a more understandable intermediary format.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_External_Lahore_Translator_Timetable_Map extends IteratorIterator
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
                'terms'        => $this->parseBits($attrs->terms),
                'days'         => $this->parseBits($attrs->days),
                'period'       => (string)$attrs->period,
            ];
        } elseif ($current->matches('//termsdefs/termsdef')) {
            return [
                'type'         => 'termsdef',
                'id'           => (string)$attrs->id,
                'name'         => (string)$attrs->name,
                'terms'        => $this->parseBits($attrs->terms),
            ];
        } elseif ($current->matches('//lessons/lesson')) {
            return [
                'type'         => 'lesson',
                'id'           => (string)$attrs->id,
                'termId'       => (string)$attrs->termsdefid,
                'subjectId'    => (string)$attrs->subjectid,
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
        }
        // Fall through without matching - return nothing
        return [];
    }

    /**
     * Parses a bit sequence into a list of set bits.
     *
     * @param $bitString
     *
     * @return array
     */
    protected function parseBits($bitString)
    {
        $result = [];
        foreach (str_split($bitString, 1) as $i => $bit) {
            if (intval($bit) === 1) {
                $result[] = $i+1;
            }
        }
        return $result;
    }
}
