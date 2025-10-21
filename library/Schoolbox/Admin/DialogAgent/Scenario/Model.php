<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DialogAgent\Scenario;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * Type enum values
     *
     * System: built by us and imposed upon the fleet
     * Custom: built by users or is somehow outside the set of System scenarios.
     *
     */
    private const string TYPE_SYSTEM = 'system';
    private const string TYPE_CUSTOM = 'custom';


    /**
     * @var string|null
     */
    private ?string $title = null;


    /**
     * @var string|null
     */
    private ?string $description = null;


    /**
     * @var string|null
     */
    private ?string $promptTemplate = null;


    /**
     * @var string|null
     */
    private ?string $type = null;


    /**
     * A set of scenario fields gets put here
     *
     * @var \Schoolbox\GenericCollection|null
     */
    private ?\Schoolbox\GenericCollection $fields = null;


    /* Modest conveniences
     */

    /**
     * A shortcut to setting System, indicating that this scenario is built by us
     * and imposed upon the fleet
     *
     * @return $this
     */
    public function setSystem(): self
    {
        $this->setType(self::TYPE_SYSTEM);
        return $this;
    }


    /**
     * A shortcut to setting Custom, indicating that this scenario is built by users
     * or is somehow outside the set of System scenarios.
     *
     * @return $this
     */
    public function setCustom(): self
    {
        $this->setType(self::TYPE_CUSTOM);
        return $this;
    }


    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }


    /**
     * @param string|null $title
     * @return $this
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }


    /**
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getPromptTemplate(): ?string
    {
        return $this->promptTemplate;
    }


    /**
     * @param string|null $promptTemplate
     * @return $this
     */
    public function setPromptTemplate(?string $promptTemplate): self
    {
        $this->promptTemplate = $promptTemplate;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }


    /**
     * @param string|null $type
     * @return $this
     */
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }


    /**
     * @return \Schoolbox\GenericCollection|null
     */
    public function getFields(): ?\Schoolbox\GenericCollection
    {
        return $this->fields;
    }


    /**
     * @param \Schoolbox\GenericCollection|null $fields
     * @return $this
     */
    public function setFields(?\Schoolbox\GenericCollection $fields): self
    {
        $this->fields = $fields;
        return $this;
    }


    /**
     * Add a scenario field to this scenario
     *
     * @param Field\Model $field
     * @return $this
     * @throws \UnexpectedValueException
     */
    public function addField(Field\Model $field): self
    {
        $this->fields->add($field);
        return $this;
    }
}
