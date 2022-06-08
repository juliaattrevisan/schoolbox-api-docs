<?php

namespace Schoolbox\Admin\HealthCheck;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Authorizer\Rule;
use InvalidArgumentException;

class Module extends BaseModule
{
    protected $basename = 'adminHealthCheckExport';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $this->register('GET', '/audit', 'getAudit', new Rule\IsSchoolboxAdmin());
        $this->register('GET', '/audit/assessmentAudit', 'assessmentAudit', new Rule\IsSchoolboxAdmin());
        $this->register('GET', '/audit/homepageTemplates', 'homepageTemplates', new Rule\IsSchoolboxAdmin());
        $this->register('GET', '/audit/topMenuReport', 'topMenuReport', new Rule\IsSchoolboxAdmin());
        $this->register('GET', '/audit/sideMenuReport', 'sideMenuReport', new Rule\IsSchoolboxAdmin());
        $this->register('GET', '/audit/resourceBrowser', 'resourceBrowser', new Rule\IsSchoolboxAdmin());
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('user.tokenBuilder'),
            $this->getConfig(),
            $this->getRouter(),
            $this->injector->get('taskd.messageFactory'),
            $this->injector->get('taskd.messageProducer')
        );
    }
}
