<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_LinkListController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var Category
     */
    private $catHandler;

    /**
     * @var Link
     */
    private $linkHandler;

    /**
     * @var array
     */
    private $source;


    /**
     * Prepares data for the render function. Business logic.
     * @return void
     */
    public function execute() {
        $this->groupHandler = new Group();
        $this->catHandler = new Category();
        $this->linkHandler = new Link();

        // remove any non-numeric values from the source config
        $this->source = $this->catHandler->getByID(
            $this->getConfig('src') ?
                preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')) :
                $this->getContext('id')
        );

        $this->sortFields = array('Name', 'Date');
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'linkList')) {
            return false;
        }

        $this->parseConfig();

        // Set up component name
        $this->setVar(
            array(
                // These are for the Select Alternate Source resourcebrowser
                'COMPONENT_CONFIG_SRC_ID' => $this->source['id'],
                'COMPONENT_CONFIG_SRC_NAME' => $this->source['id'] == $this->getContext(
                    'id'
                ) ? 'Local' : $this->source['name'],
                'LINK_CATEGORY_ID' => $this->source['id'],
                'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig(
                    'componentname'
                ) : 'News',
            )
        );

        //link output
        $this->setBlock($templateHandle, 'link', 'link_block');
        $this->setBlock('link', 'modify_link', 'modify_link_block');

        $linkList = $this->linkHandler->getAllInCategory(
            $this->source['id'], array(
	                                    'size' => $this->getConfig('size'),
                                        'sort'  => $this->getConfig('sort'),
                                        'sortdir' => $this->getConfig('sortdir'),
                                       )
        );

        $this->setVar('link_block', '');

        if (is_array($linkList)) {
            $userHandler = new User();

            $j = 0;

            foreach ($linkList as $link) {
                $url = new Url($link['url']);

                if ($url->isLocalUrl()) {
                    $linkUrl = $url->getRelativeURL();
                    $linkTarget = '';
                } else {
                    $linkUrl = $url->getBase64EncodedRedirect();
                    $linkTarget = 'external';
                }

                $date = new Alaress_Date_Format($link['submitted'], 'U');

                $output = array(
                    'LINK_ID' => $link['id'],
                    'LINK_NAME' => Scrub::text($link['description']),
                    'LINK_URL' => $linkUrl,
                    'LINK_TARGET' => $linkTarget,
                    'LINK_POSTED_BY' => $userHandler->formatName($link),
                    'LINK_POSTED_BY_ID' => $link['owner'],
                    'LINK_POSTED_DATE' => $date->getShort(),
                    'LINK_COMMENT' => nl2br(Scrub::text($link['comment'])),
                    'ON_OFF' => ++$j % 2 ? '' : 'dark',
                    'HIDDEN' => $link['hidden'] ? 'hidden' : '',
                );

                $this->setVar($output);

                // Controls
                if ($this->permission < WRITE_ACCESS) {
                    $this->setVar('modify_link_block', '');
                } else {
                    $this->parse('modify_link_block', 'modify_link');
                }

                $this->parse("link_block", "link", true);
            }
        } else {
            $this->setVar("link_block", "");
        }

        //setup block for submission button
        $this->setVar('LINK_CAT_ID', $this->source['id']);
        $this->setBlock($templateHandle, "submit_link", "submit_link_block");

        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_link_block", "");
        } else {
            $this->parse("submit_link_block", "submit_link");
        }

        return true;

    }


}
