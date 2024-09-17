<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Field;

use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model
{
    use ModelTrait\Id;


    /**
     * Note that our Mappers require the ability to set and get nulls
     * even though our schema prohibits this.
     */

    /**
     * Either student, parent or staff
     * The Guest roletype isn't really taken into consideration here
     *
     * @var string|null
     */
    private ?string $roleType = null;


    /**
     * The name of a field for the profile screen. Note that this refers to a
     * hybrid set of fields that combine:
     *     - some fields from our schoolbox.user table and thus \Schoolbox\User model
     *     - some fields from our External_UserInterface array struct
     *
     * @var string|null
     */
    private ?string $field = null;


    /**
     * How a field displays to the Superuser when they're configuring what
     * fields appear on each roletype's profile screen; don't confuse this
     * with the user-facing variant that's subjected to Terminology
     * @var string|null
     */
    private ?string $displayAs = null;


    /**
     * The order in which a field is placed among others in the same set
     * for a given role type
     *
     * @var int|null
     */
    private ?int $sequence = null;


    /**
     * Set to 1 if a field was chosen to be on a profile screen, regardless of
     * any of its configurations
     *
     * @var int|null
     */
    private ?int $enabled = null;


    /**
     * Optionally corresponds to localisation.term - this could be null which means
     * that there is no localisation for this field.
     *
     * @var string|null
     */
    private ?string $term = null;


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


    /**
     * @return string|null
     */
    public function getField(): ?string
    {
        return $this->field;
    }


    /**
     * @param string|null $field
     * @return $this
     */
    public function setField(?string $field): self
    {
        $this->field = $field;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getDisplayAs(): ?string
    {
        return $this->displayAs;
    }


    /**
     * @param string|null $displayAs
     * @return $this
     */
    public function setDisplayAs(?string $displayAs): self
    {
        $this->displayAs = $displayAs;
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


    /**
     * @return int|null
     */
    public function getEnabled(): ?int
    {
        return $this->enabled;
    }


    /**
     * @param int|null $enabled
     * @return $this
     */
    public function setEnabled(?int $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getTerm(): ?string
    {
        return $this->term;
    }


    /**
     * @param string|null $term
     * @return $this
     */
    public function setTerm(?string $term): self
    {
        $this->term = $term;
        return $this;
    }
}
