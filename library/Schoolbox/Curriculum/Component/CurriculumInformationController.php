<?php
/**
 * Curriculum information component
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\Component;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Curriculum\Node;
use Schoolbox\Resource\Folder;

class CurriculumInformationController extends Component_Homepage_ControllerAbstract
{
    /**
     * The curriculum to display.
     *
     * @var Node\Model
     */
    protected $curriculumNode = null;

    /**
     * Loads the component's configuration.
     *
     * @return true
     */
    public function execute()
    {
        // Show first curriculum for now
        /** @var Folder\Model $context */
        $context = $this->getContext();
        if ($context->getCurriculumNodes()->count()) {
            $this->curriculumNode = $context->getCurriculumNodes()->first();
        } elseif (!$this->hasReadAccess()) {
            return false;
        }
        return true;
    }

    /**
     * @param String $templateHandle
     *
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@curriculum/component/curriculumInformation.twig'
        );
    }

    /**
     * @return Node\Model
     */
    public function getCurriculumNode()
    {
        return $this->curriculumNode;
    }
}
