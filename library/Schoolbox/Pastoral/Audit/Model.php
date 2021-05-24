<?php

/**
 * This maintains an audit trail of every record a staff member views, and all the searches that they perform (and the
 * results that these searches returned)
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Audit;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\User;

class Model
{
    use Id;
    use CreatedAtDate;

    /** @var User $user Schoolbox user whose actions we are tracking */
    private $user;

    /** @var null|Pastoral $pastoral */
    private $pastoral;

    /** @var null|User $student */
    private $student;

    /** @var null|integer $reportId */
    private $reportId;

    /** @var null|string $search */
    private $search;

    /** @var null|string $searchResult */
    private $searchResult;

    /**
     * @param User $student
     *
     * @return $this
     */
    public function setStudent(User $student = null)
    {
        $this->student = $student;

        return $this;
    }

    /**
     * @return null|User
     */
    public function getStudent()
    {
        return $this->student;
    }

    /**
     * @param null|Pastoral $pastoral
     *
     * @return $this
     */
    public function setPastoral(Pastoral $pastoral = null)
    {
        $this->pastoral = $pastoral;

        return $this;
    }

    /**
     * @return null|Pastoral
     */
    public function getPastoral()
    {
        return $this->pastoral;
    }

    /**
     * @param User $user
     *
     * @return self
     */
    public function setUser(User $user)
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
     * @param null|integer $reportId
     *
     * @return $this
     */
    public function setReportId($reportId = null)
    {
        $this->reportId = $reportId;

        return $this;
    }

    /**
     * @return null|integer
     */
    public function getReportId()
    {
        return $this->reportId;
    }

    /**
     * @param null|string $search
     *
     * @return $this
     */
    public function setSearch($search = null)
    {
        $this->search = $search;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * @param null|string $searchResult
     *
     * @return $this
     */
    public function setSearchResult($searchResult = null)
    {
        $this->searchResult = $searchResult;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getSearchResult()
    {
        return $this->searchResult;
    }
}
