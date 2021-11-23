<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Action;

use JsonSerializable;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\Sequential;

/**
 * Class Model
 *
 * @package Schoolbox\Pastoral\Action
 */
class Model implements ISoftDeletable, Sequential, JsonSerializable
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;
    use ModelTraits\UpdatedAtDate;
    use ModelTraits\DeletedAtDate;
    use ModelTraits\HasSequence;

    /**
     * Action Name
     * @var string|null
     */
    private ?string $name = null;

    /**
     * External code for this action
     * @var string|null
     */
    private ?string $externalCode = null;

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     * @return static
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getExternalCode(): ?string
    {
        return $this->externalCode;
    }

    /**
     * @param string|null $externalCode
     * @return static
     */
    public function setExternalCode(?string $externalCode): self
    {
        $this->externalCode = $externalCode;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
        ];
    }
}
