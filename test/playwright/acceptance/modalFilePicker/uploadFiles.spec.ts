/**
 * uploads files into schoolbox by attaching files
 * via Resources, My Files, recording an audio and video, taking a picture
 */
import { test } from "@schoolboxTest/playwright/support/integrations";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";
import ResourcesMyFilesPicker from "@schoolboxTest/playwright/support/page/resources/ResourcesMyFilesPicker";

test.describe("Modal File Picker Upload Files", () => {
    let resourcesPage: Resources;
    let resourcesPicker: ResourcesMyFilesPicker;

    test.beforeEach(async ({ tester, page }) => {
        await tester.logout();
        await tester.loginAsAdmin();
        resourcesPage = new Resources(page);
        resourcesPicker = new ResourcesMyFilesPicker(tester, page);

        tester.isGoingTo("navigate to the resources page and open the Admin folder");
        await page.goto(ResourcesMyFilesPicker.URL);
        await resourcesPage.clickFolder(0);
    });

    /**
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
    test("Attach files via Resources", async ({ tester }) => {
        tester.isGoingTo("navigate to the Admin folder and attach files via Resources");
        await resourcesPage.clickFolder(57);
        await resourcesPicker.clickAttachmentZone();

        tester.isGoingTo("navigate to Admin > Tiles Icons > Black cursors and pointers icons and select a file");
        await resourcesPicker.clickResources();
        await resourcesPicker.clickSubFolders("Admin");
        await resourcesPicker.clickSubFolders("Tiles Icons");
        await resourcesPicker.clickSubFolders("Black cursors and pointers icons");
        await resourcesPicker.clickFile("clip15.png");

        tester.isGoingTo("attach another file from the Classes folder");
        await resourcesPicker.clickAttachmentZone();
        await resourcesPicker.clickResources();
        await resourcesPicker.clickSubFolders("Classes");
        await resourcesPicker.clickSubFolders("2019 Classes");
        await resourcesPicker.clickSubFolders("Middle School");
        await resourcesPicker.clickSubFolders("Year 9");
        await resourcesPicker.clickSubFolders("English");
        await resourcesPicker.clickSubHomeFolders("9A English (9ENGA)");
        await resourcesPicker.clickFile("Spelling tips");

        tester.isGoingTo("Verify the files are presented in the resources page under the Admin folder");
        await resourcesPage.seeFile("clip15");
        await resourcesPage.seeFile("Spelling tips");
    });

    /**
     * on resources page
     * click the admin folder
     * click the attachment zone
     * on the modal click My Files
     * select Abby file
     * ensure the file is presented under the admin folder
     */
    test("Attach files via My Files", async ({ tester }) => {
        tester.isGoingTo("navigate to the Admin folder and attach files via My Files");
        await resourcesPage.clickFolder(57);
        await resourcesPicker.clickAttachmentZone();
        await resourcesPicker.clickMyFiles();
        await resourcesPicker.clickFile("Abby");

        tester.isGoingTo("verify the file is presented in the Admin folder");
        await resourcesPage.seeFile("Abby");
    });

    /**
     * on resources page
     * click the admin folder
     * click the attachment zone
     * on the modal click Recording
     * click Audio
     * ensure the file is presented under the admin folder
     */
    test("Attach files by recording audio", async ({ tester, page }) => {
        tester.isGoingTo("navigate to the Admin folder and record an audio file");
        await resourcesPage.clickFolder(57);
        await resourcesPicker.clickAttachmentZone();
        await resourcesPicker.clickRecording();
        await resourcesPicker.clickAudio();

        tester.isGoingTo("start recording audio");
        await resourcesPicker.clickStartAudioVideoButton();
        await page.waitForTimeout(5000); // Record for 5 seconds

        tester.isGoingTo("stop recording and upload the file");
        await resourcesPicker.clickStopAudioVideoButton();
        await resourcesPicker.clickUploadButton();
        await resourcesPicker.typeName("testAudioAutomation");
        await resourcesPicker.clickSubmitButton();

        tester.isGoingTo("verify the audio file is presented");
        await resourcesPage.seeFile("testAudioAutomation");
    });

    /**
     * on resources page
     * click the admin folder
     * click the attachment zone
     * on the modal click Recording
     * click video
     * ensure the file is presented under the admin folder
     */
    test("Attach files by recording video", async ({ tester, page }) => {
        tester.isGoingTo("navigate to the Admin folder and record a video file");
        await resourcesPage.clickFolder(57);
        await resourcesPicker.clickAttachmentZone();
        await resourcesPicker.clickRecording();
        await resourcesPicker.clickVideo();

        tester.isGoingTo("start recording video");
        await resourcesPicker.clickStartAudioVideoButton();
        await page.waitForTimeout(5000); // Record for 5 seconds

        tester.isGoingTo("stop recording and upload the file");
        await resourcesPicker.clickStopAudioVideoButton();
        await resourcesPicker.clickUploadButton();
        await resourcesPicker.typeName("testVideoAutomation");
        await resourcesPicker.clickSubmitButton();

        tester.isGoingTo("verify the video file is presented");
        await resourcesPage.seeFile("testVideoAutomation");
    });

    /**
     * on resources page
     * click the admin folder
     * click the attachment zone
     * on the modal click Recording
     * click Photo
     * ensure the file is presented under the admin folder
     */
    test("Attach files by taking a photo", async ({ tester, page }) => {
        tester.isGoingTo("navigate to the Admin folder and take a photo");
        await resourcesPage.clickFolder(57);
        await resourcesPicker.clickAttachmentZone();
        await resourcesPicker.clickRecording();
        await resourcesPicker.clickPhoto();

        tester.isGoingTo("take a picture and upload the file");
        await resourcesPicker.clickCameraPhotoButton();
        await page.waitForTimeout(1000); // Simulate delay for taking a photo
        await resourcesPicker.clickUploadButton();
        await resourcesPicker.typeName("testPhotoAutomation");
        await resourcesPicker.clickSubmitButton();

        tester.isGoingTo("verify the photo file is presented");
        await resourcesPage.seeFile("testPhotoAutomation");
    });

    /**
     * Soft deleting data to not impact the tests
     * Putting files back to their original folders
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );

        await db.updateInDatabase("file", { category: 242 }, { "description LIKE": "clip15" });
        await db.updateInDatabase("file", { description: "clip15.png" }, { "description LIKE": "clip15" });
        await db.updateInDatabase("file", { category: 83 }, { "description LIKE": "Spelling tips" });

        const filesToDelete = ["Abby", "testAudioAutomation", "testVideoAutomation", "testPhotoAutomation"];
        await Promise.all(
            filesToDelete.map(async (fileName) => {
                await db.updateInDatabase(
                    "file",
                    { deleted_at: "2022-01-15 11:22:34" },
                    {
                        category: 57,
                        "description LIKE": fileName,
                    },
                );
            }),
        );
    });
});

// <?php

// /**
//  * @author    Julia Trevisan <julia.trevisan@alaress.com.au>
//  * @copyright Copyright 2022, Schoolbox Pty Ltd
//  * @link      https://schoolbox.com.au/
//  */

// namespace Tests\Acceptance\ModalFilePicker;

// use Tests\Support\AcceptanceTester;
// use Exception;
// use Tests\Support\Page\Resources\Resources;
// use Tests\Support\Page\Resources\ResourcesMyFilesPicker;

// /**
//  *
//  * uploads files into schoolbox by attaching files via Resources, My Files, recording an audio and video, taking a picture
//  *
//  * Steps:
//  * 1. Provision legacy.tar.gz data
//  * 2. vagrant ssh
//  * 3. Execute cd /usr/share/schoolbox && ./vendor/codeception/codeception/codecept --steps run Acceptance ModalFilePicker/UpoloadFilesCest
//  *
//  */
// class UpoloadFilesCest
// {
//     /**
//      * Preparations
//      *
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * get logged in
//      * navigate to https://vagrant.dev.schoolbox.cloud/resources
//      */
//     public function _before(AcceptanceTester $i): void
//     {
//         $i->logout();
//         $i->loginAsAdmin();
//         $resources = new ResourcesMyFilesPicker($i);
//         $resourcesPage = new Resources($i);
//         $i->amOnPage($resources::URL);
//         $resourcesPage->clickFolder(0);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on resources page
//      * click the admin folder
//      * click the attachment zone
//      * on the modal click Resources
//      * navigate to Admin > Tiles Icons > Black cursors and pointers icons
//      * select clip15.png file
//      * navigate to Classes>2019 Classes>Middle School>Year 9>English>9A English (9ENGA)
//      * select Spelling tips file
//      * ensure the files are presented under the admin folder
//      */
//     public function attachFilesViaResources(AcceptanceTester $i): void
//     {
//         $resources = new ResourcesMyFilesPicker($i);
//         $resourcesPage = new Resources($i);
//         $i->amGoingTo("click the Admin folder in the resources page");
//         $resourcesPage->clickFolder(57);
//         $i->amGoingTo("click the attach zone to upload files via modal");
//         $resources->clickAttachmentZone();
//         $i->amGoingTo("click Resources folder on the modal");
//         $resources->clickResources();
//         $i->amGoingTo("navigate to Admin>Tiles Icons>Black cursors and pointers icons and select a file");
//         $resources->clickSubFolders("Admin");
//         $resources->clickSubFolders("Tiles Icons");
//         $resources->clickSubFolders("Black cursors and pointers icons");
//         $resources->clickFile("clip15.png");
//         $i->amGoingTo("click the attach zone tp upload more files via modal");
//         $resources->clickAttachmentZone();
//         $i->amGoingTo("click Resources folder on the modal");
//         $resources->clickResources();
//         $i->amGoingTo("navigate to Classes>2019 Classes>Middle School>Year 9>English>9A English (9ENGA) and select a file");
//         $resources->clickSubFolders("Classes");
//         $resources->clickSubFolders("2019 Classes");
//         $resources->clickSubFolders("Middle School");
//         $resources->clickSubFolders("Year 9");
//         $resources->clickSubFolders("English");
//         $resources->clickSubHomeFolders("9A English (9ENGA)");
//         $resources->clickFile("Spelling tips");
//         $i->amGoingTo("verify the files are presented in the resources page under the Admin folder");
//         $i->canSee("clip15");
//         $i->canSee("Spelling tips");
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on resources page
//      * click the admin folder
//      * click the attachment zone
//      * on the modal click My Files
//      * select Abby file
//      * ensure the file is presented under the admin folder
//      */
//     public function attachFilesViaMyFiles(AcceptanceTester $i): void
//     {
//         $resources = new ResourcesMyFilesPicker($i);
//         $resourcesPage = new Resources($i);
//         $i->amGoingTo("click the Admin folder in the resources page");
//         $resourcesPage->clickFolder(57);
//         $i->amGoingTo("click the attach zone to upload files via modal");
//         $resources->clickAttachmentZone();
//         $i->amGoingTo("click My Files folder on the modal");
//         $resources->clickMyFiles();
//         $i->amGoingTo("click Abby file");
//         $resources->clickFile("Abby");
//         $i->canSee("Abby");
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on resources page
//      * click the admin folder
//      * click the attachment zone
//      * on the modal click Recording
//      * click Audio
//      * ensure the file is presented under the admin folder
//      */
//     public function attachFilesByRecordingAudio(AcceptanceTester $i): void
//     {
//         $resources = new ResourcesMyFilesPicker($i);
//         $resourcesPage = new Resources($i);
//         $i->amGoingTo("click the Admin folder in the resources page");
//         $resourcesPage->clickFolder(57);
//         $i->amGoingTo("click the attach zone to upload files via modal");
//         $resources->clickAttachmentZone();
//         $i->amGoingTo("click Recording on the modal and then click Audio button");
//         $resources->clickRecording();
//         $resources->clickAudio();
//         $i->amGoingTo("click the button to start recording and wait 10 seconds to record the audio");
//         $resources->clickStartAudioVideoButton();
//         $i->wait(10);
//         $i->amGoingTo("click the button to stop recording");
//         $resources->clickStopAudioVideoButton();
//         $i->amGoingTo("click the Upload button, type a file name and submit it");
//         $resources->clickUploadButton();
//         $resources->typeName('testAudioAutomation');
//         $resources->clickSubmitButton();
//         $i->waitForText('testAudioAutomation', 3);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on resources page
//      * click the admin folder
//      * click the attachment zone
//      * on the modal click Recording
//      * click video
//      * ensure the file is presented under the admin folder
//      */
//     public function attachFilesByRecordingAVideo(AcceptanceTester $i): void
//     {
//         $resources = new ResourcesMyFilesPicker($i);
//         $resourcesPage = new Resources($i);
//         $i->amGoingTo("click the Admin folder in the resources page");
//         $resourcesPage->clickFolder(57);
//         $i->amGoingTo("click the attach zone to upload files via modal");
//         $resources->clickAttachmentZone();
//         $i->amGoingTo("click Recording on the modal and then click Video button");
//         $resources->clickRecording();
//         $resources->clickVideo();
//         $i->amGoingTo("click the button to start recording and wait 10 seconds to record the video");
//         $resources->clickStartAudioVideoButton();
//         $i->wait(10);
//         $i->amGoingTo("click the button to stop recording");
//         $resources->clickStopAudioVideoButton();
//         $i->amGoingTo("click the Upload button, type a file name and submit it");
//         $resources->clickUploadButton();
//         $resources->typeName('testVideoAutomation');
//         $resources->clickSubmitButton();
//         $i->waitForText('testVideoAutomation', 3);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on resources page
//      * click the admin folder
//      * click the attachment zone
//      * on the modal click Recording
//      * click Photo
//      * ensure the file is presented under the admin folder
//      */
//     public function attachFilesByTakingPhoto(AcceptanceTester $i): void
//     {
//         $resources = new ResourcesMyFilesPicker($i);
//         $resourcesPage = new Resources($i);
//         $i->amGoingTo("click the Admin folder in the resources page");
//         $resourcesPage->clickFolder(57);
//         $i->amGoingTo("click the attach zone to upload files via modal");
//         $resources->clickAttachmentZone();
//         $i->amGoingTo("click Recording on the modal and then click Photo button");
//         $resources->clickRecording();
//         $resources->clickPhoto();
//         $i->amGoingTo("click the button to take a picture");
//         $resources->clickCameraPhotoButton();
//         $i->wait(2);
//         $i->amGoingTo("click the Upload button, type a file name and submit it");
//         $resources->clickUploadButton();
//         $resources->typeName('testPhotoAutomation');
//         $resources->clickSubmitButton();
//         $i->waitForText('testPhotoAutomation', 3);
//     }

//     /**
//      * Soft deleting data to not impact the tests
//      * Putting files back to their original folders
//      *
//      * @param AcceptanceTester $i
//      */
//     public function tearDown(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
//         $i->updateInDatabase('file', ['category' => 242], ['description like' => "clip15"]);
//         $i->updateInDatabase('file', ['description' => "clip15.png"], ['description like' => "clip15"]);
//         $i->updateInDatabase('file', ['category' => 83], ['description like' => "Spelling tips"]);
//         $i->updateInDatabase('file', ['deleted_at' => "2022-01-15 11:22:34"], ['category' => 57,'description like' => "Abby"]);
//         $i->updateInDatabase('file', ['deleted_at' => "2022-01-15 11:22:34"], ['category' => 57,'description like' => "testAudioAutomation"]);
//         $i->updateInDatabase('file', ['deleted_at' => "2022-01-15 11:22:34"], ['category' => 57,'description like' => "testVideoAutomation"]);
//         $i->updateInDatabase('file', ['deleted_at' => "2022-01-15 11:22:34"], ['category' => 57,'description like' => "testPhotoAutomation"]);
//     }
// }
