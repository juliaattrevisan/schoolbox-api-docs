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
        /**
         * Only show the parent register to parents
         */
        $parentRegisterPermission = new Rule\HasRole(Role::PARENT);

        /**
         * Only show the register for students to the following:
         * Staff who can see profiles
         * the student themselves
         * the parent of the specified student
         */
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
            '/{registerCode}/student/{studentId}',
            'getStudent',
            $studentRegisterPermission,
            $studentRegisterPreload,
        );
        $this->register(
            self::METHOD_GET,
            '/{registerCode}/parent',
            'getParent',
            $parentRegisterPermission,
        );

        $this->register(self::METHOD_GET, '/list', 'getRegisterList', new Rule\IsSuperuser());
        $this->register(self::METHOD_GET, '/{registerCode}/getFieldList', 'getFieldList', new Rule\IsSuperuser());
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('httpClient'),
            $this->injector->get('oidc.cache'),
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
