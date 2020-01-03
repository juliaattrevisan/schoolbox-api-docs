<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Fixtures\Component\Homepage;

use Aura\Di\Exception\ServiceNotFound;
use Component_Homepage_ControllerAbstract;
use Component_Instance;
use RuntimeException;
use Schoolbox\Collection;
use Schoolbox\Fixtures\Component\ComponentDelegate;
use Schoolbox\User;
use \Schoolbox\Permission\Access\UserAccess;
use SitePage;
use Alaress\Database\Connector\Base;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;

/**
 * Class Controller
 *
 * @package Schoolbox\Fixtures\Component\Homepage
 */
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
    private $fixtureAdmin = false;


    /**
     * Whether a team has been set in component config
     *
     * @var bool
     */
    private $noConfig;


    /**
     * Set to true if
     *
     * @var bool
     */
    private $noInstall;


    /**
     * Matches for the team we are viewing
     *
     * @var Collection
     */
    private $matches;


    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     * @throws ServiceNotFound
     * @throws RuntimeException
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);

        /** @var Base $databaseInternal */
        $databaseInternal = $sitepage->getDi()->get('databaseInternal');
        $this->delegate = new ComponentDelegate($databaseInternal);
    }


    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     * @throws ServiceNotFound
     * @throws RuntimeException
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
        /** @var UserAccess $fixturesAccess */
        $fixtureAccess = $this->getUser()->getAccessList()->getBy('service', UserAccess::SERVICE_SPORTS_ADMINISTRATOR);

        if ((!empty($fixtureAccess) && $fixtureAccess->getAccessLevel() >= UserAccess::ACCESS_WRITE) || $this->getUser()->isSuperuser()) {
            $this->fixtureAdmin = true;
        }

        /**
         * Check if a team has been set in the component config
         */
        $this->noInstall = $this->getSitePage()->getDi()->get('config')->get('fixtures_enabled') == '1'  ? false : true;

        $this->noConfig = $this->getConfig('team')  ? false : true;

        // Grab data for the team we are viewing

        $this->matches = $this->delegate->getTeamMatches($this->getConfig('team'), $this->getConfig('showHistorical'));

        return true;
    }


    /**
     * @param String $templateHandle
     * @return bool
     * @throws RuntimeException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@sportsfixturesHomepage/component.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'componentClass'      => $this->getComponentName(),
                'matches'             => $this->matches,
                'readOnly'            => $this->readOnly,
                'fixtureAdmin'        => $this->fixtureAdmin,
                'noConfig'            => $this->noConfig,
                'noInstall'           => $this->noInstall,
                'team'                => $this->getConfig('team')
            ]
        );
    }
}
