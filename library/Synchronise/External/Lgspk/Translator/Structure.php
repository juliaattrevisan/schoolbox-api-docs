<?php
/**
 * Translator from unknown XML format to a CSV representation of timetable
 * structure.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_External_Lgspk_Translator_Structure extends Synchronise_External_Lgspk_Translator_Base
{
    /**
     * Translates the input XML file to a timetable structure.
     *
     * @return Iterator
     */
    public function translateXmlToArray()
    {
        $reducer = new Synchronise_External_Lgspk_Translator_Structure_Reduce();
        $result  = [
            'dayCount'  => 0,
            'termTitle' => '',
            'periods'   => [],
        ];
        foreach (
            new Synchronise_External_Lgspk_Translator_Structure_Map(
                new Alaress\Xml\Filter\Path(
                    $this->iterator,
                    ['//daysdefs/daysdef', '//periods/period', '//termsdefs/termsdef']
                )
            ) as $row
        ) {
            $result = $reducer($result, $row);
        }

        $termStart  = '';
        $termFinish = '';
        foreach (\Schoolbox\CampusMapper::getInstance()->findAll() as $campus) {
            /** @var \Schoolbox\Campus $campus */
            foreach ($result['periods'] as $period) {
                foreach (range(1, $result['dayCount']) as $day) {
                    $fields = [
                        'Term Campus'   => $campus->getExtTimetableCode(),
                        'Term Title'    => $result['termTitle'],
                        'Term Start'    => 'The date on which the term starts',
                        'Term Finish'   => 'The date on which the term finishes',
                        'Term Offset'   => 'The day number that "Term Start" is',
                        'Period Day'    => $day,
                        'Period Title'  => 'Day '.$day,
                        'Period Start'  => 'The time at which this period starts',
                        'Period Finish' => 'The time at which this period finishes',
                    ];
                }
            }
        }

//        // Use External\Importer\TimetableStructure to create fields?
//        $fields = [
//            'Term Campus'   => 'The campus to create a term for (must correspond to a campus code in Schoolbox)',
//            'Term Title'    => 'The name of the term',
//            'Term Start'    => 'The date on which the term starts',
//            'Term Finish'   => 'The date on which the term finishes',
//            'Term Offset'   => 'The day number that "Term Start" is',
//            'Period Day'    => 'The day number that this period is on',
//            'Period Title'  => 'The name of this period',
//            'Period Start'  => 'The time at which this period starts',
//            'Period Finish' => 'The time at which this period finishes',
//        ];
    }
}
