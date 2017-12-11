<?php
/**
 * Tour renderer
 *
 * This class is used inside SitePage to render any tours that should appear on a specific page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tour;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Tour\Trigger\Mapper as TourTriggerMapper;
use Schoolbox\Tour\Trigger\Model as TourTrigger;
use Schoolbox\User;
use Twig_Environment;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;

class Renderer
{
    /** @var Mapper $tourMapper */
    protected $tourMapper;

    /** @var TourTriggerMapper $tourTriggerMapper */
    protected $tourTriggerMapper;

    /** @var ConfigMapper $configMapper */
    protected $configMapper;

    /**
     * Renderer constructor
     */
    public function __construct()
    {
        $this->configMapper      = ConfigMapper::getInstance();
        $this->tourMapper        = Mapper::getInstance();
        $this->tourTriggerMapper = TourTriggerMapper::getInstance();
    }

    /**
     * Render tours applicable to the current page being viewed
     *
     * @param Twig_Environment $twig Twig environment
     * @param User             $user The logged in user
     * @param string           $url  The URL being visited
     *
     * @return string
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function render(Twig_Environment $twig, User $user, string $url): string
    {
        // Retrieve all tours that the logged in user should be able to see

        $tours = $this->tourMapper->findByUserForUrl($user, $url);

        // Iterate through all of the tours for this page/user and setup an array for rendering of the appropriate JS

        $triggers = [];

        /** @var Model $tour */
        foreach ($tours as $tour) {
            $tourTriggers = $this->tourTriggerMapper->findForUrl($tour, $url);

            /** @var TourTrigger $tourTrigger */
            foreach ($tourTriggers as $tourTrigger) {
                $triggers[] = [
                    'tour'  => $tour,
                    'tourJavascriptSelector' => $tourTrigger->getJavascriptSelector(),
                    'tourJavascriptEvent'    => $tourTrigger->getJavascriptEvent(),
                ];
            }
        }

        return $twig->render('@tour/tour.twig', [
            'triggers' => $triggers,
        ]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'tour_renderer';
    }
}