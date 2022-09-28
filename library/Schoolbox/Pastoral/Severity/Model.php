<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Severity;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Sequential;

class Model implements ISoftDeletable, Sequential
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\UpdatedBy;
    use ModelTrait\HasSequence;

    /** @var string|null */
    protected ?string $name = null;

    /** @var bool $dashboard */
    protected bool $dashboard = false;

    /**
     * Colour of severity. Keeping this string rather than Color object to fit in admin list controller
     * @var string|null
     */
    protected ?string $color = null;

    /**
     * @param string $name
     *
     * @return static
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getName(): string | null
    {
        return $this->name;
    }

    /**
     * @param bool $dashboard
     *
     * @return static
     */
    public function setDashboard(bool $dashboard): self
    {
        $this->dashboard = $dashboard;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDashboard(): bool
    {
        return $this->dashboard;
    }

    /**
     * @return string|null
     */
    public function getColor(): ?string
    {
        return $this->color;
    }

    /**
     * @param string|null $color
     * @return static
     */
    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }
}
