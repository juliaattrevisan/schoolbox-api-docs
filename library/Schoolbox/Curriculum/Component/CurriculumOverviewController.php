<?php

/**
 * Curriculum information component
 *
 * Displays an overview of a single curriculum item, including its children but
 * not including its description.
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

class CurriculumOverviewController extends Component_Homepage_ControllerAbstract
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
     * Loads the component's configuration.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable()) {
            return $this->hasContextWriteAccess();
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
                if (!$node->getEnabled()) {
                    continue;
                }

                // Load this node's descendants and ancestors
                $nodeMapper->loadAncestors($node);
                $nodeMapper->loadDescendants($node);
                $this->curriculumNodes->add($node);
            }

            // make sure that the node or node's ancestors do not already in the list
            // to avoid same hierarchy appears twice in the component
            $selectedNodeIds = $this->curriculumNodes->getPropertyValues('id');
            foreach ($this->curriculumNodes as $offset => $node) {
                $nodeMapper->loadAncestors($node);
                $ancestorIds = $this->getAllAncestorIds($node);
                $diff        = array_diff($ancestorIds, $selectedNodeIds);
                if (count($diff) !== count($ancestorIds)) {
                    $this->curriculumNodes->offsetUnset($offset);
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
     * @inheritDoc
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@curriculum/component/curriculumOverview.twig',
            [
                'context'           => $this->getContext(),
                'nodes'             => $this->curriculumNodes,
                'curriculumTagging' => $this->curriculumTagging,
            ]
        );
    }

    /**
     * Find all ancestor ids to remove duplicates
     * @param Node\Model $node
     * @return array
     */
    private function getAllAncestorIds(Node\Model $node): array
    {
        $parentIds = [];
        if ($node->getParent() instanceof Node\Model) {
            $parentIds   = $this->getAllAncestorIds($node->getParent());
            $parentIds[] = $node->getParent()->getId();
        }
        return $parentIds;
    }
}
