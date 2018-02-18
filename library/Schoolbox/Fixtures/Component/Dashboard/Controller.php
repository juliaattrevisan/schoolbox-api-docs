<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Fixtures\Component\Dashboard;

use Alaress\Database\Connector\Base;
use Alaress\Session\Session;
use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Fixtures\Component\ComponentDelegate;
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

        /** @var Base $databaseInternal */
        $databaseInternal = $sitepage->getDi()->get('databaseInternal');
        $this->setDelegate(new ComponentDelegate($databaseInternal));
    }

    /**
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        return $this->getSitePage()->getDi()->get('config')->get('fixtures_enabled') == '1';
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $matches = new Collection();
        $getHistorical = false;

        // Grab the relevant data depending on role type

        if ($this->getUser()->getRole()->isParent()) {
            /** @var Session $session */
            $session  = $this->getSitePage()->getDi()->get('session');
            $children = $session->get('children') ?: [];
            $userIds = [$this->getUser()->getId()];
            foreach ($children as $child) {
                $userIds[] = $child['id'];
            }
            $matches = $matches->mergeUnique($this->getDelegate()->getMatchesForUsers($userIds, $getHistorical),'id');
        } else {
            $matches = $this->getDelegate()->getMatchesForUsers([$this->getUser()->getId()], $getHistorical);
        }

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