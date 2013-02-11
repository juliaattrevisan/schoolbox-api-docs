<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Test;
use Schoolbox;

class UserTest extends PHPUnit_Framework_TestCase {

    public function testLocalEmailAddress() {
        $user = new User;

        $user->setLocalEmail(true);
        $user->setUsername('testuser');

        $expected = $user->getUsername().'@'.Cfg::get('site_domain');

        $this->assertEquals($user->getEmail(), $expected);
    }

    public function testAltEmailAddress() {
        $user = new User;

        $email = 'testuser@gmail.com';

        $user->setLocalEmail(false);
        $user->setUsername('testuser');
        $user->setAltEmail($email);

        $this->assertEquals($user->getEmail(), $email);
    }
}