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
        $isAlaress = new Rule\IsSchoolboxAdmin();
        $this->register('GET', '/audit', 'getAudit', $isAlaress);
        $this->register('GET', '/audit/assessmentAudit', 'assessmentAudit', $isAlaress);
        // UXUI Audits
        $this->register('GET', '/audit/topMenuReport', 'topMenuReport', $isAlaress);
        $this->register('GET', '/audit/sideMenuReport', 'sideMenuReport', $isAlaress);
        $this->register('GET', '/audit/resourceBrowser', 'resourceBrowser', $isAlaress);
        // User Management Audits
        $this->register('GET', '/audit/listOfRoles', 'listOfRoles', $isAlaress);
        $this->register('GET', '/audit/listOfCampuses', 'listOfCampuses', $isAlaress);
        $this->register('GET', '/audit/listOfYearLevels', 'listOfYearLevels', $isAlaress);
        $this->register('GET', '/audit/listOfHouses', 'listOfYearLevels', $isAlaress);
        // Common Audits
        $this->register('GET', '/audit/homepageTemplates', 'homepageTemplates', $isAlaress);
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
