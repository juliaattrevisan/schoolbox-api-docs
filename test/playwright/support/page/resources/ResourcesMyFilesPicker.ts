import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";

export default class ResourcesMyFilesPicker {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/resources/";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Clicks the attachment zone
     */
    async clickAttachmentZone(): Promise<void> {
        await this.page.locator("#CmsZone").click();
    }

    /**
     * Clicks the Resources folder
     */
    async clickResources(): Promise<void> {
        await this.page.locator('[href="#filesystem-browse-panel"]').click();
    }

    /**
     * Clicks the My Files folder
     */
    async clickMyFiles(): Promise<void> {
        await this.page.locator('[href="#filesystem-myfile-panel"]').click();
    }

    /**
     * Clicks the Recording option
     */
    async clickRecording(): Promise<void> {
        await this.page.locator('[href="#filesystem-usermedia-panel"]').click();
    }

    /**
     * Clicks a sub-folder by title
     * @param folderTitle - The sub-folder title
     */
    async clickSubFolders(folderTitle: string): Promise<void> {
        await this.page.locator(`a[title="${folderTitle}"].icon-folder`).click();
    }

    /**
     * Clicks a sub-folder in the home section by title
     * @param homeFolder - The home folder title
     */
    async clickSubHomeFolders(homeFolder: string): Promise<void> {
        await this.page.locator(`a[title="${homeFolder}"].icon-home`).click();
    }

    /**
     * Finds a file by its title, asserts if it is visible, and returns the locator
     * @param fileName - The file title
     * @returns The file locator
     */
    async findFile(fileName: string): Promise<Locator> {
        const file = this.page.locator(`a[title="${fileName}"]`);
        await expect(file).toBeVisible();
        return file;
    }

    /**
     * Clicks a file by its title
     * @param folderTitle - The file title
     */
    async clickFile(folderTitle: string): Promise<void> {
        const file = await this.findFile(folderTitle);
        await file.scrollIntoViewIfNeeded();
        await file.click();
        await this.tester.common.waitForModalToBeClosed();
    }

    /**
     * Clicks the Audio option
     */
    async clickAudio(): Promise<void> {
        const audioIcon = this.page.locator('div[class="icon-mic"]');
        await audioIcon.click();
    }

    /**
     * Clicks the Photo option
     */
    async clickPhoto(): Promise<void> {
        const photoIcon = this.page.locator('div[class="icon-photo"]');
        await photoIcon.click();
    }

    /**
     * Clicks the Video option
     */
    async clickVideo(): Promise<void> {
        const videoIcon = this.page.locator('div[class="icon-video"]');
        await videoIcon.click();
    }

    /**
     * Clicks the Screen Capture option
     */
    async clickScreen(): Promise<void> {
        const screenIcon = this.page.locator('div[class="icon-screen-capture"]');
        await screenIcon.click();
    }

    /**
     * Clicks the Start button for audio/video recording
     */
    async clickStartAudioVideoButton(): Promise<void> {
        const startButton = this.page.locator("button.vjs-record-button.vjs-control.vjs-button.vjs-icon-record-start");
        await startButton.click();
    }

    /**
     * Clicks the Stop button for audio/video recording
     */
    async clickStopAudioVideoButton(): Promise<void> {
        const stopButton = this.page.locator("button.vjs-record-button.vjs-control.vjs-button.vjs-icon-record-stop");
        await stopButton.click();
    }

    /**
     * Clicks the Camera button to take a picture
     */
    async clickCameraPhotoButton(): Promise<void> {
        const cameraButton = this.page.locator("button.vjs-camera-button.vjs-control.vjs-button.vjs-icon-photo-camera");
        await cameraButton.click();
    }

    /**
     * Types a file name
     * @param fileName - The name of the file to type
     */
    async typeName(fileName: string): Promise<void> {
        await this.page.locator("input#filename").fill(fileName);
    }

    /**
     * Clicks the Upload button
     */
    async clickUploadButton(): Promise<void> {
        const uploadButton = this.page.locator('a[title="Upload"]');
        await uploadButton.click();
    }

    /**
     * Clicks the Submit button
     */
    async clickSubmitButton(): Promise<void> {
        const submitButton = this.page.locator("button.submit");
        await submitButton.click();
    }
}
