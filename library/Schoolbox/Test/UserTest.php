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

    /**
     * @var User
     */
    protected $object;

    protected $config_vars = [
        ['site_domain', 'localhost'],
        ['mail_address', 'localhost'],
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->object = new User();
        $this->object->setConfigLoader($this->mock_config);
    }

    public function testLocalEmailAddress()
    {
        $this->object->setLocalEmail(true);
        $this->object->setUsername('testuser');
        $expected = $this->object->getUsername() . '@' . $this->mock_config->get('site_domain');
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
