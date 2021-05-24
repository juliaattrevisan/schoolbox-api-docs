<?php

/**
 * Reduce function - smooshes results from `External_AscTimetables_Translator_Structure_Map`
 * into a single result.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
            case 'day':
                $carry['dayCount']++;
                if ($item['day'] == 1) {
                    $carry['termStart'] = $item['startDate'];
                }
                $carry['days'][$item['day']] = $item;
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
