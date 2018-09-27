<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Config;

use Schoolbox\Mapper\Traits\Model\Id;

class Config
{

    use Id;

    /** @var string */
    private $name;

    /** @var mixed */
    private $value;

    /** @var string */
    private $title;

    /** @var string */
    private $description;

    /** @var int */
    private $fieldId;

    /** @var null|string */
    private $options;

    /** @var null|int */
    private $typeId;

    /** @var bool */
    private $editable;

    /** @var bool */
    private $hidden;

    /** @var bool */
    private $required;

    /** @var string */
    private $module;


    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }


    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }


    /**
     * @param mixed $value
     * @return $this
     */
    public function setValue($value): self
    {
        $this->value = $value;
        return $this;
    }


    /**
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }


    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }


    /**
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }


    /**
     * @return int
     */
    public function getFieldId(): ?int
    {
        return $this->fieldId;
    }


    /**
     * @param int $fieldId
     * @return $this
     */
    public function setFieldId(int $fieldId): self
    {
        $this->fieldId = $fieldId;
        return $this;
    }


    /**
     * @return string
     */
    public function getOptions(): ?string
    {
        return $this->options;
    }


    /**
     * @param null|string $options
     * @return $this
     */
    public function setOptions(?string $options): self
    {
        $this->options = $options;
        return $this;
    }


    /**
     * @return int
     */
    public function getTypeId(): ?int
    {
        return $this->typeId;
    }


    /**
     * @param null|int $typeId
     * @return $this
     */
    public function setTypeId(?int $typeId): self
    {
        $this->typeId = $typeId;
        return $this;
    }


    /**
     * @return bool
     */
    public function getEditable(): ?bool
    {
        return $this->editable;
    }


    /**
     * @param bool $editable
     * @return $this
     */
    public function setEditable(bool $editable): self
    {
        $this->editable = $editable;
        return $this;
    }


    /**
     * @return bool
     */
    public function getHidden(): ?bool
    {
        return $this->hidden;
    }


    /**
     * @param bool $hidden
     * @return $this
     */
    public function setHidden(bool $hidden): self
    {
        $this->hidden = $hidden;
        return $this;
    }


    /**
     * @return bool
     */
    public function getRequired(): ?bool
    {
        return $this->required;
    }


    /**
     * @param bool $required
     * @return $this
     */
    public function setRequired(bool $required): self
    {
        $this->required = $required;
        return $this;
    }


    /**
     * @return string
     */
    public function getModule(): ?string
    {
        return $this->module;
    }


    /**
     * @param string $module
     * @return $this
     */
    public function setModule(string $module): self
    {
        $this->module = $module;
        return $this;
    }
}
