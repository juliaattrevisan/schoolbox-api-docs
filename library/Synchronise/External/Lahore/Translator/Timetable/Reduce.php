<?php
/**
 * Reduce function - smooshes results from `Synchronise_External_Lahore_Translator_Timetable_Map`
 * into a single result.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_External_Lahore_Translator_Timetable_Reduce
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
                $carry['terms'][$item['id']] = $item;
                break;
            case 'card':
                $carry['cards'][] = $item;
                break;
            case 'lesson':
                $carry['lessons'][$item['id']] = $item;
                break;
            case 'subject':
                $carry['subjects'][$item['id']] = $item;
                break;
        }
        return $carry;
    }
}
