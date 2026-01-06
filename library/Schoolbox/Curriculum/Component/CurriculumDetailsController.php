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
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;

class CurriculumDetailsController extends Component_Homepage_ControllerAbstract
{
    /**
     * The curriculum to display.
     */
    protected ?GenericCollection $curriculumNodes = null;

    /**
     * Is curriculum tagging turned on
     */
    protected bool $curriculumTagging = false;

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
            $nodeMapper            = Node\Mapper::getInstance();
            $this->curriculumNodes = $nodeMapper->createCollection();

            foreach ($context->getCurriculumNodes() as $node) {
                if (!$node->getEnabled() || '' === $node->getCode()) {
                    continue;
                }
                if (empty($this->getConfig('curriculumCode')) || ($node->getCode() == $this->getConfig('curriculumCode'))) {
                    $this->curriculumNodes->add($node);
                }
            }
        } elseif ($this->getContentPermission() <= UserAccess::ACCESS_READ) {
            return false;
        }

        // if no nodes left after removing disabled node, do not render for read only user
        if ((!$this->curriculumNodes || !$this->curriculumNodes->count()) && ($this->getContentPermission() <= UserAccess::ACCESS_READ)) {
            return false;
        }

        if ($this->curriculumNodes && $this->curriculumNodes->count()) {
            Node\Mapper::sortNodes($this->curriculumNodes, 0);
        }
        return true;
    }

    /**
     * @param string $templateHandle
     *
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@curriculum/component/curriculumDetails.twig',
            [
                'context'           => $this->getContext(),
                'nodes'             => $this->curriculumNodes,
                'curriculumTagging' => $this->curriculumTagging,
            ]
        );
    }
}
