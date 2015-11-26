<?php
/**
 * Reduce function - smooshes results from `External_AscTimetables_Translator_Structure_Map`
 * into a single result.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_AscTimetables_Translator_Structure_Reduce
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
                $carry['termStart'] = $item['startDate'];
                $carry['dayCount']  = $item['dayCount'];
                break;
            case 'period':
                $carry['periods'][] = [
                    'number' => $item['id'],
                    'title'  => $item['periodTitle'],
                    'start'  => $item['periodStart'],
                    'finish' => $item['periodFinish'],
                ];
                break;
        }
        return $carry;
    }
}
