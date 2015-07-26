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
            return [
                'RecordType'          => 'Week',
                'TimetableCampusName' => "$result->ShortName",
                'TimetableDays'       => $this->parseDays($result->Days[0]),
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
     * @return array
     */
    protected function parseDays($days)
    {
        $results = [];
        if ($days instanceof SimpleXMLElement) {
            foreach ($days->children() as $day) {
                $results[] = [
                    'DayName'    => "$day->ShortName",
                    'DayNumber'  => (int)$day->Ordinal,
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
