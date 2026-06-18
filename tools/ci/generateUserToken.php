#!/usr/bin/env php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\API\Token\Jwt;
use Schoolbox\UserMapper;
use Schoolbox\User\Token\Mapper as TokenMapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Generates a JWT token for a given user
 * Used in Acceptance tests to retrieve a token to run API calls instead of direct DB modifications
 */
$app = new class extends CliTool\BaseApp
{
    private const OPT_LONG_USER  = 'user:';

    private const OPT_LONG_EFFECTOR = 'effector:';

    private const OPT_LONG_EXPIRY = 'expiry:';

    private const DEFAULT_EXPIRY = 3600; // 1 hour;

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    protected function getName(): string
    {
        return 'generateUserToken';
    }

    protected function getDescription(): string
    {
        return 'Generates a JWT token for a given user. Used in Acceptance tests to retrieve a token to run API calls instead of direct DB modifications.';
    }

    protected function setOptions(): self
    {
        $this->addOption(null, self::OPT_LONG_USER, 'ID of the user to generate a token for');
        $this->addOption(null, self::OPT_LONG_EFFECTOR, 'ID of the user who generated the token');
        $this->addOption(null, self::OPT_LONG_EXPIRY, sprintf('The amount of time (in seconds) the token is valid for, defaults to %d', self::DEFAULT_EXPIRY));
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return false;
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        $userId = $this->getOpt(self::OPT_LONG_USER);
        if (!isset($userId)) {
            $this->getLogger()->error('A user ID must be provided');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $effectorId = $this->getOpt(self::OPT_LONG_EFFECTOR);
        if (!isset($effectorId)) {
            $this->getLogger()->error('An effector ID must be provided');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $expiry = $this->getOpt(self::OPT_LONG_EXPIRY) ?? self::DEFAULT_EXPIRY;

        $userMapper = UserMapper::getInstance();
        $tokenMapper = TokenMapper::getInstance();

        $user = $userMapper->findById($userId);
        $effector = $userMapper->findById($effectorId);

        $token = $tokenMapper->generateForUser(
            $user,
            $effector,
            \Alaress_Date_Format::now()->addSeconds($expiry)
        );

        /** @var Jwt $tokenBuilder */
        $tokenBuilder = $this->getDi()->get('user.tokenBuilder');
        $jwt = $tokenBuilder->generate($user, $token->getJwtId());

        echo $jwt;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
