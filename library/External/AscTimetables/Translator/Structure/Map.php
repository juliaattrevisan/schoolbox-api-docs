<?php

/**
 * Map function - converts an XML node from ASC Timetables XML export to a more
 * understandable intermediary format.
 *
 * Mapped values are used in the creation of the timetable structure.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class External_AscTimetables_Translator_Structure_Map extends IteratorIterator
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
        if ($current->matches('//days/day')) {
            // Days will be in format "(Mon|Tue|Wed|etc.)-(dom)"
            $name = (string)$attrs->name;
            if (preg_match('/^([a-z]{3})-(\\d{1,2})$/i', $name, $matches)) {
                $monthDay = $matches[2];
                // What's the date of $monthDay in the current month?
                return [
                    'type'      => 'day',
                    'id'        => $name,
                    'day'       => (int)$attrs->day + 1,
                    'startDate' => Alaress_Date_Format::now()->setTime(0, 0, 0)->setDay(intval($monthDay)),
                ];
            }
        } elseif ($current->matches('//periods/period')) {
            return [
                'type'         => 'period',
                'id'           => (string)$attrs->period,
                'periodTitle'  => (string)$attrs->name,
                'periodStart'  => (string)$attrs->starttime,
                'periodFinish' => (string)$attrs->endtime,
            ];
        }
        // Fall through all cases - return nothing
        return [];
    }
}
