<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_WikiController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var Group
     */
    private $groupHandler;


    /**
     * Available sort fields for this component.
     *
     * @var array
     */
    protected $sortFields = array('Heading', 'Created', 'Modified');

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        if(!parent::execute()){
            return false;
        }

        // Short Circuit: No point to load data if we can't view it
        if(!$this->isSourceRenderable()){
            return true;
        }

        $this->groupHandler = new Group();
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

        // Set up component name
        $this->setVar(array(
            'COMPONENT_INSTANCE_ID' => $this->instance->getId(),
        ));



        /* Need title injected here
         */
        $this->parseConfig();

        $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
        $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
        $this->setBlock('source_folder_exists', 'wiki_row', 'wiki_row_block');
        $this->setBlock('source_folder_exists', 'submit_wiki', 'submit_wiki_block');
        $this->setBlock('wiki_row', 'compact_hide', 'compact_hide_block');
        $this->setBlock('wiki_row', 'modify_wiki', 'modify_wiki_block');

        // Custom sorting options

        if(!$this->isSourceRenderable()) {
            if($this->hasContextWriteAccess()) {
                if(!$this->hasReadAccess()) {
                    $this->renderAltSourceDenied();
                } else if($this->getSource()->isDeleted()) {
                    $this->renderAltSourceDeleted();
                } else {
                    $this->renderEmptyState();
                }
            } else {
                return false;
            }
        } else {
            $this->setVar('source_folder_deleted_block', '');

            $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());

            $wikiHandler = new Wiki();
            $userHandler = new User();

            

            /* Prevent multiple blog entries from other categories stacking up in the wrong place
            * and initialise empty wiki roll by default
            */
            $this->setVar('wiki_row_block', '');

            $wikiList = $wikiHandler->getByCategory($this->getSource()->getId(), strtolower($this->getConfig('sort')),
                $this->getConfig('sortdir'), $this->getConfig('size'));

            $this->setVar(
                array(
                    'WIKI_CATEGORY_ID' => $this->source->getId()
                )
            );

            // Controls such as Post News

            /* First check that Allow Create is active. If it is, continue;
             * otherwise proceed to the usual getPermission()
             */
            $value = \Component_ConfigMapper::getInstance()->findValue('allowCreate', $this->instance->getId());

            if (((int)$value === 1) && ($this->contentPermission < WRITE_ACCESS)) {
                $this->parse("submit_wiki_block", "submit_wiki");
            } elseif ($this->contentPermission < WRITE_ACCESS) {
                $this->setVar("submit_wiki_block", "");
            } else {
                /* Allow Create is off
                 */
                $this->parse("submit_wiki_block", "submit_wiki");
            }

            // Iterate
            if (is_array($wikiList)) {
                $j = 0;
                foreach ($wikiList as $wiki) {
                    $modifiedTime = new Alaress_Date_Format($wiki['modified'], 'U');

                    $this->setVar(
                        array(
                            'WIKI_ID' => $wiki['wikiID'],
                            'WIKI_CATEGORYID' => $wiki['categoryID'],
                            'WIKI_SLUG' => $wiki['slug'],
                            'WIKI_HEADING' => Scrub::text($wiki['heading']),
                            'WIKI_AUTHOR' => $userHandler->formatName($wiki),
                            'WIKI_AUTHOR_ID' => $wiki['userID'],
                            'WIKI_MODIFIED' => $modifiedTime->getRelativeTime(),
                            'WIKI_BODY' => TextFormatter::cutSentence(Scrub::html($wiki['body']), 500),
                            'ON_OFF' => ++$j % 2 ? '' : 'dark',
                        )
                    );

                    if ($this->getConfig('compactMode')) {
                        $this->setVar(array(
                            'compact_hide_block' => '',
                            'WIKI_COMPACT' => 'compact'
                        ));
                    } else {
                        $this->setVar(array(
                            'WIKI_COMPACT' => ''
                        ));

                        $this->parse('compact_hide_block', 'compact_hide');
                    }

                    $this->setVar(
                        array(
                            'modify_wiki_block' => ''
                        )
                    );

                    if (($this->contentPermission >= READ_ACCESS && $wiki['locked'] == 'false') || $this->contentPermission >= WRITE_ACCESS) {
                        $this->parse('modify_wiki_block', 'modify_wiki');
                    }

                    $this->parse('wiki_row_block', 'wiki_row', true);
                }
            }
            $this->parse('source_folder_exists_block','source_folder_exists');
        }

        

        return true; // everything went according to plan
    }
}
