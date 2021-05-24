<?php

/**
 * Translator from ASC Timetables XML export to Schoolbox CSV timetable
 * structure import.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class External_AscTimetables_Translator_Structure extends External_AscTimetables_Translator_Base
{
    /**
     * Translates the input XML file to a timetable structure.
     *
     * @return array[]
     */
    public function translateXmlToArray()
    {
        $reducer = new External_AscTimetables_Translator_Structure_Reduce();
        $result  = [
            'termStart' => null,
            'dayCount'  => 0,
            'termTitle' => 'Whole Year',
            'days'      => [],
            'periods'   => [],
        ];
        foreach (
            new External_AscTimetables_Translator_Structure_Map(
                new Alaress\Xml\Filter\Path(
                    $this->iterator,
                    ['//days/day', '//periods/period',]
                )
            ) as $row
        ) {
            $result = $reducer($result, $row);
        }

        $endResult = [];
        if ($result['termStart'] instanceof Alaress_Date_Format && !$result['termStart']->isNull()) {
            $termStart  = $result['termStart']->getDatabaseDate();
            $termFinish = clone $result['termStart'];
            $termFinish = $termFinish->addYears(1)->getDatabaseDate();
            foreach (\Schoolbox\CampusMapper::getInstance()->findAll() as $campus) {
                foreach ($result['periods'] as $period) {
                    foreach ($result['days'] as $day) {
                        $endResult[] = [
                            'Term Campus'           => $campus->getExtTimetableCode(),
                            'Term Title'            => $result['termTitle'],
                            'Term Start'            => $termStart,
                            'Term Finish'           => $termFinish,
                            'Term Start Day Number' => 1,
                            'Period Day'            => $day['day'],
                            'Period Title'          => $period['number'],
                            'Period Start'          => $period['start'],
                            'Period Finish'         => $period['finish'],
                        ];
                    }
                }
            }
        }
        return $endResult;
    }
}
