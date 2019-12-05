<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport;

/**
 * Class NormaliseTeachers
 *
 * Arrange teacher information into a set of (key, value) tuples
 * from a denormalised query result
 *
 * @package Custom\Diocesan\AchievementReport
 */
class NormaliseTeachers
{

    /**
     * Numerical indices that contain teacher data
     * Denormalised records suggest three columns which might contain teachers
     * Some might be null; we can't really assume which ones will contain data
     */
    private const TEACHER_INDEXES = [1,2,3];


    /**
     * Denormalised data is in the format:
     *
     * ClassTeacher1
     * ClassTeacher1Email
     * ClassTeacher2
     * ClassTeacher2Email
     * ClassTeacher3
     * ClassTeacher3Email
     *
     * Any combination of those fields could contain data, or be null.
     * We have to inspect each to see where the teacher(s) are actually stored
     *
     * @param array $current
     * @return array
     */
    public function normalise(array $current): array
    {
        $teachers = [];

        foreach(self::TEACHER_INDEXES as $currentTeacherIndex) {
            $key = "ClassTeacher{$currentTeacherIndex}";
            $emailKey = "ClassTeacher{$currentTeacherIndex}Email";

            /* Teacher's name is in this field
             */
            $currentTeacher = $current[$key];

            /* Teacher's email is in this field
             */
            $currentTeacherEmail = $current[$emailKey];

            /* We only care about teachers with names
             */
            if ('' !== trim($currentTeacher)) {
                $teachers[$currentTeacher] = $currentTeacherEmail;
            }
        }

        return $teachers;
    }
}
