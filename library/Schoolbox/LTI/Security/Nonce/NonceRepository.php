<?php

/**
 * This class is a replacement for:
 * https://github.com/oat-sa/lib-lti1p3-core/blob/master/src/Security/Nonce/NonceRepository.php
 *
 * This replacement class was created because the version linked to above saved items using cache keys that contained
 * characters reserved by LTI for future extension ({}()/\@:).
 *
 * The error whose fix required creation of this class was:
 *    "Invalid key: "lti1p3-nonce-d/ODUteXkCMTyj4Cc2Il". The key contains one or more characters reserved for future extension: {}()/\@:"
 *    (thrown from vendor/cache/adapter-common/AbstractCachePool->validateKey().)
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Security\Nonce;

use OAT\Library\Lti1p3Core\Security\Nonce\NonceRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class NonceRepository implements NonceRepositoryInterface
{
    private const CACHE_PREFIX = 'lti1p3-nonce';

    /** @var CacheItemPoolInterface */
    private $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function find(string $value): ?NonceInterface
    {
        // TODO: Transform cache key used for retrieval into one
        //       guaranteed not to contain reserved characters {}()/\@.

        $item = $this->cache->getItem(sprintf('%s-%s', self::CACHE_PREFIX, $value));

        return $item->isHit() ? new Nonce($item->get()) : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function save(NonceInterface $nonce): void
    {
        $item = $this->cache->getItem(sprintf('%s-%s', self::CACHE_PREFIX, $nonce->getValue()));

        // TODO: Transform cache key used for storage into one
        //       guaranteed not to contain reserved characters {}()/\@.

        $this->cache->save(
            $item->set($nonce->getValue())->expiresAt($nonce->getExpiredAt())
        );
    }
}
