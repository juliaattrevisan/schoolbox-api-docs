<?php

declare(strict_types=1);

namespace ManageCurriculum;

use AcceptanceTester;
use Codeception\Util\Locator;
use Page\ManageCurriculum\ManageCurriculum;
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
        $mc = new ManageCurriculum($i);
        $i->amGoingTo('Add a new Curriculum');
        $i->click('Create New Curriculum');
        $i->seeElement('[data-test-add-curriculum]:disabled');
        $mc->fillNewCurriculumName('Party Town');
        $i->fillField('#code', 'PT');
        $i->click('Add');
        $i->canSee('Curriculum successfully added');
        $i->canSee('[PT] Party Town');
    }

}