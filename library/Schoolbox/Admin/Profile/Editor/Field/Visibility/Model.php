<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Field\Visibility;

use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model
{
    use ModelTrait\Id;


    /**
     * @var \Schoolbox\Admin\Profile\Editor\Field\Model|null
     */
    private ?\Schoolbox\Admin\Profile\Editor\Field\Model $field;


    /**
     * @var string|null
     */
    private ?string $roleType = null;


    /**
     * @return \Schoolbox\Admin\Profile\Editor\Field\Model|null
     */
    public function getField(): ?\Schoolbox\Admin\Profile\Editor\Field\Model
    {
        return $this->field;
    }


    /**
     * @param \Schoolbox\Admin\Profile\Editor\Field\Model|null $field
     * @return $this
     */
    public function setField(?\Schoolbox\Admin\Profile\Editor\Field\Model $field): self
    {
        $this->field = $field;
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
