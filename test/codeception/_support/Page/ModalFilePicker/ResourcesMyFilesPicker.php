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
//        $this->tester->executeJS('$("a#57_anchor").click()');
        $this->tester->executeJS('$("a#' . $folderId . '_anchor").click()');
        sleep(2);
    }

    /**
     * clicks the attachment zone
     */
    public function clickAttachmentZone(): void
    {
        $this->tester->click('[id="CmsZone"]');
    }
}
