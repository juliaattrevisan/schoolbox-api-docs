<?php

/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use Schoolbox\User;

class UserTest extends TestCase
{
    protected $object;

    public function setUp(): void
    {
        parent::setUp();

        // Overriding functions which uses config
        $this->object = new class extends User
        {
            public const NAME_FORMAT  = '{title} {firstname} {lastname}';
            public const EMAIL_DOMAIN = 'test.schoolbox.com.au';

            public function formatName($format = null)
            {
                return parent::formatName(self::NAME_FORMAT);
            }

            public function getEmail()
            {
                if ($this->getLocalEmail() == true) {
                    return $this->getUsername() . '@' . self::EMAIL_DOMAIN;
                }
                return $this->getAltEmail();
            }
        };
    }

    public function testLocalEmailAddress()
    {
        $this->object->setLocalEmail(true);
        $this->object->setUsername('testuser');
        $expected = $this->object->getUsername() . '@' . $this->object::EMAIL_DOMAIN;
        $this->assertEquals($expected, $this->object->getEmail());
    }

    public function testAltEmailAddress()
    {
        $email = 'testuser@gmail.com';
        $this->object->setLocalEmail(false);
        $this->object->setUsername('testuser');
        $this->object->setAltEmail($email);
        $this->assertEquals($email, $this->object->getEmail());
    }
}
