<?php

declare(strict_types=1);

namespace ManageCurriculum;

use AcceptanceTester;
use Codeception\Util\Locator;
use Page\Curriculum\Manage\ManageCurriculum;
use Page\Curriculum\Manage\action\AddNewCurriculum;
use Exception;

class ManageCurriculumCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in
     * navigate to https://vagrant.dev.schoolbox.cloud/curriculum
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->loginAsAdmin();
        $mc = new ManageCurriculum($i);
        $i->amOnPage($mc::URL);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum create a new curriculum
     */
    public function addNewCurriculum(AcceptanceTester $i)
    {
        $addNew = new AddNewCurriculum($i);
        $i->amGoingTo('Add a new Curriculum');
        $addNew->toggleDisplay();
        $addNew->seeAddButtonIsDisabled();
        $addNew->fillName('Party Town');
        $addNew->fillCode('PT');
        $addNew->clickAddButton('PT');
        $i->canSee('Curriculum successfully added');
        $i->canSee('[PT] Party Town');
    }

}