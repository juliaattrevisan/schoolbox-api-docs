<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Schoolbox\IConfigLoader;
use Schoolbox\Tour\Lists as TourLists;
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
            $request    = parse_url($this->serverVars['REQUEST_URI']);
            $tour['data'] = $this->tourList->getTours($this->currentUser, $request['path']);
        }
        return $tour;
    }
}
