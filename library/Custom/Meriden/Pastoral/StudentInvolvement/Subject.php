<?php
/**
 * Representation of a subject within Meriden.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Schoolbox\User;

class Subject extends Involvement
{
    /**
     * External data (class name and description)
     *
     * @var array
     */
    protected $externalData;

    /**
     * The teachers for this subject
     *
     * @var array|User[]
     */
    protected $teachers;

    /**
     * @return array
     */
    public function getExternalData()
    {
        return $this->externalData;
    }

    /**
     * @param  array $externalData
     *
     * @return $this
     */
    public function setExternalData($externalData)
    {
        $this->externalData = $externalData;
        return $this;
    }

    /**
     * @return array|\Schoolbox\User[]
     */
    public function getTeachers()
    {
        return $this->teachers;
    }

    /**
     * @param  array|\Schoolbox\User[] $teachers
     *
     * @return $this
     */
    public function setTeachers($teachers)
    {
        $this->teachers = $teachers;
        return $this;
    }
}
