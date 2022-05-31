<?php

/**
 * @author    Julia Trevisan <julia.trevisan@alaress.com.au>
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\ModalFilePicker;

use AcceptanceTester;
use Exception;

class ResourcesMyFilesPicker
{

    public const URL = '/resources/';
    private AcceptanceTester $tester;


    /**
     * Constructor
     *
     * @param AcceptanceTester $i
     */
    public function __construct(AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * clicks the admin folder
     * @param int $folderId
     * @throws Exception
     */
    public function clickFolder(int $folderId)
    {
        $this->tester->executeJS('$("a#' . $folderId . '_anchor").click()');
    }

    /**
     * clicks the attachment zone
     */
    public function clickAttachmentZone(): void
    {
        $this->tester->click('[id="CmsZone"]');
    }

    /**
     * clicks the resources folder
     * @throws Exception
     */
    public function clickResources(): void
    {
        $this->tester->click('[href="#filesystem-browse-panel"]');
    }

    /**
     * clicks the my files folder
     * @throws Exception
     */
    public function clickMyFiles(): void
    {
        $this->tester->click('[href="#filesystem-myfile-panel"]');
    }

    /**
     * clicks Recording
     * @throws Exception
     */
    public function clickRecording(): void
    {
        $this->tester->click('[href="#filesystem-usermedia-panel"]');
    }

    /**
     * @param string $folderTitle
     * clicks the attachment zone
     */
    public function clickSubFolders(string $folderTitle)
    {
        $this->tester->click('a[title="' . $folderTitle . '"][class="icon-folder"]');
    }

    /**
     * @param string $homeFolder
     * clicks the attachment zone
     */
    public function clickSubHomeFolders(string $homeFolder)
    {
        $this->tester->click('a[title="' . $homeFolder . '"][class="icon-home"]');
    }


    /**
     * @param string $fileTitle
     * clicks a file
     */
    public function clickFiles(string $folderTitle)
    {
        $this->tester->click('a[title="' . $folderTitle . '"]');
    }

    /**
     * clicks audio
     * @throws Exception
     */
    public function clickAudio(): void
    {
        $this->tester->waitForElementVisible('div[class="icon-mic"]', 5);
        $this->tester->click('div[class="icon-mic"]');
    }

    /**
     * clicks photo
     * @throws Exception
     */
    public function clickPhoto(): void
    {
        $this->tester->waitForElementVisible('div[class="icon-photo"]', 5);
        $this->tester->click('div[class="icon-photo"]');
    }

    /**
     * clicks video
     * @throws Exception
     */
    public function clickVideo(): void
    {
        $this->tester->waitForElementVisible('div[class="icon-video"]', 5);
        $this->tester->click('div[class="icon-video"]');
    }

    /**
     * clicks screen
     * @throws Exception
     */
    public function clickScreen(): void
    {
        $this->tester->waitForElementVisible('div[class="icon-screen-capture"]', 5);
        $this->tester->click('div[class="icon-screen-capture"]');
    }

    /**
     * clicks start button
     * @throws Exception
     */
    public function clickStartAudioVideoButton(): void
    {
        $this->tester->waitForElementVisible('button.vjs-record-button.vjs-control.vjs-button.vjs-icon-record-start', 5);
        $this->tester->click('button.vjs-record-button.vjs-control.vjs-button.vjs-icon-record-start');
    }

    /**
     * clicks stop button
     * @throws Exception
     */
    public function clickStopAudioVideoButton(): void
    {
        $this->tester->waitForElementVisible('button.vjs-record-button.vjs-control.vjs-button.vjs-icon-record-stop', 5);
        $this->tester->click('button.vjs-record-button.vjs-control.vjs-button.vjs-icon-record-stop');
    }

    /**
     * @param string $fileName
     * type a file name
     */
    public function typeName(string $fileName): void
    {
        $this->tester->fillField(['id' => 'filename'], $fileName);
    }

    /**
     * clicks stop button
     * @throws Exception
     */
    public function clickUploadButton(): void
    {
        $this->tester->waitForElementVisible('a[title="Upload"]', 5);
        $this->tester->click('a[title="Upload"]');
    }

    /**
     * clicks stop button
     * @throws Exception
     */
    public function clickSubmitButton(): void
    {
        $this->tester->waitForElementVisible('button.submit', 5);
        $this->tester->click('button.submit');
    }
}
