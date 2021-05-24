<?php

/**
 * Reduce function - smooshes results from `External_AscTimetables_Translator_Enrolment_Map`
 * into a single result.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class External_AscTimetables_Translator_Enrolment_Reduce
{
    /**
     * @param array $carry
     * @param array $item
     *
     * @return array
     */
    public function __invoke($carry, $item)
    {
        // Subjects and a list of students within
        if (!isset($item['type'])) {
            return $carry;
        }
        $subject = $item['subject'];
        if (!isset($carry[$subject])) {
            $carry[$subject] = [
                'code'     => '',
                'students' => [],
            ];
        }
        switch ($item['type']) {
            case 'subject':
                $carry[$subject]['code'] = $item['code'];
                break;
            case 'student':
                $carry[$subject]['students'][] = $item['student'];
                break;
        }
        return $carry;
    }
}
