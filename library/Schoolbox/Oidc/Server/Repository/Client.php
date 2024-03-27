<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Schoolbox\Oidc\Server\Entity\Client as ClientEntity;
use Schoolbox\Oidc\Server\RelyingParty;

/**
 * OIDC client repository interface implementation: fetches and validates a
 * client entity out of what is set up in the database.
 */
class Client implements ClientRepositoryInterface
{
    /**
     * Constructor
     *
     * @param RelyingParty\Mapper $mapper Mapper used to find relying parties.
     */
    public function __construct(private RelyingParty\Mapper $mapper)
    {
    }

    /**
     * Get a client entity.
     *
     * @param string $clientIdentifier The client's identifier.
     *
     * @return ClientEntityInterface|null
     */
    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        $relyingParty = $this->findRelyingParty($clientIdentifier);
        if (!$relyingParty) {
            return null;
        }
        return new ClientEntity($relyingParty);
    }

    /**
     * Validate whether this client is valid for the given grant.
     *
     * @param string $clientIdentifier The client's identifier.
     * @param string $clientSecret     The client's secret: will be hashed and
     *                                 compared to the stored value.
     * @param string $grantType        The requested grant type.
     * @return bool
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        $relyingParty = $this->findRelyingParty($clientIdentifier);
        if (!$relyingParty) {
            return false;
        }
        $clientSecretHash = RelyingParty\Generator\ClientSecret::hash($clientSecret);
        if ($clientSecretHash !== $relyingParty->getClientSecretHash()) {
            return false;
        }

        // Currently assume any grant type is acceptable
        return true;
    }

    /**
     * Helper method for finding the relying party model from the client ID.
     *
     * @param string $clientIdentifier
     * @return RelyingParty\Model|null
     */
    private function findRelyingParty(string $clientIdentifier): ?RelyingParty\Model
    {
        return $this->mapper->findByColumn('client_id', $clientIdentifier)->first();
    }
}
