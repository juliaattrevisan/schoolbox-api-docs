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
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\Config\Loader as ConfigLoader;

class CurriculumOverviewController extends Component_Homepage_ControllerAbstract
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
     * Loads the component's configuration.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }

        /**
         * Check if curriculum tagging has been turnt on
         */
        // TODO use config loader that exists in components already?
        $config = new ConfigLoader();
        $this->curriculumTagging = $config->get('curriculum_tagging_enabled');

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

        // Load child nodes of the node model.
        if ($this->curriculumNode instanceof Node\Model) {
            Node\Mapper::getInstance()->loadDescendants($this->curriculumNode);
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
            '@curriculum/component/curriculumOverview.twig',
            array(
                'context'  => $this->getContext(),
                'node'     => $this->curriculumNode,
                'readonly' => ($this->getContentPermission() <= UserAccess::ACCESS_READ),
                'curriculumTagging' => $this->curriculumTagging,
            )
        );
    }
}
