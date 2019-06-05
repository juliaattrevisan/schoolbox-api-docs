<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
    public function getTeachers(string $classCode): array
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
