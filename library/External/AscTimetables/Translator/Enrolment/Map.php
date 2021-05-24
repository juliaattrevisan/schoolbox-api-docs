<?php

/**
 * Map function - converts an XML node from ASC Timetables XML export to a more
 * understandable intermediary format.
 *
 * Mapped values are used in the creation of the student enrolments.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
