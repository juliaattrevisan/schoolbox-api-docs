<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Isams\Register;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Role;
use Schoolbox\UserMapper;

/**
 * Module for the iSams register.
 */
class Module extends BaseModule
{
    protected $basename = 'isamsRegister';

    /**
     * @inheritdoc
     */
    public function registerRoutes()
    {
        $studentRegisterPermission = new Rule\Any(
            new Rule\IsUserOrParentOfUser(
                $this->getExternalUser(),
                $this->getMapperFactory()->get(UserMapper::class),
                'studentId',
            ),
            new Rule\HasRolePermission(Role::PERMISSION_VIEW_STUDENT_PROFILE, Role::STAFF),
        );
        $studentRegisterPreload = function (array $params) {
            $student = $this->getMapperFactory()->get(UserMapper::class)->findById($params['studentId']);
            if (!$student->getRole()->isStudent()) {
                throw new NotFound();
            }
            return [
                'student' => $student,
            ];
        };
        $this->register(
            self::METHOD_GET,
            '/student/{studentId}',
            'getStudent',
            $studentRegisterPermission,
            $studentRegisterPreload,
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('httpClient'),
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
