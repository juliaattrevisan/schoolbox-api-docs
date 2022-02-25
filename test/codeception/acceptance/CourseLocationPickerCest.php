<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace acceptance;

use AcceptanceTester;
use Page\CourseModify;
use Exception;

/**
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps acceptance CourseLocationPickerCest
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
        // Set the location of the 'ELC' course to 'Competing ideologies' unit
        $i->updateInDatabase('folder', ['parent' => 266], ['id' => 65]);

        $i->loginAsAdmin();
        ;
        $i->amOnPage(CourseModify::getUrl(4));
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
        // Set the location of the 'ELC' course to 'Competing ideologies' unit
        $i->updateInDatabase('folder', ['parent' => 266], ['id' => 65]);
    }


    /**
     * Test that user can select current (invalid) location
     *
     * @param AcceptanceTester $i
     */
    public function retainCurrentInvalid(AcceptanceTester $i): void
    {
        // 'Use This Folder' button is initially disabled
        $i->seeElement(CourseModify::SELECTOR_USE_FOLDER . '[disabled]');

        // User clicks current location 'Competing ideologies' unit
        $i->click('[id="266"]');

        // 'Use This Folder' button is now enabled
        $i->seeElement(CourseModify::SELECTOR_USE_FOLDER . ':not([disabled])');

        // User clicks 'Use This Folder' button
        $i->click(CourseModify::SELECTOR_USE_FOLDER);

        // The hidden form field retains this value
        $val = $i->executeJS("return $('#parent').val()");
        $i->assertEquals(266, $val);

        // User clicks 'Save Course' button
        $i->click(CourseModify::SELECTOR_SAVE_COURSE);

        $i->seeInDatabase('course_specification', ['id' => 4, 'folder_id' => 65]);
        $i->seeInDatabase('folder', ['id' => 65, 'parent' => 266]);
    }


    /**16
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
        // open the 'French' homepage node
        $i->scrollAndClick('[id="10"]');

        // 'French' homepage should be selected
        $i->assertEquals(true, $i->executeJS("return $('#10 > .jstree-wholerow.jstree-wholerow-clicked').length"));

        // User can see 'Year 9 French' course node
        $i->see('Year 9 French');

        // User clicks 'Year 9 French' course node
        $i->click('[id="16"]');

        // 'Year 9 French' is not selected
        $i->assertEquals(false, $i->executeJS("return $('#16 > .jstree-wholerow.jstree-wholerow-clicked').length"));

        // User cannot see units inside 'Year 9 French' course
        $i->assertEquals(true, $i->executeJS("return $('#16.jstree-leaf').length"));

        // But 'French' homepage still is selected
        $i->assertEquals(true, $i->executeJS("return $('#10 > .jstree-wholerow.jstree-wholerow-clicked').length"));

        // User clicks 'English' homepage
        $i->scrollAndClick('[id="85"]');

        // Then clicks 'Use This Folder' button
        $i->click(CourseModify::SELECTOR_USE_FOLDER);

        // Form has updated with this ID
        $i->assertEquals(85, $i->executeJS("return $('#parent').val()"));

        // User clicks 'Save Course' button
        $i->click(CourseModify::SELECTOR_SAVE_COURSE);

        // Database is updated to reflect the new course location
        $i->seeInDatabase('folder', ['id' => 65, 'parent' => 85]);
    }
}
