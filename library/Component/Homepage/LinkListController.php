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
     * Link Mapper
     *
     * @var Schoolbox\Resource\LinkMapper
     */
    private $linkMapper = null;

    /**
     * Available sort fields for this component.
     *
     * @var array
     */
    protected $sortFields = array('Name', 'Date');

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }
        
        $this->setLinkMapper(new \Schoolbox\Resource\LinkMapper());
        return true;
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

        $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());

        // Set up component name
        $this->setVar(
            array(
                // These are for the Select Alternate Source resourcebrowser
                'COMPONENT_CONFIG_SRC_ID'   => $this->getSource()->getId(),
                'COMPONENT_CONFIG_SRC_NAME' => $this->getSource()->getId() == $this->getContext()->getId()
                        ? 'Local'
                        : $this->getSource()->getName(),
                'LINK_CATEGORY_ID'          => $this->getSource()->getId(),
            )
        );

        /* Need title injected here
         */
        $this->parseConfig();

        //link output
        $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
        $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
        $this->setBlock('source_folder_exists', 'link', 'link_block');
        $this->setBlock('source_folder_exists', "submit_link", "submit_link_block");
        $this->setBlock('link', 'modify_link', 'modify_link_block');

        if(!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailable();
        } else {
            $this->setVar('source_folder_deleted_block', '');

            /** @var Schoolbox\Resource\Link[] $allLinks */
            $allLinks = $this->getLinkMapper()->findAllInCategory(
                $this->getSource(),
                array(
                    'size' => $this->getConfig('size'),
                    'sort' => $this->getConfig('sort'),
                    'sortdir' => $this->getConfig('sortdir'),
                )
            );

            $this->setVar('link_block', '');

            $j = 0;

            foreach ($allLinks as $currentLink) {
                if ($currentLink->getHidden() && $this->contentPermission < WRITE_ACCESS) {
                    continue;
                }
                $this->setVar(
                    array(
                        'LINK_ID' => $currentLink->getId(),
                        'LINK_NAME' => Scrub::text($currentLink->getDescription()),
                        'LINK_URL' => $currentLink->getHref(),
                        'LINK_TARGET' => $currentLink->getLinkTarget(),
                        'LINK_POSTED_BY' => $currentLink->getOwner()->formatName(),
                        'LINK_POSTED_BY_ID' => $currentLink->getOwner()->getId(),
                        'LINK_POSTED_DATE' => $currentLink->getSubmitted()->getShort(),
                        'LINK_COMMENT' => nl2br(Scrub::text($currentLink->getComment())),
                        'HIDDEN' => $currentLink->getHidden() ? 'hidden' : '',
                    )
                );

                // Controls
                if ($this->contentPermission < WRITE_ACCESS) {
                    $this->setVar('modify_link_block', '');
                } else {
                    $this->parse('modify_link_block', 'modify_link');
                }

                $this->parse("link_block", "link", true);
            }

            //setup block for submission button
            $this->setVar('LINK_CAT_ID', $this->source->getId());


            if ($this->contentPermission < WRITE_ACCESS) {
                $this->setVar("submit_link_block", "");
            } else {
                $this->parse("submit_link_block", "submit_link");
            }
            $this->parse('source_folder_exists_block','source_folder_exists');
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
}
