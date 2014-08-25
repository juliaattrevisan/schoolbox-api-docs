<?php
/**
 * Tile component
 *
 * Uses Twig for output instead of the PHPLib suckfest.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Tile\Layout;
use Schoolbox\Mapper\Exception as MapperException;

class Component_Homepage_TileController extends Component_Homepage_ControllerAbstract
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
     * @var array[Collection|Booking\Model[]]
     */
    protected $bookings;

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        // configuration:
        // gridId
        // tileLayout:
        //  don't quite understand this
        // tileShape
        //  landscape?
        $layoutId = $this->getConfig('layout');

        // empty layout by default
        $this->layout = new Layout\Model();
        if (!empty($layoutId)) {
            try {
                // Load the layout if it's there
                // This load should include each tile.
                $this->layout = Layout\Mapper::getInstance()->findById(
                    $this->getConfig($layoutId),
                    array(
                        'joins' => array('tile'),
                    )
                );
            } catch (MapperException $e) {
                // if it wasn't, use the empty layout
            }
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
        // Don't mess about with blocks, shove this into Twig
        if (!$this->loadTemplate($templateHandle, 'tile')) {
            return false;
        }
        $this->parseConfig();

        // There should at least be a sitepage I can get a container out of!
        // And that container always ought to be there!
        // It won't be though, if this page wasn't loaded through
        // Schoolbox\Application\WebRequest\Kernel.
        $content = '';
        try {
            $content = $this->sitepage->getTwig()->render(
                'component/homepage/tile.twig',
                array('component' => $this,)
            );
        } catch (Exception $e) {
            // Don't update content
        }
        $this->setVar('COMPONENT_CONTENT', $content);
        return true;
    }
}
