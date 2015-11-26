<?php
/**
 * Map function - converts an XML node from Lahore's ASC Timetables XML export
 * to a more understandable intermediary format.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_AscTimetables_Translator_Enrolment_Map extends IteratorIterator
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
        if ($current->matches('//studentsubjects/studentsubject')) {
            return [
                'type'    => 'student',
                'student' => (string)$attrs->studentid,
                'subject' => (string)$attrs->subjectid,
            ];
        } elseif ($current->matches('//subjects/subject')) {
            return [
                'type'    => 'subject',
                'subject' => (string)$attrs->id,
                'code'    => (string)$attrs->short,
            ];
        }
        // Fall through without matching - return nothing
        return [];
    }
}
