<?php

namespace Schoolbox\Learning\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Config\ConfigMapper;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class LearningMomentsController extends Component_Homepage_ControllerAbstract
{
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
        if (!$this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_ENABLED)) {
            return false;
        }

        // Do not show to parents.
        if ($this->getUser()->getRole()->isParent()) {
            return false;
        }

        // Only show to students if learning moments are enabled for the student's year level.
        if ($this->getUser()->getRole()->isStudent() && !$this->getUser()->getYear()?->getLearningMomentsEnabled()) {
            return false;
        }

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
    public function renderTemplate($templateHandle): bool
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@learning/component/learningMomentsHomepage.twig',
            [
                'user' => $this->getUser(),
                'component' => $this,
                'folder' => $this->getSource(),
            ]
        );
    }
}
