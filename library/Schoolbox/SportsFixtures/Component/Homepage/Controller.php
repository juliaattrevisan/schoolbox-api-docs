<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\SportsFixtures\Component\ComponentDelegate as ComponentDelegate;
use Schoolbox\User;
use SitePage;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * @var ComponentDelegate
     */
    private $delegate;

    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);
        $this->delegate = new ComponentDelegate($sitepage);
    }

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        return true;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Grab data for the team we are viewing
        
        $data = $this->delegate->getTeamData($this->config["team"]["value"]);

        return $this->renderTwigTemplate(
            $templateHandle,
            '@sportsfixturesHomepage/component.twig',
            array_merge($data, [
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName()
            ])
        );
    }
}