<?php

/**
 * International Baccalaureate admin module
 *
 * @author      Haris Ali <haris.ali@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IB\Group\Subject\Language\Mapper as IBGroupSubjectLanguageMapper;
use Schoolbox\IB\Group\Subject\Mapper as IBGroupSubjectMapper;
use Schoolbox\IB\Group\Mapper as IBGroupMapper;
use Schoolbox\Language\Mapper as LanguageMapper;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Code\Mapper as FolderCodeMapper;
use Schoolbox\IB\Registrations\Sessions\Mapper as RegistrationSessionMapper;

class Module extends BaseModule
{
    protected $basename = 'internationalBaccalaureate';

    /**
     * @return void
     */
    protected function registerRoutes(): void
    {
        $ruleIBCoordinatorAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_IB_COORDINATOR, UserAccess::ACCESS_FULL)
            )
        );
        $preloadRegistrationSession = function ($params) {
            return $this->preloadRegistrationSession($params);
        };

        $this->register('GET', '/configuration', 'getConfiguration', $ruleIBCoordinatorAccess);
        $this->register('GET', '/configuration/api/subjects/{groupId:\\d+}', 'getCategorySubjects', $ruleIBCoordinatorAccess);
        $this->register('GET', '/configuration/api/categories/{categoryId:\\d+}', 'getConfigurationData', $ruleIBCoordinatorAccess);
        $this->register('GET', '/configuration/api/languages', 'searchLanguages', $ruleIBCoordinatorAccess);
        $this->register('GET', '/configuration/api/class-codes', 'searchClassCodes', $ruleIBCoordinatorAccess);
        $this->register('GET', '/configuration/api/student-count', 'getStudentCount', $ruleIBCoordinatorAccess);
        $this->register('POST', '/configuration/api/save', 'saveConfiguration', $ruleIBCoordinatorAccess);
        $this->register('GET', '/registrations', 'getRegistrations', $ruleIBCoordinatorAccess);

        /* Registration Sessions
         */
        $this->register('GET', '/registrations/sessions', 'getRegistrationSessions', $ruleIBCoordinatorAccess);
        $this->register('POST', '/registrations/sessions', 'createRegistrationSession', $ruleIBCoordinatorAccess);
        $this->register('DELETE', '/registrations/sessions/{sessionId:\d+}', 'deleteRegistrationSession', $ruleIBCoordinatorAccess, $preloadRegistrationSession);
        $this->register('GET', '/registrations/sessions/type/{id:\\d+}', 'test', $ruleIBCoordinatorAccess);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ConfigMapper::class),
            $this->getMapperFactory()->get(IBGroupMapper::class),
            $this->getMapperFactory()->get(IBGroupSubjectMapper::class),
            $this->getMapperFactory()->get(IBGroupSubjectLanguageMapper::class),
            $this->getMapperFactory()->get(LanguageMapper::class),
            $this->getMapperFactory()->get(FolderCodeMapper::class),
            $this->getMapperFactory()->get(RegistrationSessionMapper::class),
            $this->injector->get('externalClass'),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
            $this->injector->get('oidc.cache'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Preload an existing registration session
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception\NotFound
     */
    private function preloadRegistrationSession(array $params): array
    {
        if (\array_key_exists('registrationSession', $params)) {
            $registrationSession = $params['registrationSession'];
        } else {
            try {
                $registrationSession = $this->getMapperFactory()
                    ->get(RegistrationSessionMapper::class)
                    ->findById($params['sessionId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw new NotFound('Unknown registration session id submitted.', 0, $e);
            }
        }

        return [
            'registrationSession' => $registrationSession,
        ];
    }
}
