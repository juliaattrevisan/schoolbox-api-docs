<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\User;

/**
 * Generate data for rendering a feature tour.
 */
class Tour implements IDataGenerator
{
    /**
     * @var IConfigLoader
     */
    private $config;


    /**
     * @var null|User
     */
    private $currentUser;


    /**
     * @var Router
     */
    private $router;


    /**
     * @var array
     */
    private $serverVars;


    /**
     * Constructor
     *
     * @param IConfigLoader $config
     * @param null|User     $currentUser
     * @param Router        $router
     * @param array         $serverVars
     */
    public function __construct(IConfigLoader $config, ?User $currentUser, Router $router, array $serverVars)
    {
        $this->config      = $config;
        $this->currentUser = $currentUser;
        $this->router      = $router;
        $this->serverVars  = $serverVars;
    }


    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        $tour = [];
        // TODO figure out how to figure out if user's impersonated?
        // Alternately just don't show tours to impersonated users
        if ($this->currentUser && $this->config->get('tours_enabled')) {
            $request    = parse_url($this->serverVars['REQUEST_URI']);
            $encodedUrl = Redirect::encode($request['path']);

            // Add the route generator here
            try {
                $tour['url'] = $this->router->generate('tour.getTours', ['url' => $encodedUrl]);
            } catch (RouteNotFound $e) {
                // noop
            }
        }
        return $tour;
    }
}
