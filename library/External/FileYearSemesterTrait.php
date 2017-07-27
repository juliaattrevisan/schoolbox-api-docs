<?php
/**
 * A FileYear/FileSemester trait for Synergetic external classes
 *
 * These are Synergetic specific constructs; as such when using the the
 * external XXXX class it will be necessary to test which external connector is
 * in use and instantiate External_Synergetic_XXXX directly instead of the
 * generic External_XXXX class.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

trait External_FileYearSemesterTrait
{
    /** @var int $fileYear */
    public $fileYear = null;

    /** @var int $fileSemester */
    public $fileSemester = null;

    /**
     * @return int
     */
    public function getFileYear()
    {
        return $this->fileYear;
    }

    /**
     * @param $fileYear
     * @return $this
     */
    public function setFileYear($fileYear)
    {
        $this->fileYear = $fileYear;
        return $this;
    }

    /**
     * @return int
     */
    public function getFileSemester()
    {
        return $this->fileSemester;
    }

    /**
     * @param $fileSemester
     * @return $this
     */
    public function setFileSemester($fileSemester)
    {
        $this->fileSemester = $fileSemester;
        return $this;
    }
}
