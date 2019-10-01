<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class External_Synergetic_Ths_Pastoral extends External_Synergetic_Pastoral
{

    /** @inheritDoc */
    protected function getStudentViewName(): string
    {
        return 'uvStudents';
    }


    /** @inheritDoc */
    protected function getExtraFileSemJoinClause(): string
    {
        return "";
    }
}
