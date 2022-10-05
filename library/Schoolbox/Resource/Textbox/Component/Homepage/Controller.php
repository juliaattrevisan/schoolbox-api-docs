<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Textbox\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;

/**
 * Homepage textbox component.
 */
class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * Default heading for this component.
     */
    private const DEFAULT_COMPONENT_HEADING = 'Text Box';


    /**
     * @var \Schoolbox\Resource\Textbox\Model
     */
    private $textbox;


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
            if (!$this->hasContextWriteAccess()) {
                return false;
            }
        }

        /* See if we can find the textbox specified in the component config
         * A textbox that's deleted is treated the same as if it never existed;
         * we have to decide how to allow users to interact/manage this
         */
        $textboxMapper = TextboxMapper::getInstance();
        try {
            $this->textbox = $textboxMapper->findById($this->getConfig('textbox'));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Find the minimum access level among all these places.
             *     The current homepage
             *         this will be at least READ, except when you're a WRITE accessor
             *         looking at Student View in which case this will be READ.
             *         That's because you're looking at this from a READ user's perspective
             *     The remote location if you're alt-sourcing from there
             *         If you aren't alt-sourcing, this will be READ
             *
             * From these places, find the minimum access level and that's your
             * getContentPermission() value. It might be READ even if you're a WRITE accessor
             *
             * If it's not found
             *     and you're equated as a WRITE accessor in this homepage
             *     and you have permissions to alter its contents in the remote location,
             *        then make a placeholder component so you can get access to its ui etc.
             *
             * Otherwise, you can't do anything about it because you have READ- access
             * to the remote location, or you only have READ access to this homepage
             * (We just decided arbitrarily that READ accessors who have WRITE access in the
             * remote location don't get a chance to manage its contents)
             *
             * Hide this whole component entirely.
             */
            if (!($this->hasWriteAccess() || $this->hasContextWriteAccess())) {
                return false;
            }
            $this->textbox = $textboxMapper
                ->createModel()
                ->setCategory(FolderMapper::getInstance()->createModel()->setId($this->getSource()->getId()));
        }

        // Set the component heading. In order of preference:
        // * heading from config
        // * textbox's name
        // * self::DEFAULT_COMPONENT_HEADING
        $headingCandidates = [
            $this->getConfig('componentname'),
            $this->textbox->getHeading(),
            self::DEFAULT_COMPONENT_HEADING,
        ];
        foreach ($headingCandidates as $candidate) {
            if (isset($candidate) && $candidate !== '') {
                $this->setComponentName($candidate);
                break;
            }
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
        // If alt source is not renderable
        // or alt source is set without any text box selected, and does not have write permission to alt sourced folder
        // show the Unable to display empty state
        if (
            !$this->isSourceRenderable()
            || ($this->isUsingAltSource() && !$this->textbox->getId() && !$this->hasWriteAccess())
        ) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@textbox/component/homepage.twig',
            [
                'component' => $this,
                'textbox'   => $this->textbox,
                'context'   => $this->getContext(),
                'writable'  => $this->getContentPermission() >= UserAccess::ACCESS_WRITE,
            ],
            !$this->getConfig('compactMode')
        );
    }
}
