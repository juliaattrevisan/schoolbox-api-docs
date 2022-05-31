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
}
