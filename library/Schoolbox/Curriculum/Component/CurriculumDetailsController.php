<?php
/**
 * Curriculum information component
 *
 * Displays the full details of a single curriculum item, without any children.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\Component;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Curriculum\Node;
use Schoolbox\Resource\Folder;

class CurriculumDetailsController extends Component_Homepage_ControllerAbstract
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
     * @return bool
     */
    public function execute()
    {
        // Show first curriculum for now
        /** @var Folder\Model $context */
        $context = $this->getContext();
        if ($context->getCurriculumNodes()->count()) {
            // No curriculum item selected? just use the first.
            $this->curriculumNode = $context->getCurriculumNodes()->first();
            foreach ($context->getCurriculumNodes() as $node) {
                if ($node->getCode() == $this->getConfig('curriculumCode')) {
                    $this->curriculumNode = $node;
                    break;
                }
            }
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
            '@curriculum/component/curriculumDetails.twig',
            array(
                'context' => $this->getContext(),
                'node'    => $this->curriculumNode,
            )
        );
    }
}
