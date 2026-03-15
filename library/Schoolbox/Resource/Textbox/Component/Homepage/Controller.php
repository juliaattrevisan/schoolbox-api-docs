<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Textbox\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Comms\News\Querystring;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;
use Schoolbox\Editor\Templates\Mapper as EditorTemplateMapper;
use Schoolbox\Admin\Editor\Templates\Category\Model as EditorTemplatesCategoryModel;

/**
 * Homepage textbox component.
 */
class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * Default heading for this component.
     */
    public const DEFAULT_COMPONENT_HEADING = 'Text Box';


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
            // show empty state, only if the user can create a new text box in this folder
            // when alt-sourced and text box is not selected, just hide it until existing text box is selected
            if (!$this->hasContextWriteAccess()) {
                return false;
            }
            $this->textbox = $textboxMapper
                ->createModel()
                ->setFolder(FolderMapper::getInstance()->createModel()->setId($this->getSource()->getId()));
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
            // In condition below, deliberately compare untrimmed $candidate string.
            // The user might want to override the DEFAULT_COMPONENT_HEADING with a blank textbox heading or
            // componentname, and the way to do that is to use a string containing whitespace e.g. ' '.
            if (isset($candidate) && $candidate !== '') {
                $this->setComponentName($candidate);
                break;
            }
        }

        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param string $templateHandle
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

        $editorTemplateHelper = new EditorTemplateHelper(
            $this->getMapperFactory()->get(EditorTemplateMapper::class),
            EditorTemplatesCategoryModel::HOMEPAGE,
            $this->getUser()
        );

        return $this->renderTwigTemplate(
            $templateHandle,
            '@textbox/component/homepage.twig',
            [
                'component' => $this,
                'textbox'   => $this->textbox,
                'context'   => $this->getContext(),
                'writable'  => $this->getContentPermission() >= UserAccess::ACCESS_WRITE,
                'returnUrl' => Querystring::encode("/homepage/{$this->getContext()->getId()}"),
                'editorTemplates' => $editorTemplateHelper->getTemplatesForCategory()
            ],
            !$this->getConfig('compactMode')
        );
    }
}
