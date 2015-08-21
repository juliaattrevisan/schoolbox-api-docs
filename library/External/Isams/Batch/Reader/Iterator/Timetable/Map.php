<?php
/**
 * Maps results for `External_Isams_Batch_Reader_Timetable->parse()`.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Timetable_Map extends External_Isams_Batch_Reader_Iterator_BaseMap
{
    /**
     * @return array
     */
    public function current()
    {
        /** @var Alaress\Xml\State $current */
        $current = $this->getInnerIterator()->current();
        $result  = new SimpleXMLElement($current->getReader()->readOuterXml());
        $id      = (string)$result->attributes()->id;
        if ($current->matches('//TimetableManager/Structure/Week')) {
            // Assume that campus is first initials in name
            $campusCode = preg_replace('|^(\\S+).*|', '$1', "$result->Name");
            // Assume that every week has the same number of days
            // Offsets:
            // Week 1: offset by 0 days
            // Week 2: offset by $numberOfDays days
            // Week 3: offset by $numberOfDays * 2 days
            // etc.
            $days       = $result->Days[0];
            $weekNumber = (int)$result->Ordinal;
            $offset     = ($weekNumber - 1) * $days->children()->count();
            return [
                'RecordType'          => 'Week',
                'TimetableCampusName' => $campusCode,
                'TimetableDays'       => $this->parseDays($days, $offset),
            ];
        } else {
            return [
                'RecordType' => 'Unknown',
                'ID'         => $id,
            ];
        }
    }

    /**
     * Parses the day list into a list of day arrays.
     *
     * @param  SimpleXMLElement $days
     * @param  int              $offset Days to offset the day number by
     * @return array
     */
    protected function parseDays($days, $offset)
    {
        $results = [];
        if ($days instanceof SimpleXMLElement) {
            foreach ($days->children() as $day) {
                // Can't use day shortname anymore
                $dayNumber = $offset + (int)$day->Ordinal;
                $results[] = [
                    'DayName'    => "Day $dayNumber",
                    'DayNumber'  => $dayNumber,
                    'DayPeriods' => $this->parsePeriods($day->Periods[0]),
                ];
            }
        }
        return $results;
    }

    /**
     * Parses the period list into a list of period arrays.
     *
     * @param  SimpleXMLElement $periods
     * @return array
     */
    protected function parsePeriods($periods)
    {
        $results = [];
        if ($periods instanceof SimpleXMLElement) {
            foreach ($periods->children() as $period) {
                $results[] = [
                    'ID'           => (int)$period->attributes()->id,
                    'PeriodName'   => "$period->ShortName",
                    'PeriodNumber' => (int)$period->Ordinal,
                    'PeriodStart'  => (string)$period->StartTime,
                    'PeriodEnd'    => (string)$period->EndTime,
                ];
            }
        }
        return $results;
    }
}
