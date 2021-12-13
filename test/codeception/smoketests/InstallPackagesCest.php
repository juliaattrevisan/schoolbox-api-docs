<?php

namespace SmokeTests;

use IntegrationTester;
use Payload;

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
        // Australian Curriculum - General Capabilities package
        $id           = 1;
        $packagePayload = new Payload\Admin\Modules\Packages($id);
        #WHEN
        $i->amGoingTo('Send request to install package "Australian Curriculum - General Capabilities"');
        $i->sendRequest($packagePayload);
        #THEN
        $i->seeResponseCodeIs(200);

        #GIVEN
        // Australian Curriculum - Cross-curriculum Priorities package
        $id           = 2;
        $packagePayload = new Payload\Admin\Modules\Packages($id);
        #WHEN
        $i->amGoingTo('Send request to install package "Australian Curriculum - Cross-curriculum Priorities package"');
        $i->sendRequest($packagePayload);
        #THEN
        $i->seeResponseCodeIs(200);

        #GIVEN
        // General Capability Badges package
        $id           = 3;
        $packagePayload = new Payload\Admin\Modules\Packages($id);
        #WHEN
        $i->amGoingTo('Send request to install package "General Capability Badges package"');
        $i->sendRequest($packagePayload);
        #THEN
        $i->seeResponseCodeIs(200);

        #GIVEN
        // Personal Development Badges package
        $id           = 4;
        $packagePayload = new Payload\Admin\Modules\Packages($id);
        #WHEN
        $i->amGoingTo('Send request to install package "Personal Development Badges package"');
        $i->sendRequest($packagePayload);
        #THEN
        $i->seeResponseCodeIs(200);

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
        // Australian Early Years Learning Framework package
        $id           = 7;
        $packagePayload = new Payload\Admin\Modules\Packages($id);
        #WHEN
        $i->amGoingTo('Send request to install package "Australian Early Years Learning Framework package"');
        $i->sendRequest($packagePayload);
        #THEN
        $i->seeResponseCodeIs(200);
    }
}
