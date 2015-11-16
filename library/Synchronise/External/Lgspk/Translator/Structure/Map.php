<?php
/**
 * Translator from unknown XML format to a CSV representation of timetable
 * structure.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_External_Lgspk_Translator_Structure_Map extends IteratorIterator
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
        if ($current->matches('//termsdefs/termsdef')) {
            return [
                'type'      => 'termsdef',
                'id'        => (string)$attrs->id,
                'termTitle' => (string)$attrs->name,
            ];
        } elseif ($current->matches('//daysdefs/daysdef')) {
            if ((string)$attrs->short === 'E') {
                return [
                    'type'     => 'daysdef',
                    'id'       => (string)$attrs->name,
                    'dayCount' => strlen((string)$attrs->days),
                ];
            } else {
                return [];
            }
        } elseif ($current->matches('//periods/period')) {
            return [
                'type'         => 'period',
                'id'           => (string)$attrs->id,
                'periodTitle'  => (string)$attrs->name,
                'periodStart'  => (string)$attrs->starttime,
                'periodFinish' => (string)$attrs->endtime,
            ];
        } else {
            return [];
        }
    }
}
