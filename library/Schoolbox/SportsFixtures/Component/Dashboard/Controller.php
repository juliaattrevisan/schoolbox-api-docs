<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Component\Dashboard;

use Alaress\Session\Session;
use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\SportsFixtures\Component\ComponentDelegate as ComponentDelegate;
use Schoolbox\User;
use SitePage;

class Controller extends Component_Dashboard_ControllerAbstract
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
        // Grab the relevant data depending on role type

        if ($this->user->getRole()->isParent()) {
            $data = $this->delegate->getParentDashboardData();
        } else if ($this->user->getRole()->isStudent()) {
            $data = $this->delegate->getStudentDashboardData($this->user);
        } else {
            // Unsupported role type, don't render the component
            return false;
        }

        // If the child wasn't involved in any matches don't render the component

        if (count($data["matches"]) == 0) {
            return false;
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@sportsfixturesDashboard/component.twig',
            array_merge($data, [
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName()
            ])
        );
    }
}