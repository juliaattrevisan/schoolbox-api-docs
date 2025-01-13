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
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;

class CurriculumOverviewController extends Component_Homepage_ControllerAbstract
{
    /**
     * Default component name as specified in component_config_definition
     */
    const DEFAULT_COMPONENT_HEADING = 'Curriculum Overview';
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
     * The selected curriculum tree filter node. The node that will be used to
     * to filter the component to display only the nodes that are descendants of
     * this node.
     *
     * @var Node\Model|null
     */
    protected ?Node\Model $curriculumTreeFilter = null;

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
            // Note this logic is duplicated in Curriculum/Api/Usage/Folder/Controller::buildTreeResponse

            $directNodeIds = $context->getCurriculumNodes()->getPropertyValues('id');

            // Fetch every node which is an ancestor or descendant of one of this folder's nodes, whether enabled or disabled.
            // A previously set curriculum node should remain on a folder even if the node has subsequently been disabled.
            $nodeIds = $nodeMapper->findFamilyTreeIds($directNodeIds, 'curriculum_node_parent_id', 50, 0);

            // Then find all the nodes and filter out disabled nodes that aren't directly mapped to this folder
            // This is because we still want to show disabled nodes if they are still directly mapped to aid in transitioning curriculum
            $curriculumNodes = $nodeMapper->findByColumnList(['id' => $nodeIds])->filter(
                fn ($node) => $node->getEnabled() || in_array($node->getId(), $directNodeIds)
            );

            // Then, sort the entire list of nodes into a tree structure.
            Node\Mapper::sortNodes($curriculumNodes, 0);
            $this->curriculumNodes = $nodeMapper->getCollectionAsTree($curriculumNodes);
        } elseif ($this->getContentPermission() <= UserAccess::ACCESS_READ) {
            return false;
        }

        // if no nodes left after removing disabled node, do not render for read only user
        if ((!$this->curriculumNodes || !$this->curriculumNodes->count()) && ($this->getContentPermission() <= UserAccess::ACCESS_READ)) {
            return false;
        }

        // This should be unnecessary but PHPStan complains otherwise
        /** @var GenericCollection<Node\Model> $selectedNodes */
        $selectedNodes       = $this->getContext()->getCurriculumNodes()->reindex();
        $this->selectedNodes = $selectedNodes;

        // Load curriculum tree filter node
        $curriculumTreeFilterConfig = $this->getConfig('curriculumTreeFilter');
        if ($curriculumTreeFilterConfig) {
            try {
                $this->curriculumTreeFilter = $nodeMapper->findById($curriculumTreeFilterConfig);
            } catch (Exception) {
                // Node not found or deleted?
                // Just act as if nothing were selected.
            }
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
                'context' => $this->getContext(),
                'componentInstanceId' => $this->getInstance()->getId(),
                'nodes' => $this->curriculumNodes,
                'selectedNodes' => $this->selectedNodes,
                'curriculumTreeFilter' => $this->curriculumTreeFilter,
                'curriculumTagging' => $this->curriculumTagging,
                'readonly' => !$this->hasContextWriteAccess(),
            ]
        );
    }
}
