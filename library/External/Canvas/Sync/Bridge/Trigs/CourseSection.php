<?php

//
///**
// * @copyright Copyright 2023, Schoolbox Pty Ltd
// * @link      https://schoolbox.com.au/
// */
//
//declare(strict_types=1);
//
//namespace External\Canvas\Sync\Bridge\Trigs;
//
//class CourseSection extends \External\Canvas\Sync\Bridge\CourseSection
//{
//    /**
//     * TRIGS has SIS ids within canvas with _2022, this means when stripping the year from the code
//     * we are still left with _. This is to remove the _ allowing the codes to match.
//     * @param string|null $termSisSourceId
//     * @inheritDoc
//     */
//    public function parseSubjectCodeFromSisSourceId(string $sisSourceId, string $year, ?string $termSisSourceId): ?string
//    {
//        if (empty($year) && empty($termSisSourceId)) {
//            return $sisSourceId;
//        }
//        if (!empty($termSisSourceId)) {
//            return str_replace("_$termSisSourceId", '', $sisSourceId);
//        }
//        return str_replace("_$year", '', $sisSourceId);
//    }
//}
