<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Offline_Class implements External_ClassInterface
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
    public function getTeacherName(string $classCode): array
    {
        // Noop
        return [];
    }

}
