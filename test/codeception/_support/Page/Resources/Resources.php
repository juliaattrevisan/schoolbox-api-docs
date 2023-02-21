<?php

/**
 * @author    Julia Trevisan <julia.trevisan@alaress.com.au>
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Resources;

use AcceptanceTester;
use Codeception\Util\Locator;
use Exception;

class Resources
{
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
     * clicks a folder in the resources page by folder id
     * @param int $folderId
     * @throws Exception
     */
    public function clickFolder(int $folderId): void
    {
        $this->tester->executeJS('$("a#' . $folderId . '_anchor").click()');
    }


    /**
     * clicks a folder in the resources page by folder name
     * @param string $folderName
     * @throws Exception
     */
    public function clickFolderName(string $folderName): void
    {
        $this->tester->click(Locator::contains('a.jstree-anchor', $folderName));
    }

    /**
     * clicks on the right side menu options to a folder:
     * home, modify, drop-box, files, folder, link, download, upload, delete
     * @param string $menuItem
     * @throws Exception
     */
    public function clickMenuItem(string $menuItem): void
    {
        $this->tester->click('a.icon-' . $menuItem);
    }
}
