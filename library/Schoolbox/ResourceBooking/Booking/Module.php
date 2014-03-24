<?php
/**
 * Module definition for the booking part of the resource booking module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\ResourceBooking\Booking;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule {

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath) {
        // All of these URLs relate to the resource ID, not a booking ID.
        // Therefore, they should probably be moved to the item module.
        $router->addGet('resourceBooking.book', $basePath.'/bookItem/{id}');
        $router->addPost('resourceBooking.createBooking', $basePath.'/bookItem/{id}')
               ->addValues(array('action' => 'createBooking'));
        $router->add('resourceBooking.bookingCalendar', $basePath.'/itemCalendar/{id}');

        // Approving or rejecting a single booking
        $router->add('resourceBooking.approve', $basePath.'/{id}/approve');
        $router->add('resourceBooking.reject',  $basePath.'/{id}/reject');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {
        $authorizer
            ->addRule('/^resourceBooking(?:$|\.)/', new Rule\IsLoggedIn())
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader) {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__.'/templates', 'resourceBooking');
        $templateLoader->addLoader($loader);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector) {
        $injector->setters['Schoolbox\\ResourceBooking\\Booking\\Controller']['setEventMapper']
            = $injector->lazyNew('EOTC\\Calendar\\EventMapper');
        $injector->setters['Schoolbox\\ResourceBooking\\Booking\\Controller']['setBookingMapper']
            = $injector->lazyNew('Schoolbox\\ResourceBooking\\Booking\\Mapper');
        $injector->setters['Schoolbox\\ResourceBooking\\Booking\\Controller']['setResourceMapper']
            = $injector->lazyNew('Schoolbox\\ResourceBooking\\Item\\Mapper');
        $injector->set(
            'controller:resourceBooking',
            $injector->lazyNew('Schoolbox\\ResourceBooking\\Booking\\Controller')
        );
    }

}
