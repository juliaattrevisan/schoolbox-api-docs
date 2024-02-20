<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;
use Tests\Support\Page\CourseModify;
use Exception;

/**
 *
 * modify the course Faculty/Subject folder and ensure the location is saved:
 * ensure user is able to select homepage as course location and not able to select courses or units as course location,
 * ensure it is still possible to save it for course with legacy invalid(unit) location
 *
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance CourseLocationPickerCest
 */
class CourseLocationPickerCest
{
    /**
     * Go to the course modify page before every test
     *
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function _before(AcceptanceTester $i): void
    {
        $i->amGoingTo('Set the location of the ELC course to Competing ideologies unit');
        $i->updateInDatabase('folder', ['parent' => 266], ['id' => 65]);
        $i->loginAsAdmin();
        $i->amGoingTo('navigate to the ELC course modify page');
        $i->amOnPage(CourseModify::getUrl(4));
        $i->amGoingTo('click the Faculty filed to be able to select a faculty / subject folder via modal');
        $i->click(CourseModify::SELECTOR_LOCATION);
        $i->waitForElement(CourseModify::SELECTOR_USE_FOLDER); // Need to wait for modal fade-in to finish
    }


    /**
     * Clean up after every test
     *
     * @param AcceptanceTester $i
     */
    public function _after(AcceptanceTester $i): void
    {
        $i->amGoingTo('Set the location of the ELC course to Competing ideologies unit');
        $i->updateInDatabase('folder', ['parent' => 266], ['id' => 65]);
    }


    /**
     * Test that user can select current (invalid) location
     *
     * @param AcceptanceTester $i
     */
    public function retainCurrentInvalid(AcceptanceTester $i): void
    {
        $i->amGoingTo('check that Use This Folder button is initially disabled');
        $i->seeElement(CourseModify::SELECTOR_USE_FOLDER . '[disabled]');

        $i->amGoingTo('click current location Competing ideologies unit');
        $i->click('[id="266"]');

        $i->amGoingTo('check that Use This Folder button is now enabled');
        $i->seeElement(CourseModify::SELECTOR_USE_FOLDER . ':not([disabled])');

        $i->amGoingTo('click Use This Folder button');
        $i->click(CourseModify::SELECTOR_USE_FOLDER);

        $i->amGoingTo('check if the hidden form field retains this value');
        $val = $i->executeJS("return $('#parent').val()");
        $i->assertEquals(266, $val);

        $i->amGoingTo('click the Save Course button');
        $i->click(CourseModify::SELECTOR_SAVE_COURSE);

        $i->amGoingTo('check the course has the proper faculty / subject folder');
        $i->seeInDatabase('course_specification', ['id' => 4, 'folder_id' => 65]);
        $i->seeInDatabase('folder', ['id' => 65, 'parent' => 266]);
    }


    /**
     * Tests user:
     * * can see other courses
     * * cannot select courses as a course location
     * * cannot see units inside a course node
     * * can select a homepage as a course location
     * * can persist a homepage as a course location
     *
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function locationSelection(AcceptanceTester $i): void
    {
        $i->amGoingTo('open the French homepage node');
        $i->scrollAndClick('[id="10"]');

        $i->amGoingTo('check the French homepage is selected');
        $i->assertEquals(true, $i->executeJS("return $('#10 > .jstree-wholerow.jstree-wholerow-clicked').length"));

        $i->amGoingTo('user can see Year 9 French course node');
        $i->see('Year 9 French');

        $i->amGoingTo('click Year 9 French course node');
        $i->click('[id="16"]');

        $i->amGoingTo('check Year 9 French is not selected');
        $i->assertEquals(false, $i->executeJS("return $('#16 > .jstree-wholerow.jstree-wholerow-clicked').length"));

        $i->amGoingTo('check the user cannot see units inside Year 9 French course');
        $i->assertEquals(true, $i->executeJS("return $('#16.jstree-leaf').length"));

        $i->amGoingTo('check the French homepage still is selected');
        $i->assertEquals(true, $i->executeJS("return $('#10 > .jstree-wholerow.jstree-wholerow-clicked').length"));

        $i->amGoingTo('click English homepage');
        $i->scrollAndClick('[id="85"]');

        $i->amGoingTo('click Use This Folder button');
        $i->click(CourseModify::SELECTOR_USE_FOLDER);

        $i->amGoingTo('check the form has updated with this ID');
        $i->assertEquals(85, $i->executeJS("return $('#parent').val()"));

        $i->amGoingTo('lick Save Course button');
        $i->click(CourseModify::SELECTOR_SAVE_COURSE);

        $i->amGoingTo('check the database is updated to reflect the new course location');
        $i->seeInDatabase('folder', ['id' => 65, 'parent' => 85]);
    }
}
