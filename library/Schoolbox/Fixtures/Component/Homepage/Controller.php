<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Fixtures\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Fixtures\Component\ComponentDelegate as ComponentDelegate;
use Schoolbox\User;
use \Schoolbox\Permission\Access\UserAccess;
use SitePage;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * @var ComponentDelegate
     */
    private $delegate;

    /**
     * @var bool
     */
    private $readOnly;

    /**
     * @var bool
     */
    private $fixtureAdmin;

    /**
     * Whether a team has been set in component config
     * @var bool
     */
    private $noConfig;

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
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        /*
         * Check access to this page
         */
        $this->readOnly = $this->getContentPermission() < UserAccess::ACCESS_WRITE;

        /**
         * Check if user is a fixture admin
         */
        $this->fixtureAdmin = (UserAccess::SERVICE_SPORTS_ADMINISTRATOR) ? true : false;

        /**
         * Check if a team has been set in the component config
         */
        $this->noConfig = $this->getConfig('team') ? false : true;


        return true;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Grab data for the team we are viewing

        $matches = $this->delegate->getTeamMatches($this->getConfig('team'), $this->getConfig('showHistorical'));

        return $this->renderTwigTemplate(
            $templateHandle,
            '@sportsfixturesHomepage/component.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'componentClass'      => $this->getComponentName(),
                'matches'             => $matches,
                'readOnly'            => $this->readOnly,
                'fixtureAdmin'        => $this->fixtureAdmin,
                'noConfig'            => $this->noConfig
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