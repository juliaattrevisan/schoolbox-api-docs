<?php

/**
 * This maintains an audit trail of every record a staff member views, and all the searches that they perform
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

    /**
     * Schoolbox user whose actions we are tracking
     */
    private User $user;

    private ?Pastoral $pastoral = null;

    private ?User $student = null;

    private ?int $reportId = null;

    private ?string $search = null;


    /**
     * @param User|null $student
     *
     * @return $this
     */
    public function setStudent(?User $student = null): self
    {
        $this->student = $student;

        return $this;
    }

    /**
     * @return null|User
     */
    public function getStudent(): ?User
    {
        return $this->student;
    }

    /**
     * @param Pastoral|null $pastoral
     *
     * @return $this
     */
    public function setPastoral(?Pastoral $pastoral = null): self
    {
        $this->pastoral = $pastoral;

        return $this;
    }

    /**
     * @return null|Pastoral
     */
    public function getPastoral(): ?Pastoral
    {
        return $this->pastoral;
    }

    /**
     * @param User $user
     *
     * @return self
     */
    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param null|integer $reportId
     *
     * @return $this
     */
    public function setReportId($reportId = null): self
    {
        $this->reportId = $reportId;

        return $this;
    }

    /**
     * @return null|integer
     */
    public function getReportId(): ?int
    {
        return $this->reportId;
    }

    /**
     * @param null|string $search
     *
     * @return $this
     */
    public function setSearch($search = null): self
    {
        $this->search = $search;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getSearch(): ?string
    {
        return $this->search;
    }
}
