<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

class External_Synergetic_Newic_User extends External_Synergetic_User
{
    /**
     * Synergetic getPortrait returns null for users without external id
     * Changing this behaviour for this school to return existing portrait
     *
     * @inheritdoc
     */
    public function getPortrait(\Schoolbox\User $user): ?\Schoolbox\Resource\Storage\Model
    {
        if (null === $user->getExternalId()) {
            return $user->getPortrait();
        }
        return parent::getPortrait($user);
    }
}
