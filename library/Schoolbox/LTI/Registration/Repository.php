<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Registration;

use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;

/**
 * Registration repository for the Schoolbox LTI platform.
 */
class Repository implements RegistrationRepositoryInterface
{
    /**
     * The registrations.
     *
     * @var array|RegistrationInterface[]
     */
    private array $registrations;

    /**
     * Constructor
     *
     * @param RegistrationInterface ...$registrations
     */
    public function __construct(RegistrationInterface ...$registrations)
    {
        $this->registrations = $registrations;
    }

    /**
     * Find the registration by its identifier.
     *
     * @param string $identifier
     * @return RegistrationInterface|null
     */
    public function find(string $identifier): ?RegistrationInterface
    {
        foreach ($this->registrations as $registration) {
            if ($registration->getIdentifier() === $identifier) {
                return $registration;
            }
        }
        return null;
    }

    /**
     * Find all registrations: in this repo, there is only one registration.
     *
     * @return RegistrationInterface[]
     */
    public function findAll(): array
    {
        return $this->registrations;
    }

    /**
     * Find the registration by its client ID,
     *
     * @param string $clientId
     * @return RegistrationInterface|null
     */
    public function findByClientId(string $clientId): ?RegistrationInterface
    {
        foreach ($this->registrations as $registration) {
            if ($registration->getClientId() === $clientId) {
                return $registration;
            }
        }
        return null;
    }

    /**
     * Find the registration by its platform issuer (a.k.a. audience), and
     * optionally its client ID.
     *
     * @param string      $issuer
     * @param string|null $clientId
     * @return RegistrationInterface|null
     */
    public function findByPlatformIssuer(string $issuer, ?string $clientId = null): ?RegistrationInterface
    {
        foreach ($this->registrations as $registration) {
            if ($registration->getPlatform()->getAudience() === $issuer) {
                if (!isset($clientId) || $registration->getClientId() === $clientId) {
                    return $registration;
                }
            }
        }
        return null;
    }

    /**
     * Find the registration by its tool issuer (a.k.a. audience), and
     * optionally its client ID.
     *
     * @param string      $issuer
     * @param string|null $clientId
     * @return RegistrationInterface|null
     */
    public function findByToolIssuer(string $issuer, ?string $clientId = null): ?RegistrationInterface
    {
        foreach ($this->registrations as $registration) {
            if ($registration->getTool()->getAudience() === $issuer) {
                if (!isset($clientId) || $registration->getClientId() === $clientId) {
                    return $registration;
                }
            }
        }
        return null;
    }
}
