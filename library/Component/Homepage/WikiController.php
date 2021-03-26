<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_WikiController extends Component_Homepage_ControllerAbstract
{

    /**
     * Available sort fields for this component.
     *
     * @var array
     */
    protected $sortFields = ['Heading', 'Created', 'Modified'];

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable()) {
            return $this->hasContextWriteAccess();
        }

        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'wiki')) {
            return false;
        }
        if (!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        }

        /* Need title injected here
         */
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_wiki', 'has_wiki_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $wikiHandler = new Wiki();
        $userHandler = new User();

        $wikiList = $wikiHandler->getByCategory(
            $this->getSource()->getId(), strtolower($this->getConfig('sort')),
            $this->getConfig('sortdir'), $this->getConfig('size')
        );

        $this->setVar('WIKI_CATEGORY_ID', $this->getSource()->getId());

        if (!is_array($wikiList)) {
            $this->setVar(
                [
                    'has_wiki_block'    => '',
                    'empty_read_block'  => '',
                    'empty_write_block' => '',
                    'SOURCE_ID'         => $this->getSource()->getId(),
                ]
            );

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                return false;
            }

            $this->parse('empty_state_block', 'empty_state');
        } else {
            $this->setBlock('has_wiki', 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock('has_wiki', 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('source_folder_exists', 'wiki_row', 'wiki_row_block');
            $this->setBlock('source_folder_exists', 'submit_wiki', 'submit_wiki_block');
            $this->setBlock('wiki_row', 'compact_hide', 'compact_hide_block');
            $this->setBlock('wiki_row', 'modify_wiki', 'modify_wiki_block');

            $this->setVar('empty_state_block', '');

            // Custom sorting options

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            } else {
                $this->setVar('source_folder_deleted_block', '');

                $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());

                /* Prevent multiple blog entries from other categories stacking up in the wrong place
                * and initialise empty wiki roll by default
                */
                $this->setVar('wiki_row_block', '');

                // Controls such as Post News

                /* First check that Allow Create is active. If it is, continue;
                 * otherwise proceed to the usual getPermission()
                 */
                $value = \Component_ConfigMapper::getInstance()->findValue('allowCreate', $this->getInstance()->getId());

                if (((int)$value === 1) && ($this->getContentPermission() < UserAccess::ACCESS_WRITE)) {
                    $this->parse("submit_wiki_block", "submit_wiki");
                } elseif ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                    $this->setVar("submit_wiki_block", "");
                } else {
                    /* Allow Create is off
                     */
                    $this->parse("submit_wiki_block", "submit_wiki");
                }

                // Iterate
                foreach ($wikiList as $wiki) {
                    $modifiedTime = new Alaress_Date_Format($wiki['modified'], 'U');

                    $this->setVar(
                        [
                            'WIKI_ID'         => $wiki['wikiID'],
                            'WIKI_CATEGORYID' => $wiki['categoryID'],
                            'WIKI_SLUG'       => $wiki['slug'],
                            'WIKI_HEADING'    => Scrub::text($wiki['heading']),
                            'WIKI_AUTHOR'     => $userHandler->formatName($wiki),
                            'WIKI_AUTHOR_ID'  => $wiki['userID'],
                            'WIKI_MODIFIED'   => $modifiedTime->getRelativeTime(),
                            'WIKI_BODY'       => TextFormatter::cutSentence(Scrub::html($wiki['body']), 500),
                        ]
                    );

                    if ($this->getConfig('compactMode')) {
                        $this->setVar(
                            [
                                'compact_hide_block' => '',
                                'WIKI_COMPACT'       => 'compact',
                            ]
                        );
                    } else {
                        $this->setVar('WIKI_COMPACT', '');

                        $this->parse('compact_hide_block', 'compact_hide');
                    }

                    $this->setVar('modify_wiki_block', '');

                    if (($this->getContentPermission() >= UserAccess::ACCESS_READ && $wiki['locked'] == 'false')
                        || $this->getContentPermission() >= UserAccess::ACCESS_WRITE
                    ) {
                        $this->parse('modify_wiki_block', 'modify_wiki');
                    }

                    $this->parse('wiki_row_block', 'wiki_row', true);
                }

                $this->parse('source_folder_exists_block', 'source_folder_exists');
            }

            $this->parse('has_wiki_block', 'has_wiki');
        }

        return true; // everything went according to plan
    }
}
