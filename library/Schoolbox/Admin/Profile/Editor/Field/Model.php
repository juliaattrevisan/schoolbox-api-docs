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
     * @return string|null
     */
    public function getRoleType(): ?string
    {
        return $this->roleType;
    }


    /**
     * @param string|null $roleType
     * @return void
     */
    public function setRoleType(?string $roleType): void
    {
        $this->roleType = $roleType;
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
     * @return void
     */
    public function setField(?string $field): void
    {
        $this->field = $field;
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
     * @return void
     */
    public function setDisplayAs(?string $displayAs): void
    {
        $this->displayAs = $displayAs;
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
     * @return void
     */
    public function setSequence(?int $sequence): void
    {
        $this->sequence = $sequence;
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
     * @return void
     */
    public function setEnabled(?int $enabled): void
    {
        $this->enabled = $enabled;
    }
}
