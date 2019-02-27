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
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Tile\Layout;
use Twig_Environment;

class TileControllerDelegate
{

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
     * @return Layout\Model|null Null if the layout no longer exists.
     *                           (If no layout ID is defined, an empty one is
     *                            returned)
     */
    public function fetchLayout(Component_ControllerAbstract $controller): ?Layout\Model
    {
        $layoutId = $controller->getConfig('layoutId');

        // empty layout by default
        $mapper = Layout\Mapper::getInstance();
        $layout = $mapper->createModel();
        if (!empty($layoutId)) {
            try {
                // Load the layout if it's there
                // This load should include each tile.
                $layout = $mapper->findById(
                    $layoutId,
                    ['joins' => ['tile'],]
                );
            } catch (MapperException $e) {
                return null;
            }
        }
        return $layout;
    }
}
