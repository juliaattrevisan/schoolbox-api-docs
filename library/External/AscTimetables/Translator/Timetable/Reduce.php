<?php

/**
 * Reduce function - smooshes results from `External_AscTimetables_Translator_Timetable_Map`
 * into a single result.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class External_AscTimetables_Translator_Timetable_Reduce
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
        $id   = !empty($item['id']) ? $item['id'] : '';
        $type = $item['type'];
        unset($item['id'], $item['type']);
        switch ($type) {
            case 'card':
                $carry['cards'][] = $item;
                break;
            case 'lesson':
                $carry['lessons'][$id] = $item;
                break;
            case 'subject':
                $carry['subjects'][$id] = $item;
                break;
            case 'classroom':
                $carry['classrooms'][$id] = $item;
                break;
            case 'class':
                $carry['classes'][$id] = $item;
                break;
            case 'teacher':
                $carry['teachers'][$id] = $item;
                break;
        }
        return $carry;
    }
}
