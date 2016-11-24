<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Fixtures\Component\Dashboard;

use Alaress\Session\Session;
use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Fixtures\Component\ComponentDelegate as ComponentDelegate;
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
     * @param User $user
     * @param SitePage $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);
        $this->setDelegate(new ComponentDelegate());
    }


    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $playerIds = [];
        $getHistorical = false;

        // Grab the relevant data depending on role type

        if ($this->getUser()->getRole()->isParent()) {
            /** @var Session $session */
            $session  = $this->getSitePage()->getDi()->get('session');
            $children = $session->get('children') ?: [];
            foreach ($children as $child) {
                $playerIds[] = $child['id'];
            }
        } else {
            if ($this->getUser()->getRole()->isStudent()) {
                $playerIds[] = $this->getUser()->getId();
            }
        }

        $matches = $this->getDelegate()->getMatchesForUsers($playerIds, $getHistorical);

        if (0 == count($matches)) {
            return false;
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@sportsfixturesDashboard/component.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'componentClass'      => $this->getComponentName(),
                'user'                => $this->getUser(),
                'matches'             => $matches,
            ]
        );
    }

    /*
     * Getters / Setters
     */

    /**
     * @return ComponentDelegate
     */
    protected function getDelegate()
    {
        return $this->delegate;
    }

    /**
     * @param ComponentDelegate $delegate
     * @return static
     */
    protected function setDelegate(ComponentDelegate $delegate)
    {
        $this->delegate = $delegate;
        return $this;
    }

}