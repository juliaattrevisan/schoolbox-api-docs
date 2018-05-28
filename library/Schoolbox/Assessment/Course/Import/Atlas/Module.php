<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import\Atlas;

use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use GuzzleHttp\Client;
use Schoolbox\Learning\Course;
use Twig\Error\LoaderError;

class Module extends \Alaress\Module\Module
{
    /** @var string */
    protected $basename = 'courseImportAtlas';


    /**
     * @return void
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $staffOnly = $this->getAuthRule('isStaffOrSuper');
        $this->register('GET', '', 'getImport', $staffOnly);
        $this->register('POST', '', 'postImport', $staffOnly);
    }


    /**
     * @param IChainLoader $templateLoader
     * @throws LoaderError
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'apiBridge' => $injector->lazyNew(
                        ApiBridge::class,
                        [
                            'httpClient' => $injector->lazyNew(
                                Client::class,
                                [
                                    'config' => [
                                        'base_uri' => 'https://api.rubicon.com/api/',
                                        'headers'  => [
                                            'Authorization' => 'Bearer ' . $this->getConfig()->get('rubicon_atlas_api_token'),
                                            'Accept'        => 'application/json',
                                        ],
                                    ]
                                ]
                            )
                        ]
                    )
                ]
            )
        );
    }
}
