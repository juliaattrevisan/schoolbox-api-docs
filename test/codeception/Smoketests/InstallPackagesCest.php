<?php

namespace Tests\Smoketests;

use Tests\Support\IntegrationTester;
use Tests\Support\Payload;

class InstallPackagesCest
{
    /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin();
        $i->stopFollowingRedirects();
    }


    /** Install each package
     * check if response code is 200
     *
     * @param IntegrationTester $i
     */
    public function installPackages(IntegrationTester $i): void
    {
        #GIVEN
        // Extra Curricular Badges package
        $id           = 5;
        $packagePayload = new Payload\Admin\Modules\Packages($id);
        #WHEN
        $i->amGoingTo('Send request to install package "Extra Curricular Badges package"');
        $i->sendRequest($packagePayload);
        #THEN
        $i->seeResponseCodeIs(200);

        #GIVEN
        // Community Badges package
        $id           = 6;
        $packagePayload = new Payload\Admin\Modules\Packages($id);
        #WHEN
        $i->amGoingTo('Send request to install package "Community Badges package"');
        $i->sendRequest($packagePayload);
        #THEN
        $i->seeResponseCodeIs(200);

        #GIVEN
        // Australian Curriculum V9 package
        $id           = 14;
        $packagePayload = new Payload\Admin\Modules\Packages($id);
        #WHEN
        $i->amGoingTo('Send request to install package "Australian Curriculum V9 package"');
        $i->sendRequest($packagePayload);
        #THEN
        $i->seeResponseCodeIs(200);

        #GIVEN
        // UK National Curriculum package
        $id           = 32;
        $packagePayload = new Payload\Admin\Modules\Packages($id);
        #WHEN
        $i->amGoingTo('Send request to install package "UK National Curriculum"');
        $i->sendRequest($packagePayload);
        #THEN
        $i->seeResponseCodeIs(200);
    }
}
