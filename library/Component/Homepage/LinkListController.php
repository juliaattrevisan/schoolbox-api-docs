<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_LinkListController extends Component_Homepage_ControllerAbstract
{
    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var array
     */
    private $source;

    /**
     * Prepares data for the render function. Business logic.
     * @return void
     */
    public function execute()
    {
        $this->groupHandler = new Group();

        $categoryMapper = new \Schoolbox\Resource\CategoryMapper();

        try {
            // remove any non-numeric values from the source config
            $this->source = $categoryMapper->findById(
                $this->getConfig('src') ? preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')) : $this->getContext('id')
            );
        }
        catch (\Exception $e) {
            $this->source = $categoryMapper->findById($this->getContext('id'));
        }

        $this->sortFields = array('Name', 'Date');
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'linkList')) {
            return false;
        }

        $this->parseConfig();

        if ($this->permission < READ_ACCESS) {
            return false;
        }

        // Set up component name
        $this->setVar(
            array(
                // These are for the Select Alternate Source resourcebrowser
                'COMPONENT_CONFIG_SRC_ID'   => $this->source->getId(),
                'COMPONENT_CONFIG_SRC_NAME' => $this->source->getId() == $this->getContext('id') ? 'Local' : $this->source->getName(),
                'LINK_CATEGORY_ID'          => $this->source->getId(),
                'COMPONENT_NAME'            => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'News',
            )
        );

        //link output
        $this->setBlock($templateHandle, 'link', 'link_block');
        $this->setBlock('link', 'modify_link', 'modify_link_block');


        $linkMapper = new \Schoolbox\LinkMapper();
        $linkList = $linkMapper->findAllInCategory(
            $this->source, array(
                'size'    => $this->getConfig('size'),
                'sort'    => $this->getConfig('sort'),
                'sortdir' => $this->getConfig('sortdir'),
            )
        );

        $this->setVar('link_block', '');

        $j = 0;

        foreach ($linkList as $link) {
            $url = new Url($link->getUrl());

            if ($url->isLocalUrl()) {
                $linkUrl    = $url->getRelativeURL();
                $linkTarget = '';
            } else {
                $linkUrl    = $url->getBase64EncodedRedirect();
                $linkTarget = 'external';
            }

            $output = array(
                'LINK_ID'           => $link->getId(),
                'LINK_NAME'         => Scrub::text($link->getDescription()),
                'LINK_URL'          => $linkUrl,
                'LINK_TARGET'       => $linkTarget,
                'LINK_POSTED_BY'    => $link->getOwnerId()->formatName(),
                'LINK_POSTED_BY_ID' => $link->getOwnerId()->getId(),
                'LINK_POSTED_DATE'  => $link->getSubmittedAt()->getShort(),
                'LINK_COMMENT'      => nl2br(Scrub::text($link->getComment())),
                'ON_OFF'            => ++$j % 2 ? '' : 'dark',
                'HIDDEN'            => $link->getHidden() ? 'hidden' : '',
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

        //setup block for submission button
        $this->setVar('LINK_CAT_ID', $this->source->getId());
        $this->setBlock($templateHandle, "submit_link", "submit_link_block");

        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_link_block", "");
        } else {
            $this->parse("submit_link_block", "submit_link");
        }

        return true;
    }
}
