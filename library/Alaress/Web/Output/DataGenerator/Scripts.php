<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Schoolbox\IConfigLoader;

/**
 * Generate data for rendering custom scripts.
 */
class Scripts implements IDataGenerator
{
    /**
     * @var IConfigLoader
     */
    private $config;


    /**
     * @var array
     */
    private $serverVars;


    /**
     * Constructor
     *
     * @param IConfigLoader $config
     * @param array         $serverVars
     */
    public function __construct(IConfigLoader $config, array $serverVars)
    {
        $this->config     = $config;
        $this->serverVars = $serverVars;
    }


    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        $data       = ['header' => '',];
        $requestUri = $this->serverVars['REQUEST_URI'] ?? '';
        if (strpos($requestUri, '/adminv2/') !== 0) {
            $data['header'] = $this->config->get('site_header_scripts');
        }
        return $data;
    }
}
