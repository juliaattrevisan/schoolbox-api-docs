<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\AccessToken;

use Alaress_Date_Format;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Entity\AccessToken;
use OAT\Library\Lti1p3Core\Security\OAuth2\Entity\Scope;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

/**
 * Access token cache: basically, a wrapper around a mapper.
 */
class CachedAccessTokenPool implements CacheItemPoolInterface
{
    /**
     * Access token mapper
     */
    private Mapper $accessTokenMapper;

    /**
     * A repository of clients: required so that rehydrated access tokens can be
     * associated with a client.
     */
    private ClientRepositoryInterface $clientRepository;

    /**
     * Access token cache
     *
     * @var array<string, CachedAccessToken>
     */
    private array $cache = [];

    /**
     * Constructor
     *
     * @param Mapper                    $accessTokenMapper
     * @param ClientRepositoryInterface $clientRepository
     */
    public function __construct(Mapper $accessTokenMapper, ClientRepositoryInterface $clientRepository)
    {
        $this->accessTokenMapper = $accessTokenMapper;
        $this->clientRepository  = $clientRepository;
    }

    /**
     * Returns a Cache Item representing the specified key.
     *
     * @param string $key
     * @return CachedAccessToken
     * @throws CacheInvalidArgumentException if `$key` isn't valid
     */
    public function getItem($key)
    {
        $this->assertKeyValid($key);
        if (!isset($this->cache[$key])) {
            $accessToken = new AccessToken();
            $accessToken->setIdentifier($key);
            try {
                $model = $this->accessTokenMapper->findById($key);

                // TODO decide once and for all upon identifier in LTI: I think username makes most sense
                $accessToken->setUserIdentifier($model->getUser()->getUsername() ?? null);
                $accessToken->setClient(
                    $this->clientRepository->getClientEntity($model->getLtiTool()->getClientId())
                );
                foreach ($model->getScopes() as $scope) {
                    $accessToken->addScope(new Scope($scope));
                }
                $tokenExists = true;
            } catch (MapperException $e) {
                $tokenExists = false;
            }
            $this->cache[$key] = (new CachedAccessToken($accessToken, $tokenExists))->setDirty(!$tokenExists);
        }
        return $this->cache[$key];
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param array<string> $keys
     * @return array<string, CachedAccessToken>
     * @throws CacheInvalidArgumentException if any `$keys` aren't valid
     */
    public function getItems(array $keys = [])
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getItem($key);
        }
        return $result;
    }

    /**
     * Confirms if the cache contains specified cache item.
     *
     * @param string $key
     * @return bool
     * @throws CacheInvalidArgumentException if `$key` isn't valid
     */
    public function hasItem($key)
    {
        $this->assertKeyValid($key);
        return isset($this->cache[$key]);
    }

    /**
     * Deletes all items in the pool.
     *
     * @return bool
     */
    public function clear()
    {
        foreach (array_keys($this->cache) as $key) {
            if (!$this->deleteItem($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Removes the item from the pool.
     *
     * @param string $key
     * @return bool
     * @throws CacheInvalidArgumentException if `$key` isn't valid
     */
    public function deleteItem($key)
    {
        $this->assertKeyValid($key);
        if (!$this->hasItem($key)) {
            return false;
        }

        $deleted = $this->accessTokenMapper->deleteByColumn('identifier', $key);
        if ($deleted) {
            unset($this->cache[$key]);
            return true;
        }
        return false;
    }

    /**
     * Removes multiple items from the pool.
     *
     * @param array $keys
     * @return bool
     * @throws CacheInvalidArgumentException if any `$keys` aren't valid
     */
    public function deleteItems(array $keys)
    {
        foreach ($keys as $key) {
            if (!$this->deleteItem($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Persists a cache item immediately.
     *
     * @param CacheItemInterface $item
     * @return bool
     */
    public function save(CacheItemInterface $item)
    {
        if ($item instanceof CachedAccessToken) {
            // Convert the access token into a model
            $token  = $item->get();
            $scopes = [];
            foreach ($token->getScopes() as $scope) {
                $scopes[] = $scope->getIdentifier();
            }

            $user           = null;
            $userIdentifier = $token->getUserIdentifier();
            if (isset($userIdentifier)) {
                // TODO inject this mapper
                $user = UserMapper::getInstance()->findByUsername($userIdentifier);
            }
            $model = $this->accessTokenMapper
                ->createModel()
                ->setIdentifier($item->getKey())
                ->setExpiresAt(Alaress_Date_Format::newFromTimestamp($token->getExpiryDateTime()->getTimestamp()))
                ->setScopes($scopes)
                ->setUser($user)
                // TODO inject this mapper too
                ->setLtiTool(\Schoolbox\LTI\Provider\Mapper::getInstance()->findOneByColumn('client_id', $token->getClient()->getIdentifier()));
            $this->cache[$item->getKey()] = $item;
            try {
                $this->accessTokenMapper->save($model);
                $item->setDirty(false);
                return true;
            } catch (MapperException $e) {
                // noop: fall through
            }
        }
        return false;
    }

    /**
     * Sets a cache item to be persisted later.
     *
     * @param CacheItemInterface $item
     * @return bool
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if ($item instanceof CachedAccessToken) {
            $item->setDirty(true);
            $this->cache[$item->getKey()] = $item;
            return true;
        }
        return false;
    }

    /**
     * Persists any deferred cache items.
     *
     * @return bool
     */
    public function commit()
    {
        foreach ($this->cache as $item) {
            if ($item->isDirty()) {
                if (!$this->save($item)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Asserts `$key` is valid.
     *
     * Currently, that just means "is it a string"?
     *
     * @param mixed $key
     * @throws CacheInvalidArgumentException if $key isn't valid
     */
    private function assertKeyValid($key): void
    {
        if (!is_string($key)) {
            throw new CacheInvalidArgumentException(sprintf('Cache key must be a string: received %s', gettype($key)));
        }
    }
}
