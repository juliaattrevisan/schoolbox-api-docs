<?php
/**
 * Translator from unknown XML format to a CSV representation of timetable
 * structure.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_External_Lgspk_Translator_Structure_Reduce
{
    /**
     * @param array $carry
     * @param array $item
     *
     * @return array
     */
    public function __invoke($carry, $item)
    {
        if (!isset($item['type'])) {
            return $carry;
        }
        switch ($item['type']) {
            case 'termsdef':
                $carry['termTitle'] = $item['termTitle'];
                break;
            case 'daysdef':
                $carry['dayCount'] = $item['dayCount'];
                break;
            case 'period':
                $carry['periods'][] = [
                    'title'  => $item['periodTitle'],
                    'start'  => $item['periodStart'],
                    'finish' => $item['periodFinish'],
                ];
                break;
        }
        return $carry;
    }
}
