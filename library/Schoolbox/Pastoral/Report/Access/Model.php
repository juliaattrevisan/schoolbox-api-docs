<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Report\Access;

use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\User;

class Model
{
    public const WRITE = 2;
    public const READ = 1;

    private ?int $id = null;

    private int $pastoralReportId;

    private ?User $user = null;

    private ?PastoralGroup $group = null;

    private int $access;

    private PastoralReport $pastoralReport;

    /**
     * @return int|null
     */
    public function getId(): int|null
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getPastoralReportId(): int
    {
        return $this->pastoralReportId;
    }

    /**
     * @param int $pastoralReportId
     * @return static
     */
    public function setPastoralReportId(int $pastoralReportId): self
    {
        $this->pastoralReportId = $pastoralReportId;
        return $this;
    }

    /**
     * @return PastoralReport
     */
    public function getPastoralReport(): PastoralReport
    {
        return $this->pastoralReport;
    }

    /**
     * @param PastoralReport $pastoralReport
     * @return static
     */
    public function setPastoralReport(PastoralReport $pastoralReport): self
    {
        $this->pastoralReport = $pastoralReport;
        return $this;
    }

    /**
     * @return null|PastoralGroup
     */
    public function getGroup(): ?PastoralGroup
    {
        return $this->group;
    }

    /**
     * @param PastoralGroup|null $pastoralGroup
     * @return static
     */
    public function setGroup(?PastoralGroup $pastoralGroup): self
    {
        $this->group = $pastoralGroup;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param null|User $user
     * @return static
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return int
     */
    public function getAccess(): int
    {
        return $this->access;
    }

    /**
     * @param int $access
     * @return static
     */
    public function setAccess(int $access): self
    {
        $this->access = $access;
        return $this;
    }
}
