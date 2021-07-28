<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\SubType;

use Alaress\Color\Color;
use JsonSerializable;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Pastoral\Type as PastoralType;
use Schoolbox\Sequential;

class Model implements ISoftDeletable, JsonSerializable, Sequential
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\UpdatedBy;
    use ModelTrait\HasSequence;

    /**
     * @var PastoralType\Model
     */
    private $pastoralType;

    /**
     * @var string
     */
    private $name;


    /**
     * @var string|null
     */
    private ?string $template = null;


    /**
     * This type's color.
     */
    private ?Color $color;


    /**
     * This type's icon.
     */
    private ?string $icon;


    /**
     * Optional. Null if not linked to SIS
     *
     * @var null|string
     */
    private $externalCode;


    /**
     * Visibility locked
     * Defaults to false, when created from the admin page.
     */
    private bool $visibilityLocked = false;

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param PastoralType\Model $pastoralType
     *
     * @return $this
     */
    public function setPastoralType($pastoralType)
    {
        $this->pastoralType = $pastoralType;

        return $this;
    }

    /**
     * @return PastoralType\Model
     */
    public function getPastoralType()
    {
        return $this->pastoralType;
    }

    /**
     * @param null|string $externalCode
     *
     * @return $this
     */
    public function setExternalCode($externalCode = null)
    {
        $this->externalCode = $externalCode;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getExternalCode()
    {
        return $this->externalCode;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'id'   => $this->getId(),
            'name' => $this->getName(),
        ];
    }

    /**
     * @return string|null
     */
    public function getTemplate(): ?string
    {
        return $this->template;
    }


    /**
     * @param string|null $template
     * @return Model
     */
    public function setTemplate(?string $template): self
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return Color|null
     */
    public function getColor(): ?Color
    {
        return $this->color;
    }

    /**
     * @param Color|null $color
     */
    public function setColor(?Color $color): void
    {
        $this->color = $color;
    }

    /**
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @param string|null $icon
     */
    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    /**
     * @return bool
     */
    public function getVisibilityLocked(): bool
    {
        return $this->visibilityLocked;
    }

    /**
     * @param bool $visibilityLocked
     * @return static
     */
    public function setVisibilityLocked(bool $visibilityLocked): self
    {
        $this->visibilityLocked = $visibilityLocked;
        return $this;
    }
}
