import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

/**
 * Support file for interacting with the <evidence-media-recording> component
 * @see /library/Schoolbox/Learning/Moment/vue/evidence-media-recording.vue
 */
export default class EvidenceMediaRecording {
    private tester: AcceptanceTester;

    private page: Page;

    // Constants for data-test selectors
    static readonly DATA_TEST_CAPTURE_MEDIA_CONTAINER = "capture-media-container";

    static readonly SELECTOR_TAKE_PHOTO = "button.vjs-camera-button.vjs-control.vjs-button.vjs-icon-photo-camera";
    static readonly SELECTOR_RECORDING_START = "button.vjs-record-button.vjs-control.vjs-button.vjs-icon-record-start";
    static readonly SELECTOR_RECORDING_STOP = "button.vjs-record-button.vjs-control.vjs-button.vjs-icon-record-stop";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Locate the <evidence-media-recording> component on the page
     * This is used for the share photo, video, and audio flows
     * @returns Locator for the <evidence-media-recording> component
     */
    public locateCaptureMediaContainer(): Locator {
        return this.page.getByTestId(EvidenceMediaRecording.DATA_TEST_CAPTURE_MEDIA_CONTAINER);
    }

    /**
     * Click the button to take a photo
     */
    async takePhoto(): Promise<void> {
        const mediaContainer = this.locateCaptureMediaContainer();
        await mediaContainer.locator(EvidenceMediaRecording.SELECTOR_TAKE_PHOTO).click();
    }

    /**
     * Click the button to start recording a video/audio
     */
    async startRecording(): Promise<void> {
        const mediaContainer = this.locateCaptureMediaContainer();
        await mediaContainer.locator(EvidenceMediaRecording.SELECTOR_RECORDING_START).click();
    }

    /**
     * Click the button to stop recording a video/audio
     */
    async stopRecording(): Promise<void> {
        const mediaContainer = this.locateCaptureMediaContainer();
        await mediaContainer.locator(EvidenceMediaRecording.SELECTOR_RECORDING_STOP).click();
    }
}
