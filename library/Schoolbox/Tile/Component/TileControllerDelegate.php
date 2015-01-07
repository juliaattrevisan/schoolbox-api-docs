<?php
/**
 * Delegate class for logic that is common to both the dashboard and homepage
 * tile components.
 *
 * This would be a trait if Schoolbox was on PHP 5.4+, but it isn't yet so what
 * can I do?
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tile\Component;

use Component_ControllerAbstract;
use Group as GroupHandler;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Tile\Layout;
use Schoolbox\Mapper\Exception as MapperException;
use SitePage;
use Twig_Environment;

class TileControllerDelegate
{
    /**
     * Deprecated group handler, used for fetching user's permissions
     *
     * @var GroupHandler
     */
    protected $groupHandler;

    /**
     * @var Layout\Model
     */
    protected $layout;

    /**
     * @var Twig_Environment
     */
    protected $templateEngine;

    /**
     * Loads the grid component into its set configuration.
     *
     * @param Component_ControllerAbstract $controller
     *
     * @return Layout\Model
     */
    public function fetchLayout(Component_ControllerAbstract $controller)
    {
        // layoutId: the layout that was created when this component was
        // tileSource: the layout chosen for an alternate source
        $altSource = $controller->getConfig('tileSource');
        $layoutId  = $controller->getConfig('layoutId');
        if ($altSource) {
            // Get this component, and get its layout ID
            $altComponents = \Component_InstanceMapper::getInstance()->findByInstanceId($altSource);
            if (isset($altComponents[$altSource])) {
                $altComponent = $altComponents[$altSource];
                $altConfig    = $altComponent->getConfigArray();
                $layoutId     = $altConfig['layoutId']['value'];
            }
        }

        // empty layout by default
        $mapper = Layout\Mapper::getInstance();
        $layout = $mapper->createModel();
        if (!empty($layoutId)) {
            try {
                // Load the layout if it's there
                // This load should include each tile.
                $layout = $mapper->findById(
                    $layoutId,
                    array('joins' => array('tile'),)
                );
            } catch (MapperException $e) {
                // if it wasn't, use the empty layout
            }
        }
        return $layout;
    }

    /**
     * Sets up PHPLib blocks for the component.
     *
     * @param Component_ControllerAbstract $controller
     * @param string                       $templateHandle
     * @return $this
     */
    public function setupBlocks(Component_ControllerAbstract $controller, $templateHandle)
    {
        // There should at least be a sitepage I can get a container out of!
        // And that container always ought to be there!
        // It won't be though, if this page wasn't loaded through
        // Schoolbox\Application\WebRequest\Kernel.
        // Therefore I have to do this PHPLib/Twig hybrid thing.
        $controller->setBlock($templateHandle, 'header', 'header_block');
        $controller->setBlock($templateHandle, 'config', 'config_block');
        $controller->setBlock($templateHandle, 'buttons', 'buttons_block');
        return $this;
    }

    /**
     * Parses blocks for this controller.
     *
     * @param Component_ControllerAbstract $controller The controller to parse
     * @param string                       $content    Content fetched from getTwigContent()
     * @param int                          $permission One of the UserAccess::ACCESS_* constants
     * @return $this
     */
    public function parseBlocks(Component_ControllerAbstract $controller, $content, $permission)
    {
        $controller->setVar(
            array(
                'header_block'               => '',
                'buttons_block'              => '',
                'COMPONENT_CONTENT'          => $content,
                'TILE_COMPONENT_INSTANCE_ID' => $controller->getConfig('tileSource')
                    ?: $controller->getInstance()->getId(),
            )
        );
        if ($permission >= UserAccess::ACCESS_WRITE) {
            $controller->parse('header_block', 'header');
            $controller->parse('buttons_block', 'buttons');
        }
        return $this;
    }

    /**
     * Fetches content out of the Twig template
     *
     * @param Component_ControllerAbstract $controller The controller to parse
     * @param SitePage                     $sitepage   The Sitepage to get Twig out of
     * @return string
     */
    public function fetchTwigContent(Component_ControllerAbstract $controller, SitePage $sitepage)
    {
        $content = '';
        try {
            $content = $sitepage->getTwig()->render(
                '@tile/component/tile.twig',
                array('component' => $controller,)
            );
        } catch (\Exception $e) {
            // Don't show any content
        }
        return $content;
    }
}
