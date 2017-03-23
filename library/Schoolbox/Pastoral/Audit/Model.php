<?php
/**
 * This maintains an audit trail of every record a staff member views, and all the searches that they perform (and the
 * results that these searches returned)
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Audit;

use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\User;

class Model
{
    use Id;
    use CreatedAtDate;

    /** @var User $user */
    protected $user;

    /** @var Pastoral $pastoral */
    protected $pastoral;

    /** @var User $student */
    protected $student;

    /** @var integer $reportId */
    protected $reportId;

    /** @var string $search */
    protected $search;

    /** @var string $searchResult */
    protected $searchResult;

    /**
     * @param User $student
     * @return self
     */
    public function setStudent($student)
    {
        $this->student = $student;
        return $this;
    }

    /**
     * @return User
     */
    public function getStudent()
    {
        return $this->student;
    }

    /**
     * @param Pastoral $pastoral
     * @return self
     */
    public function setPastoral($pastoral)
    {
        $this->pastoral = $pastoral;
        return $this;
    }

    /**
     * @return Pastoral
     */
    public function getPastoral()
    {
        return $this->pastoral;
    }

    /**
     * @param User $user
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param integer $reportId
     * @return self
     */
    public function setReportId($reportId)
    {
        $this->reportId = $reportId;
        return $this;
    }

    /**
     * @return integer
     */
    public function getReportId()
    {
        return $this->reportId;
    }    

    /**
     * @param string $search
     * @return self
     */
    public function setSearch($search)
    {
        $this->search = $search;
        return $this;
    }

    /**
     * @return string
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * @param string $searchResult
     * @return self
     */
    public function setSearchResult($searchResult)
    {
        $this->searchResult = $searchResult;
        return $this;
    }

    /**
     * @return string
     */
    public function getSearchResult()
    {
        return $this->searchResult;
    }
}