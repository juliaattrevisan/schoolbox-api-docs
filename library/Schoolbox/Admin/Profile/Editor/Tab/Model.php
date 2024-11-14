<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Tab;

use Schoolbox\GenericCollection;
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
     * These tabs were mined from the hardcoded student.twig:
     * activity feed, goals, achievements, classes, contacts
     *
     * This column helps you identify which is which, even when schools have
     * filled their configurable columns with junk
     *
     * @var string|null
     */
    private ?string $tab = null;


    /**
     * How a tab displays to the Superuser when they're configuring what
     * tabs appear on each roletype's profile screen; don't confuse this
     * with the user-facing variant that's subjected to Terminology. There
     * aren't many terminology usages in profile tabs atm but you can expect
     * that to change over time
     *
     * @var string|null
     */
    private ?string $displayAs = null;


    /**
     * The order in which a tab is placed among others in the same set
     * for a given role type
     *
     * @var int|null
     */
    private ?int $sequence = null;


    /**
     * A list of Availability settings for this tab
     *
     * @var GenericCollection|null
     */
    private ?GenericCollection $availabilityCampus = null;


    /**
     * A list of Visibility settings for this tab
     *
     * @var GenericCollection|null
     */
    private ?GenericCollection $visibility = null;


    /**
     * @return GenericCollection|null
     */
    public function getAvailabilityCampus(): ?GenericCollection
    {
        return $this->availabilityCampus;
    }


    /**
     * @param GenericCollection|null $availabilityCampus
     * @return $this
     */
    public function setAvailabilityCampus(?GenericCollection $availabilityCampus): self
    {
        $this->availabilityCampus = $availabilityCampus;
        return $this;
    }


    /**
     * @return GenericCollection|null
     */
    public function getVisibility(): ?GenericCollection
    {
        return $this->visibility;
    }


    /**
     * @param GenericCollection|null $visibility
     * @return $this
     */
    public function setVisibility(?GenericCollection $visibility): self
    {
        $this->visibility = $visibility;
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


    /**
     * @return string|null
     */
    public function getTab(): ?string
    {
        return $this->tab;
    }


    /**
     * @param string|null $tab
     * @return $this
     */
    public function setTab(?string $tab): self
    {
        $this->tab = $tab;
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
}
