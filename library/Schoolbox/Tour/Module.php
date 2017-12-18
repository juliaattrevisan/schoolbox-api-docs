<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tour;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\Tour\Trigger\Mapper as TourTriggerMapper;

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
            ],
            'POST' => [
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $mappers = [
            'tourMapper'        => $injector->get('mapperFactory')->getLazy(TourMapper::getInstance()),
            'tourTriggerMapper' => $injector->get('mapperFactory')->getLazy(TourTriggerMapper::getInstance()),
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
                $injectedValues['url'] = urldecode(base64_decode($params['url']));
            }

            return $injectedValues;
        });
    }

}
