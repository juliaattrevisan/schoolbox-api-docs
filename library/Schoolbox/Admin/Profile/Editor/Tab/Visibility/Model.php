<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Tab\Visibility;

use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model
{
    use ModelTrait\Id;


    /**
     * @var \Schoolbox\Admin\Profile\Editor\Tab\Model|null
     */
    private ?\Schoolbox\Admin\Profile\Editor\Tab\Model $tab;


    /**
     * @var string|null
     */
    private ?string $roleType = null;


    /**
     * @return \Schoolbox\Admin\Profile\Editor\Tab\Model|null
     */
    public function getTab(): ?\Schoolbox\Admin\Profile\Editor\Tab\Model
    {
        return $this->tab;
    }


    /**
     * @param \Schoolbox\Admin\Profile\Editor\Tab\Model|null $tab
     * @return $this
     */
    public function setTab(?\Schoolbox\Admin\Profile\Editor\Tab\Model $tab): self
    {
        $this->tab = $tab;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getRoleType(): ?string
    {
        return $this->roleType;
    }


    /**
     * @param string|null $roleType
     * @return $this
     */
    public function setRoleType(?string $roleType): self
    {
        $this->roleType = $roleType;
        return $this;
    }
}
