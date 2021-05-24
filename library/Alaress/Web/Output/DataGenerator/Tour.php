<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Schoolbox\IConfigLoader;
use Schoolbox\Tour\Lists as TourLists;
use Schoolbox\User;

use function array_key_exists;

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
     * @var TourLists
     */
    private $tourList;


    /**
     * @var array
     */
    private $serverVars;


    /**
     * Constructor
     *
     * @param IConfigLoader $config
     * @param null|User     $currentUser
     * @param TourLists        $tourList
     * @param array         $serverVars
     */
    public function __construct(IConfigLoader $config, ?User $currentUser, TourLists $tourList, array $serverVars)
    {
        $this->config      = $config;
        $this->currentUser = $currentUser;
        $this->tourList    = $tourList;
        $this->serverVars  = $serverVars;
    }


    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        $tour = [];
        if ($this->currentUser && $this->config->get('tours_enabled')) {
            // parse_url fails when REQUEST_URI contains a colon. Don't tour when failed to parse_url.
            $request    = parse_url($this->serverVars['REQUEST_URI']);
            if ($request && array_key_exists('path', $request)) {
                $tour['data'] = $this->tourList->getTours($this->currentUser, $request['path']);
            }
        }
        return $tour;
    }
}
