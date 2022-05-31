<?php

/**
 * @author    Julia Trevisan <julia.trevisan@alaress.com.au>
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

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
     * navigate to Admin > Tiles Icons > Black cursors and pointers icons
     * select clip15.png file
     * navigate to Classes>2019 Classes>Middle School>Year 9>English>9A English (9ENGA)
     * select Spelling tips file
     * ensure the files are presented under the admin folder
     */
    public function attachFilesViaResources(AcceptanceTester $i)
    {
        $resources = new ResourcesMyFilesPicker($i);
        $i->amGoingTo("click the Admin folder in the resources page");
        $resources->clickFolder(57);
        $i->amGoingTo("click the attach zone tp upload files via modal");
        $resources->clickAttachmentZone();
        $i->amGoingTo("click Resources folder on the modal");
        $resources->clickResources();
        $i->amGoingTo("navigate to Admin>Tiles Icons>Black cursors and pointers icons and select a file");
        $resources->clickSubFolders("Admin");
        $resources->clickSubFolders("Tiles Icons");
        $resources->clickSubFolders("Black cursors and pointers icons");
        $resources->clickFiles("clip15.png");
        $i->amGoingTo("click the attach zone tp upload more files via modal");
        $resources->clickAttachmentZone();
        $i->amGoingTo("click Resources folder on the modal");
        $resources->clickResources();
        $i->amGoingTo("navigate to Classes>2019 Classes>Middle School>Year 9>English>9A English (9ENGA) and select a file");
        $resources->clickSubFolders("Classes");
        $resources->clickSubFolders("2019 Classes");
        $resources->clickSubFolders("Middle School");
        $resources->clickSubFolders("Year 9");
        $resources->clickSubFolders("English");
        $resources->clickSubHomeFolders("9A English (9ENGA)");
        $resources->clickFiles("Spelling tips");
        $i->amGoingTo("verify the files are presented in the resources page under the Admin folder");
        $i->canSee("clip15");
        $i->canSee("Spelling tips");
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on resources page
     * click the admin folder
     * click the attachment zone
     * on the modal click My Files
     * select Abby file
     * ensure the file is presented under the admin folder
     */
    public function attachFilesViaMyFiles(AcceptanceTester $i)
    {
        $resources = new ResourcesMyFilesPicker($i);
        $i->amGoingTo("click the Admin folder in the resources page");
        $resources->clickFolder(57);
        $i->amGoingTo("click the attach zone tp upload files via modal");
        $resources->clickAttachmentZone();
        $i->amGoingTo("click My Files folder on the modal");
        $resources->clickMyFiles();
        $i->amGoingTo("click Abby file");
        $resources->clickFiles("Abby");
        $i->canSee("Abby");
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on resources page
     * click the admin folder
     * click the attachment zone
     * on the modal click Recording
     * click Audio
     * ensure the file is presented under the admin folder
     */
    public function attachFilesByRecordingAudio(AcceptanceTester $i)
    {
        $resources = new ResourcesMyFilesPicker($i);
        $i->amGoingTo("click the Admin folder in the resources page");
        $resources->clickFolder(57);
        $i->amGoingTo("click the attach zone tp upload files via modal");
        $resources->clickAttachmentZone();
        $i->amGoingTo("click Recording on the modal and then click Audio button");
        $resources->clickRecording();
        $resources->clickAudio();
        $i->amGoingTo("click the button to start recording and wait 10 seconds to record the audio");
        $resources->clickStartAudioVideoButton();
        $i->wait(10);
        $i->amGoingTo("click the button to stop recording");
        $resources->clickStopAudioVideoButton();
        $i->amGoingTo("click the Upload button, type a file name and submit it");
        $resources->clickUploadButton();
        $resources->typeName('testAudioAutomation');
        $resources->clickSubmitButton();
        $i->waitForText('testAudioAutomation2', 3);
    }
}
