<?php
/**
 * Grid component
 *
 * Uses Twig for output instead of the PHPLib suckfest.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Component_Homepage_GridController extends Component_Homepage_ControllerAbstract
{
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
        if (!$this->loadTemplate($templateHandle, 'grid')) {
            return false;
        }

        // There should at least be a sitepage I can get a container out of!
        // And that container always ought to be there!
        $di      = $this->sitepage->getDi();
        $content = '';
        if ($di instanceof Aura\Di\ContainerInterface) {
            $content = $di->get('templateEngine')->render(
                'component/homepage/grid.twig',
                array('component' => $this,)
            );
        }
        $this->setVar('COMPONENT_CONTENT', $content);
        return true;

    }

}
