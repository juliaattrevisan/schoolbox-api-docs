<?php
class LoginCest 
{
    public function _before(AcceptanceTester $i)
    {
        $i->amOnPage('/login');
    }

    public function loginSuccessfully(AcceptanceTester $i)
    {
        $i->fillField('username', 'alaress');
        $i->fillField('password', 'alaress');
        $i->click('Submit');
        $i->see("Good Morning, Alaress Admin");
    }
    
    public function loginWithInvalidPassword(AcceptanceTester $i)
    {
        $i->fillField('username', 'wrong');
        $i->fillField('password', 'wrong');
        $i->click('Submit');
        $i->see("The username or password you typed is incorrect, please try again.");

        // Test DB contents
        $i->dontSeeInDatabase('user', ['username' => 'wrong']);
    }
}
