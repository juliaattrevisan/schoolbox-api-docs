<?php

/**
 * @author    Julia Trevisan <julia.trevisan@alaress.com.au>
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use AcceptanceTester;
use Page\ModalFilePicker\ResourcesMyFilesPicker;

class UpoloadFilesCest
{

    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in
     * navigate to https://vagrant.dev.schoolbox.cloud/resources
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->loginAsAdmin();
        $resources = new ResourcesMyFilesPicker($i);
        $i->amOnPage($resources::URL);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on resources page
     * click the admin folder
     * click the attachment zone
     * on the modal click Resources
     * navigate to Admin > Dashboard Components > Middle Student Image Slider
     * select camp file
     * ensure the camp file is presented under the admin folder
     */
    public function attachFilesViaResources(AcceptanceTester $i)
    {
        $resources = new ResourcesMyFilesPicker($i);
        $resources->clickFolder(57);
        $resources->clickAttachmentZone();

    }
}
