<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Schoolbox\API\Token\Jwt;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

class Api extends \Codeception\Module
{
    /** @var \Schoolbox\Application\CliTool\Kernel */
    public \Schoolbox\Application\CliTool\Kernel $cliKernel;

    /**
     * HOOK: executed after configuration is loaded
     *
     * Initializes the cli kernel so that we can use the dependency injector.
     */
    public function _initialize(): void
    {
        $app = new class () extends \Schoolbox\Application\CliTool\App
        {
            public function run(): void
            {
            }
        };
        // TODO Expose kernel options as Helper config in codeception.dist.yml
        $this->cliKernel = new \Schoolbox\Application\CliTool\Kernel(
            $app,
            [\Schoolbox\Application\CliTool\Kernel::OPTION_ERRORS => E_ALL]
        );
    }

    /**
     * @return string
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getToken(): string
    {
        $admin = UserMapper::getInstance()->findByUsername('alaress');
        /** @var Jwt $tokenBuilder */
        $tokenBuilder = $this->cliKernel->get('user.tokenBuilder');
        return $tokenBuilder->generate($admin);
    }

    public function getCampuses()
    {
        return CampusMapper::getInstance()->findAll();
    }

    public function getRoles()
    {
        return RoleMapper::getInstance()->findAll();
    }
}
