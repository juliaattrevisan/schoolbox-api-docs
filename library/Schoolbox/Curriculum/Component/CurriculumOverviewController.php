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
     * The curriculum to display in this component, compiled into a tree
     * structure which contains the entire curriculum minus any nodes which are
     * neither ancestors nor descendants of this folder's nodes.
     *
     * @var GenericCollection<Node\Model>|null
     */
    protected ?GenericCollection $curriculumNodes = null;

    /**
     * The nodes selected to display within this component: when rendered, any
     * nodes outside of these will be excluded.
     *
     * @var GenericCollection<Node\Model>|null
     */
    protected ?GenericCollection $selectedNodes = null;

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

        $nodeMapper = Node\Mapper::getInstance();

        // Show first curriculum for now
        /** @var Folder\Model $context */
        $context = $this->getContext();
        if ($context->getCurriculumNodes()->count()) {
            $nodeIds = [];
            foreach ($context->getCurriculumNodes() as $node) {
                if (!$node->getEnabled()) {
                    continue;
                }

                $nodeIds[] = $node->getId();
            }

            // Fetch every node which is an ancestor or descendant of one of
            // this folder's nodes, and is still enabled:
            // only enabled nodes should be selectable.
            $nodeIds = $nodeMapper->findFamilyTreeIds($nodeIds, 'curriculum_node_parent_id');

            // Then, sort the entire list of nodes into a tree structure.
            $this->curriculumNodes = $nodeMapper->getCollectionAsTree(
                $nodeMapper->findByColumnList(['id' => $nodeIds, 'enabled' => true,]),
            );
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

        $this->selectedNodes = $this->getContext()->getCurriculumNodes()->reindex();

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
                'selectedNodes'     => $this->selectedNodes,
                'curriculumTagging' => $this->curriculumTagging,
                'readonly' => !$this->getAuthorizer()->isPathAuthorizedForUser(
                    'curriculum.edit',
                    [],
                    $this->getUser()
                ),
            ]
        );
    }
}
