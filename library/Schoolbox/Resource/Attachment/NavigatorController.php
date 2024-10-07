<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment;

use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Scrub;
use SitePage;

/**
 * Class NavigatorController
 *
 * @package Schoolbox\Resource\Attachment
 */
abstract class NavigatorController extends AbstractController
{
    /**
     * @param bool               $multi
     * @param string|bool|null   $mimeFilter
     * @param IConfigLoader|null $conf
     * @param RosettaStone|null  $rosetta
     * @param bool               $useFolder
     * @param int                $defaultFolderId
     */
    public function __construct($multi, $mimeFilter = null, $conf = null, $rosetta = null, $useFolder = true, $defaultFolderId = 0)
    {
        parent::__construct($multi, $mimeFilter, $useFolder, $defaultFolderId);
        $this->template = 'navigator';
    }


    /**
     * @param SitePage $page
     * @return void
     */
    protected function pparse(SitePage $page)
    {
        /* socialstreambrowser has an explicit use case for explicitly being false
         * in browser.js::149 loadNode(). The only way to have folders-only is to
         * specify an empty mimeFilter using (bool)false; null is the default case
         * which shows you both folders and files; otherwise it filters to the string
         * you specified
         *
         * I thought that setting mimeFilter to an empty string would be the equivalent
         * of folders-only, but alright, mr matt will have his mysteries
         */
        if (null === $this->mimeFilter) {
            $mimeFilter = 'null';
        } elseif (false === $this->mimeFilter || '' === $this->mimeFilter) {
            $mimeFilter = 'false';
        } else {
            $mimeFilter = "'" . Scrub::text(rawurldecode($this->mimeFilter)) . "'";
        }

        /* Enable a Use This Folder button in the component action bar, but only
         * if we wanted it on; parse it away otherwise
         */
        $page->setBlock(TEMPLATE_content, 'use_this_folder_component_action', 'use_this_folder_component_action_block');

        if ($this->useFolder) {
            $page->parse('use_this_folder_component_action_block', 'use_this_folder_component_action');
        } else {
            $page->setVar([
                'use_this_folder_component_action_block' => ''
            ]);
        }

        $page->setVar([
            'USE_FOLDER' => $this->useFolder ? 'true' : 'false',
            'FOLDER_ID'  => $this->defaultFolderId,
            'MIME_FILTER' => $mimeFilter,
            'SOURCE_SLUG' => substr($this->getSlug(), strpos($this->getSlug(), '/') + 1),
        ]);
    }
}
