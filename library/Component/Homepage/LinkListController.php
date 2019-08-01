<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_LinkListController extends Component_Homepage_ControllerAbstract
{
    /**
     * Link Mapper
     *
     * @var Schoolbox\Resource\LinkMapper
     */
    private $linkMapper;

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
        $this->linkMapper = \Schoolbox\Resource\LinkMapper::getInstance();

        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }
        
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
        if(!$this->isSourceRenderable()) {
           return $this->renderSourceUnavailableTwig($templateHandle);
        }

        $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());

        // Set up component name
        $this->setVar([
            // These are for the Select Alternate Source resourcebrowser
            'COMPONENT_CONFIG_SRC_ID'   => Scrub::text($this->getSource()->getId()),
            'COMPONENT_CONFIG_SRC_NAME' => $this->getSource()->getId() == $this->getContext()->getId()
                    ? 'Local'
                    : Scrub::text($this->getSource()->getName()),
            'LINK_CATEGORY_ID'          => Scrub::text($this->getSource()->getId()),
        ]);

        /** @var Schoolbox\Resource\Link[] $allLinks */
        $allLinks = $this->linkMapper->findAllInCategory(
            $this->getSource(), [
                'size'    => $this->getConfig('size'),
                'sort'    => $this->getConfig('sort'),
                'sortdir' => $this->getConfig('sortdir'),
            ],
            $this->getContentPermission() > UserAccess::ACCESS_READ
        );

        // Get total number of links available for the user
        $countParams = [
            'category' => $this->getSource()->getId(),
        ];
        if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
            // If readonly user, do not count hidden links
            $countParams['hidden'] = 0;
        }
        $linkCount = $this->linkMapper->countByColumnList($countParams);

        /* Need title injected here
         */
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_link', 'has_link_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        //setup block for submission button
        $this->setVar('LINK_CAT_ID', $this->getSource()->getId());

        if (count($allLinks) == 0) {

            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');

            $this->setVar([
                'has_link_block' => '',
                'empty_read_block' => '',
                'empty_write_block' => '',
                'SOURCE_ID' => Scrub::text($this->getSource()->getId()),
            ]);

            if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                $this->parse('empty_write_block', 'empty_write');
            } else {
                $this->parse('empty_read_block', 'empty_read');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            //link output
            $this->setBlock('has_link', 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock('has_link', 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('source_folder_exists', 'link', 'link_block');
            $this->setBlock('source_folder_exists', "submit_link", "submit_link_block");
            $this->setBlock('source_folder_exists', "view_all_links", "view_all_links_block");
            $this->setBlock('link', 'modify_link', 'modify_link_block');
            $this->setBlock('link', 'owner_exists', 'owner_exists_block');
            $this->setBlock('link', 'owner_deleted', 'owner_deleted_block');

            $this->setVar('empty_state_block', '');

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            } else {

                $this->setVar([
                    'source_folder_deleted_block' => '',
                    'link_block'                  => '',
                ]);

                foreach ($allLinks as $currentLink) {

                    $this->setVar([
                        'LINK_ID'           => Scrub::text($currentLink->getId()),
                        'LINK_NAME'         => Scrub::text($currentLink->getDescription()),
                        'LINK_TARGET'       => Scrub::text($currentLink->getLinkTarget()),
                        'LINK_POSTED_BY'    => Scrub::text($currentLink->getOwner()->formatName()),
                        'LINK_POSTED_BY_ID' => Scrub::text($currentLink->getOwner()->getId()),
                        'LINK_POSTED_DATE'  => Scrub::text($currentLink->getSubmitted()->getShort()),
                        'LINK_COMMENT'      => nl2br(Scrub::text($currentLink->getComment())),
                        'HIDDEN'            => $currentLink->getHidden() ? 'translucent' : '',
                    ]);

                    // Owner name and optional link
                    if ($currentLink->getOwner()->isDeleted()) {
                        $this->setVar('owner_exists_block', '');
                        $this->parse('owner_deleted_block', 'owner_deleted');
                    } else {
                        $this->parse('owner_exists_block', 'owner_exists');
                        $this->setVar('owner_deleted_block', '');
                    }

                    // Controls
                    if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                        $this->setVar('modify_link_block', '');
                    } else {
                        $this->parse('modify_link_block', 'modify_link');
                    }

                    $this->parse("link_block", "link", true);
                }

                if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                    $this->setVar("submit_link_block", "");
                } else {
                    $this->parse("submit_link_block", "submit_link");
                }

                // Show view all links button, only when total available is more than links displayed.
                if (\count($allLinks) < $linkCount) {
                    $this->parse('view_all_links_block', 'view_all_links');
                } else {
                    $this->setVar('view_all_links_block', '');
                }
                $this->parse('source_folder_exists_block', 'source_folder_exists');
            }

            $this->parse('has_link_block', 'has_link');
        }

        return true;
    }
}
