<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tour;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Schoolbox\Learning\Assessment\Redirect;

class Module extends BaseModule
{
    /** @var string $basename */
    protected $basename = 'tour';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes()
    {
        // Define routes and assign permissions to them

        $this->registerRoutesArray([
            'GET'  => [
                '/url/{url}' => ['getTours' => null],
                '/getStatus/{tour_uuid}' => 'tourStatus'
            ],
            'POST' => [
                '/url/{url}{/oldStep}' => 'saveStatus',
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $mappers = [
            'tourMapper'        => $injector->get('mapperFactory')->getLazy(Mapper::class),
            'tourTriggerMapper' => $injector->get('mapperFactory')->getLazy(Trigger\Mapper::class),
            'tourStepMapper'    => $injector->get('mapperFactory')->getLazy(Step\Mapper::class),
            'tourStatusMapper'  => $injector->get('mapperFactory')->getLazy(Status\Mapper::class)
        ];

        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, $mappers));
    }


    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload($matcher, '.*', function ($params) {
            $injectedValues = [];

            // Decode URL and inject into controller functions

            if (array_key_exists('url', $params)) {
                $injectedValues['url'] = Redirect::decode($params['url']);
            }

            return $injectedValues;
        });
    }

}
