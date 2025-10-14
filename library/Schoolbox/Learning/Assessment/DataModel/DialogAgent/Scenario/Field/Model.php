<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Field;

use Schoolbox\ISoftDeletable;
use \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Model as ScenarioModel;
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
     * These help the frontend decide how to render a particular Field's options,
     * configuration and settings etc.
     */
    private const string TYPE_TEXTAREA = 'textarea';
    private const string TYPE_SWITCH = 'switch';
    private const string TYPE_DROPDOWN = 'dropdown';


    /**
     * @var ScenarioModel|null
     */
    private ?ScenarioModel $scenario = null;


    /**
     * @var string|null
     */
    private ?string $title = null;


    /**
     * @var string|null
     */
    private ?string $slug = null;


    /**
     * @var string|null
     */
    private ?string $type = null;


    /**
     * @var string|null
     */
    private ?string $meta = null;


    /**
     * @var string|null
     */
    private ?string $options = null;


    /**
     * @var int|null
     */
    private ?int $sequence = null;


    /* Modest conveniences
     */

    /**
     * This field is a Textarea for the staff to configure.
     *
     * @return $this
     */
    public function setTypeTextarea(): self
    {
        $this->setType(self::TYPE_TEXTAREA);
        return $this;
    }


    /**
     * This field is a Switch/Toggle thing for the staff to configure.
     *
     * @return $this
     */
    public function setTypeSwitch(): self
    {
        $this->setType(self::TYPE_SWITCH);
        return $this;
    }


    /**
     * This field is a Dropdown for the staff to configure.
     *
     * @return $this
     */
    public function setTypeDropdown(): self
    {
        $this->setType(self::TYPE_DROPDOWN);
        return $this;
    }


    /**
     * @return ScenarioModel|null
     */
    public function getScenario(): ?ScenarioModel
    {
        return $this->scenario;
    }


    /**
     * @param ScenarioModel|null $scenario
     * @return $this
     */
    public function setScenario(?ScenarioModel $scenario): self
    {
        $this->scenario = $scenario;
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
    public function getSlug(): ?string
    {
        return $this->slug;
    }


    /**
     * @param string|null $slug
     * @return $this
     */
    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
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
     * @return string|null
     */
    public function getMeta(): ?string
    {
        return $this->meta;
    }


    /**
     * @param string|null $meta
     * @return $this
     */
    public function setMeta(?string $meta): self
    {
        $this->meta = $meta;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getOptions(): ?string
    {
        return $this->options;
    }


    /**
     * @param string|null $options
     * @return $this
     */
    public function setOptions(?string $options): self
    {
        $this->options = $options;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getSequence(): ?int
    {
        return $this->sequence;
    }


    /**
     * @param int|null $sequence
     * @return $this
     */
    public function setSequence(?int $sequence): self
    {
        $this->sequence = $sequence;
        return $this;
    }
}
