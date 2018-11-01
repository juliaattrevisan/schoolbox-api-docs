<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report;

use Alaress;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Alaress\Authorizer\Rule;

class Module extends Alaress\Module\Module
{

    public const BASE_NAME = 'tigsSummativeReport';


    /**
     * @inheritdoc
     */
    public function __construct(ContainerInterface $injector, string $basename = '')
    {
        parent::__construct($injector, $basename ?: self::BASE_NAME);
    }


    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $isLoggedIn = new Rule\IsLoggedIn();

        $this->register('GET', '/user/{userId}', 'getParticipationReport', $isLoggedIn);
        $this->register('GET', '/{userId}/folder/{folderId}', 'getSubjectReport', $isLoggedIn);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . self::BASE_NAME, $injector->lazyNew(Controller::class));
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
