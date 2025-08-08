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
     * Does the user have edit access to this component / page
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
            ]
        );
    }
}
