<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Helper;

use Codeception\Exception\ModuleException;
use Codeception\Module\Db;
use Codeception\TestInterface;
use Payload\Request;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\CampusMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Action\Mapper as PastoralActionMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubtypeMapper;
use Schoolbox\Pastoral\SubType\Model;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Custom methods for API testing
 *
 * Run "./vendor/bin/codecept build" to access methods in this class
 */
//class Api extends \Codeception\Module
class Api extends \Codeception\Module
{
    /** @var Kernel */
    public Kernel $cliKernel;

    private UserMapper $userMapper;

    private array $originalConfig = [];

    /**
     * HOOK: executed after configuration is loaded
     *
     * Initializes the cli kernel so that we can use the dependency injector.
     */
    public function _initialize(): void
    {
        parent::_initialize();
        $app = new class () extends \Schoolbox\Application\CliTool\App {
            public function run(): void
            {
            }
        };
        // TODO Expose kernel options as Helper config in codeception.dist.yml
        $this->cliKernel  = new Kernel(
            $app,
            [Kernel::OPTION_ERRORS => E_ALL]
        );
        $this->userMapper = UserMapper::getInstance();
    }

    private array $users = [];

    public function _before(TestInterface $test): void
    {
        parent::_before($test);
        $this->userMapper = UserMapper::getInstance();

        $time = time();

        $this->users['super']   = $this->createTestUser("tester-superuser-$time", Role::STAFF, true);
        $this->users['staff']   = $this->createTestUser("tester-staff-$time", Role::STAFF, false);
        $this->users['student'] = $this->createTestUser("tester-student-$time", Role::STUDENT, false);
        $this->users['parent']  = $this->createTestUser("tester-parent-$time", Role::PARENT, false);
        $this->users['guest']   = $this->createTestUser("tester-guest-$time", Role::GUEST, false);
    }

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    /**
     * Generate token for user
     * @param string $username
     * @return string
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getAdminToken(): string
    {
        $admin = $this->userMapper->findByUsername('alaress');
        /** @var Jwt $tokenBuilder */
        $tokenBuilder = $this->cliKernel->get('user.tokenBuilder');
        return $tokenBuilder->generate($admin);
    }

    /**
     * Sets database config value temporally
     * @param string $name
     * @param mixed  $value
     */
    public function changeDBConfig(string $name, $value)
    {
        $db = $this->getDbModule();

        $currentValue = $db->grabFromDatabase('config', 'value', ['name' => $name]);
        if ($value === $currentValue) {
            // no need to change
            return;
        }

        if (!key_exists($name, $this->originalConfig)) {
            $this->originalConfig[$name] = $currentValue;
        }

        $db->updateInDatabase('config', ['value' => $value], ['name' => $name]);
    }

    /**
     * Resets database value back
     */
    public function restoreDBConfig()
    {
        $db = $this->getDbModule();
        foreach ($this->originalConfig as $name => $value) {
            $db->updateInDatabase('config', ['value' => $value], ['name' => $name]);
        }
    }

    /**
     * @param string $username
     * @param string $roleType
     * @param bool   $superUser
     * @return User
     * @throws MapperException
     */
    public function createTestUser(string $username, string $roleType, bool $superUser): User
    {
        $user = $this->userMapper->createModel()
                                 ->setUsername($username)
                                 ->setFirstname($username)
                                 ->setRole($this->getRole($roleType))
                                 ->setCampusCollection($this->getCampuses())
                                 ->setLocalEmail(false)
                                 ->setWebmail(false)
                                 ->setUseInternalMail(false)
                                 ->setEportfolioEnable(false)
                                 ->setEnabled(true)
                                 ->setSuperuser($superUser);

        $this->userMapper->save($user);
        return $user;
    }

    /**
     * @return GenericCollection
     */
    public function getCampuses(): GenericCollection
    {
        return CampusMapper::getInstance()->findAll();
    }

    /**
     * @param string $roleType
     * @return Role
     * @throws MapperException
     */
    public function getRole(string $roleType): Role
    {
        return RoleMapper::getInstance()->findOneByColumn('group_type', $roleType);
    }

    /**
     * @return GenericCollection
     */
    public function getPastoralTypes(): GenericCollection
    {
        return PastoralTypeMapper::getInstance()->findAll();
    }

    /**
     * @return GenericCollection
     */
    public function getPastoralSeverities(): GenericCollection
    {
        return PastoralSeverityMapper::getInstance()->findAll();
    }

    /**
     * @return GenericCollection
     */
    public function getPastoralTags()
    {
        return PastoralTagMapper::getInstance()->findAll();
    }

    /**
     * @return GenericCollection
     * @throws MapperException
     */
    public function preparePastoralActions(): GenericCollection
    {
        $pastoralActionMapper  = PastoralActionMapper::getInstance();
        $pastoralSubtypeMapper = PastoralSubtypeMapper::getInstance();
        $pastoralTypeMapper    = PastoralTypeMapper::getInstance();
        $actions               = $pastoralActionMapper->findAll();

        if ($actions->count() === 0) {
            for ($i = 0; $i < 5; $i++) {
                $newAction = $pastoralActionMapper->createModel()
                                                  ->setName($this->generateText(50))
                                                  ->setSequence($i);
                $pastoralActionMapper->save($newAction);
            }
            $actions = $pastoralActionMapper->findAll();
        }

        $types = $pastoralTypeMapper->findAll();
        foreach ($types as $type) {
            if ($type->getSubTypes()->count()) {
                /** @var Model $subType */
                $subType = $type->getSubTypes()->first();
                $subType->setActions($pastoralActionMapper->createCollection()->add($actions->first()));
                $pastoralSubtypeMapper->save($subType);
            } else {
                $type->setActions($pastoralActionMapper->createCollection()->add($actions->last()));
                $pastoralTypeMapper->save($type);
            }
        }

        $test = $this->getMaxValue('lu_pastoral_action', 'id');

        return $pastoralActionMapper->findAll();
    }

    /**
     * Return DB module in a type-safe manner.
     *
     * @return Db
     * @throws ModuleException
     */
    private function getDbModule(): Db
    {
        return $this->getModule('Db');
    }

    /**
     * @param int $length
     * @return string
     */
    public function generateText(int $length): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz.012345678!@#$%*&^()-+';

        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            if ($length > 8 && rand(1, 7) === 5) {
                $randomString .= ' ';
                $i++;
            }
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }


    /**
     * @param string $tableName
     * @param string $column
     * @return mixed
     * @throws ModuleException
     */
    public function getMaxValue(string $tableName, string $column)
    {
        return $this->getDbModule()->grabFromDatabase($tableName, "max($column)");
    }
}
