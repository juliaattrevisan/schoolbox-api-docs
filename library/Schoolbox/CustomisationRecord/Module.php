<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\CustomisationRecord;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Role;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'customisationRecord';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $staffWithViewPermission = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\HasRolePermission(Role::PERMISSION_VIEW_STUDENT_GRADES, Role::STAFF),
                new Rule\IsSuperuser()
            )
        );

        $canViewStudentRecord = new Rule\All(
            $staffWithViewPermission,
            new Rule\Callback(
                function ($params) {
                    $studentId = $params['studentId'] ?? null;
                    if (!$studentId) {
                        throw new Unauthorized();
                    }
                    /* Allows the extra layer of checking to see if the student has existing customisations, if not then shouldn't be able to view the records
                     */
                    $customisations = $this->getMapperFactory()->get(Mapper::class)->findByColumn('user_id', $studentId);
                    if ($customisations->count() <= 0) {
                        throw new Unauthorized();
                    }
                    return true;
                }
            )
        );

        $this->register(self::METHOD_GET, '/records', 'viewRecords', $staffWithViewPermission);
        $this->register(self::METHOD_GET, '/records/student/{studentId}', 'viewRecordByStudent', $canViewStudentRecord);
        $this->register(self::METHOD_GET, '/records/list', 'getListRecords', $staffWithViewPermission);
        $this->register(self::METHOD_GET, '/records/export', 'exportRecordsToCsv', $staffWithViewPermission);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->injector->get('assessmentDataSource'),
            $this->injector->get('filters.factoryFactory'),
            $this->getMapperFactory()->get(\Schoolbox\Filter\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Filter\Selection\Mapper::class),
            $this->injector->get('l10n'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
