<?php
/**
 * Translator from unknown XML format to a CSV representation of timetable
 * structure.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_External_Lahore_Translator_Structure_Map extends IteratorIterator
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
            // Is this day 1? use this as the reset day of this timetable
            $days = (string)$attrs->days;
            if (substr($days, 0, 1) === '1') {
                // Days will be in format "(Mon|Tue|Wed|etc.)-(dom)"
                $name = (string)$attrs->name;
                if (preg_match('/^([a-z]{3})-(\\d{1,2})$/i', $name, $matches)) {
                    $monthDay = $matches[2];
                    // What's the date of $monthDay in the current month?
                    return [
                        'type'      => 'daysdef',
                        'id'        => $name,
                        'dayCount'  => strlen($days),
                        'startDate' => Alaress_Date_Format::now()->setTime(0, 0, 0)->setDay(intval($monthDay)),
                    ];
                }
            }
        } elseif ($current->matches('//periods/period')) {
            return [
                'type'         => 'period',
                'id'           => (string)$attrs->id,
                'periodTitle'  => (string)$attrs->name,
                'periodStart'  => (string)$attrs->starttime,
                'periodFinish' => (string)$attrs->endtime,
            ];
        }
        // Fall through all cases - return nothing
        return [];
    }
}
