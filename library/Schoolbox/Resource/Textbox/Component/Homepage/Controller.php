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

        $textboxMapper = TextboxMapper::getInstance();
        try {
            $this->textbox = $textboxMapper->findById($this->getConfig('textbox'));
        } catch (\Schoolbox\Mapper\Exception $e) {
            // didn't find it or it's been deleted: hide from read only user or create a blank template box instead
            if (!$this->hasContextWriteAccess()) {
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
        if (!$this->isSourceRenderable()) {
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
