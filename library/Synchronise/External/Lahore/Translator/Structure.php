<?php
/**
 * Translator from Lahore's ASC Timetables XML export to Schoolbox CSV
 * timetable structure import format.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_External_Lahore_Translator_Structure extends Synchronise_External_Lahore_Translator_Base
{
    /**
     * Translates the input XML file to a timetable structure.
     *
     * @return array[]
     */
    public function translateXmlToArray()
    {
        $reducer = new Synchronise_External_Lahore_Translator_Structure_Reduce();
        $result  = [
            'termStart' => null,
            'dayCount'  => 0,
            'termTitle' => '',
            'periods'   => [],
        ];
        foreach (
            new Synchronise_External_Lahore_Translator_Structure_Map(
                new Alaress\Xml\Filter\Path(
                    $this->iterator,
                    ['//daysdefs/daysdef', '//periods/period', '//termsdefs/termsdef']
                )
            ) as $row
        ) {
            $result = $reducer($result, $row);
        }

        $endResult = [];
        if ($result['termStart'] instanceof Alaress_Date_Format) {
            $termStart  = $result['termStart']->getDatabaseDate();
            $termFinish = clone $result['termStart'];
            $termFinish = $termFinish->addYears(1)->getDatabaseDate();
            foreach (\Schoolbox\CampusMapper::getInstance()->findAll() as $campus) {
                /** @var \Schoolbox\Campus $campus */
                foreach ($result['periods'] as $period) {
                    foreach (range(1, $result['dayCount']) as $day) {
                        $endResult[] = [
                            'Term Campus'   => $campus->getExtTimetableCode(),
                            'Term Title'    => $result['termTitle'],
                            'Term Start'    => $termStart,
                            'Term Finish'   => $termFinish,
                            'Term Offset'   => 0,
                            'Period Day'    => $day,
                            'Period Title'  => $period['id'],
                            'Period Start'  => $period['start'],
                            'Period Finish' => $period['finish'],
                        ];
                    }
                }
            }
        }
        return $endResult;
    }
}
