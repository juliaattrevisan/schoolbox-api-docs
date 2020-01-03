<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Fixtures\Component\Dashboard;

use Alaress\Database\Connector\Base;
use Alaress\Session\Session;
use Aura\Di\Exception\ServiceNotFound;
use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use RuntimeException;
use Schoolbox\Collection;
use Schoolbox\Fixtures\Component\ComponentDelegate;
use Schoolbox\User;
use SitePage;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;
use UnexpectedValueException;

/**
 * Class Controller
 *
 * @package Schoolbox\Fixtures\Component\Dashboard
 */
class Controller extends Component_Dashboard_ControllerAbstract
{

    /**
     * @var ComponentDelegate
     */
    private $delegate;


    /**
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
     * @return bool  Whether to continue on and attempt Component render
     * @throws RuntimeException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     */
    public function execute()
    {

        /* If the module is disabled, do nothing
         */
        if (!(int)$this->getSitePage()->getDi()->get('config')->get('fixtures_enabled')) {
            return false;
        }

        /* Otherwise fetch all the relevant matches
         */
        $getHistorical = false;

        // Grab the relevant data depending on role type
        $userIds = [$this->getUser()->getId()];

        if ($this->getUser()->getRole()->isParent()) {

            /** @var Session $session */
            $session  = $this->getSitePage()->getDi()->get('session');

            $children = $session->get('children') ?: [];

            foreach ($children as $child) {
                $userIds[] = $child['id'];
            }
        }

        $this->matches = $this->delegate->getMatchesForUsers($userIds, $getHistorical);

        return count($this->matches) > 0;
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
            '@sportsfixturesDashboard/component.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'componentClass'      => $this->getComponentName(),
                'user'                => $this->getUser(),
                'matches'             => $this->matches,
            ]
        );
    }
}
