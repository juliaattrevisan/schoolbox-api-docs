<?php
/**
 * Curriculum information component
 *
 * Displays the full details of a single curriculum item, without any children.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Component;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Curriculum\Node;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\Config\Loader as ConfigLoader;

class CurriculumDetailsController extends Component_Homepage_ControllerAbstract
{
    /**
     * The curriculum to display.
     *
     * @var Node\Model
     */
    protected $curriculumNode = null;

    /**
     * Is curriculum tagging turned on
     *
     * @var bool
     */
    protected $curriculumTagging = null;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable() && !$this->hasContextWriteAccess()) {
            return false;
        }

        /**
         * Check if curriculum tagging has been turnt on
         */
        $this->curriculumTagging = $this->getConfigLoader()->get('curriculum_tagging_enabled');

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
                'context'  => $this->getContext(),
                'node'     => $this->curriculumNode,
                'readonly' => ($this->getContentPermission() <= UserAccess::ACCESS_READ),
                'curriculumTagging' => $this->curriculumTagging,
            )
        );
    }
}
