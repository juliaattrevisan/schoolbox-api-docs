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
     * Constructor
     *
     * @param IConfigLoader $config
     * @param null|User $currentUser
     * @param TourLists $tourList
     * @param array $serverVars
     * @param ?string $version
     */
    public function __construct(
        private IConfigLoader $config,
        private ?User $currentUser,
        private TourLists $tourList,
        private array $serverVars,
        private ?string $version,
    ) {
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
                $tour['data'] = $this->tourList->getTours($this->currentUser, $request['path'], $this->version);
            }
        }
        return $tour;
    }
}
