<?php

/**
 * IB Qualification Model
 * @author    [Your Name]
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\IB\Qualification;

use JsonSerializable;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\Sequential;

/**
 * Class Model
 *
 * @package Schoolbox\IB\Qualification
 */
class Model implements ISoftDeletable, Sequential, JsonSerializable
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;
    use ModelTraits\UpdatedAtDate;
    use ModelTraits\DeletedAtDate;
    use ModelTraits\HasSequence;

    /**
     * System Purpose Constants
     */
    public const SYSTEM_PURPOSE_DIPLOMA = 'DIPLOMA';
    public const SYSTEM_PURPOSE_ANTICIPATED = 'ANTICIPATED';
    public const SYSTEM_PURPOSE_COURSE = 'COURSE';

    /**
     * Available system purposes
     */
    public const VALID_SYSTEM_PURPOSES = [
        self::SYSTEM_PURPOSE_DIPLOMA,
        self::SYSTEM_PURPOSE_ANTICIPATED,
        self::SYSTEM_PURPOSE_COURSE,
    ];

    /**
     * Qualification Name
     * @var string|null
     */
    private ?string $name = null;

    /**
     * System Purpose for this qualification
     * @var string|null
     */
    private ?string $systemPurpose = null;

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
    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSystemPurpose(): ?string
    {
        return $this->systemPurpose;
    }

    /**
     * @param string|null $systemPurpose
     * @return static
     */
    public function setSystemPurpose(?string $systemPurpose): static
    {
        $this->systemPurpose = $systemPurpose;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'systemPurpose' => $this->getSystemPurpose(),
            'sequence' => $this->getSequence(),
            'createdAt' => $this->getCreatedAt(),
            'updatedAt' => $this->getUpdatedAt(),
            'deletedAt' => $this->getDeletedAt(),
        ];
    }
}