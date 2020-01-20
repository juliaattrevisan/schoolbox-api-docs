<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
*/

use External\IOfflineConnector;

class External_Offline_Class implements External_ClassInterface, IOfflineConnector
{

    /**
     * @inheritdoc
     */
    public function getStudents(string $classCode): array
    {
        // Noop
        return [];
    }


    /**
     * @inheritdoc
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
        // Noop
        return [];
    }

    /**
     * @return array
     */
    public function findClassTypes(): array
    {
        return [];
    }

    /**
     * @param string $filetype
     * @param array  $timetableCode
     *
     * @return array
     */
    public function findClassesByType(string $filetype, array $timetableCode): array
    {
        return [];
    }
}
