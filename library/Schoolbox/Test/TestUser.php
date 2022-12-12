<?php

/**
 * Test user class not relying on Config
 *
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use Schoolbox\User;

class TestUser extends User
{
    public const NAME_FORMAT  = '{title} {firstname} {lastname}';
    public const EMAIL_DOMAIN = 'test.schoolbox.com.au';

    /**
     * @inheritdoc
     */
    public function formatName($format = null)
    {
        return parent::formatName(self::NAME_FORMAT);
    }

    /**
     * @inheritdoc
     */
    public function getEmail()
    {
        if ($this->getLocalEmail() == true) {
            return $this->getUsername() . '@' . self::EMAIL_DOMAIN;
        }
        return $this->getAltEmail();
    }
}
