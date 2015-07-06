<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_LinkListController extends Component_Homepage_ControllerAbstract
{

    /**
     * Component name
     *
     * @var string
     */
    protected $componentName = 'Links';

    /**
     * Group handler
     *
     * @var Group
     */
    private $groupHandler;

    /**
     * Link Mapper
     *
     * @var Schoolbox\Resource\LinkMapper
     */
    private $linkMapper = null;

    /**
     * An instance of Category
     *
     * @var \Schoolbox\Resource\Category
     */
    private $source;

    /**
     * Category Mapper
     *
     * @var \Schoolbox\Resource\CategoryMapper
     */
    private $categoryMapper = null;

    /**
     * Available sort fields for this component.
     *
     * @var array
     */
    protected $sortFields = array('Name', 'Date');

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->groupHandler = new Group();
        $this->setLinkMapper(new \Schoolbox\Resource\LinkMapper());
        $this->setCategoryMapper(new \Schoolbox\Resource\CategoryMapper());

        $this->source = $this->getContext();

        if (is_numeric($this->getConfig('src'))) {
            try {
                // remove any non-numeric values from the source config
                $this->source = $this->getCategoryMapper()->findById(preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')));
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     *
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
                'COMPONENT_CONFIG_SRC_NAME' => $this->source->getId() == $this->getContext()->getId()
                        ? 'Local'
                        : $this->source->getName(),
                'LINK_CATEGORY_ID'          => $this->source->getId(),
                'COMPONENT_NAME'            => strlen(trim($this->getConfig('componentname')))
                        ? $this->getConfig('componentname')
                        : $this->getComponentName(),
            )
        );

        //link output
        $this->setBlock($templateHandle, 'link', 'link_block');
        $this->setBlock('link', 'modify_link', 'modify_link_block');

        /** @var Schoolbox\Resource\Link[] $allLinks */
        $allLinks = $this->getLinkMapper()->findAllInCategory(
            $this->source,
            array(
                'size'    => $this->getConfig('size'),
                'sort'    => $this->getConfig('sort'),
                'sortdir' => $this->getConfig('sortdir'),
            )
        );

        $this->setVar('link_block', '');

        $j = 0;

        foreach ($allLinks as $currentLink) {
            if ($currentLink->getHidden() && $this->permission < WRITE_ACCESS) {
                continue;
            }
            $this->setVar(
                array(
                    'LINK_ID'           => $currentLink->getId(),
                    'LINK_NAME'         => Scrub::text($currentLink->getDescription()),
                    'LINK_URL'          => $currentLink->getHref(),
                    'LINK_TARGET'       => $currentLink->getTarget(),
                    'LINK_POSTED_BY'    => $currentLink->getOwner()->formatName(),
                    'LINK_POSTED_BY_ID' => $currentLink->getOwner()->getId(),
                    'LINK_POSTED_DATE'  => $currentLink->getSubmitted()->getShort(),
                    'LINK_COMMENT'      => nl2br(Scrub::text($currentLink->getComment())),
                    'ON_OFF'            => ++$j % 2 ? '' : 'dark',
                    'HIDDEN'            => $currentLink->getHidden() ? 'hidden' : '',
                )
            );

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

    /* Getters/Setters
     */

    /**
     * Set link mapper
     *
     * @param \Schoolbox\Resource\LinkMapper $linkMapper
     */
    private function setLinkMapper($linkMapper)
    {
        $this->linkMapper = $linkMapper;
    }

    /**
     * Get link mapper
     *
     * @return \Schoolbox\Resource\LinkMapper
     */
    private function getLinkMapper()
    {
        return $this->linkMapper;
    }

    /**
     * Set category mapper
     *
     * @param \Schoolbox\Resource\CategoryMapper $categoryMapper
     */
    private function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Get category mapper
     *
     * @return \Schoolbox\Resource\CategoryMapper
     */
    private function getCategoryMapper()
    {
        return $this->categoryMapper;
    }
}
