<?php

namespace Schoolbox\Learning\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Permission\Access\UserAccess;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class LearningMomentsController extends Component_Homepage_ControllerAbstract
{
    /**
     * Does the user have access to this component?
     * @var bool
     */
    private bool $readOnly;

    /**
     * Business logic to collate data ready for output
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute(): bool
    {
        if (!parent::execute()) {
            return false;
        }

        // Only show if learning moments are enabled.
        if (!$this->getConfigLoader()->get('learning_moments_enabled')) {
            return false;
        }

        // Parents cannot see the Learning Moments component.
        if ($this->getUser()->getRole()->isParent()) {
            return false;
        }

        $this->readOnly = $this->getContentPermission() < UserAccess::ACCESS_WRITE;

        return true;
    }

    /**
     * Output the data we collected in execute()
     *
     * @param String $templateHandle
     * @return bool
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@learning/component/learningMomentsHomepage.twig',
            [
                'user' => $this->getUser(),
                'baseUrl' => '/learning/evidence/user/' . $this->getUser()->getId() . '/create',
                'component' => $this,
                'readOnly' => $this->readOnly,
                'folder' => $this->getSource(),
            ]
        );
    }
}
