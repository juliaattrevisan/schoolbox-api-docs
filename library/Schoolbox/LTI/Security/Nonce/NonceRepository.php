<?php

/**
 * This class is a replacement for:
 * https://github.com/oat-sa/lib-lti1p3-core/blob/master/src/Security/Nonce/NonceRepository.php
 *
 * This replacement class was created because the version linked to above saved items using cache keys that contained
 * characters reserved by php-cache for future extension ({}()/\@:).  This caused errors when the php-cache
 * AbstractCachePool class tried to validate the key.
 *
 * The error whose fix required creation of this class was:
 *    "Invalid key: "lti1p3-nonce-d/ODUteXkCMTyj4Cc2Il". The key contains one or more characters reserved for future extension: {}()/\@:"
 *    (thrown from vendor/cache/adapter-common/AbstractCachePool->validateKey().)
 *    (https://github.com/php-cache/cache/blob/4f777b28a8c91ce8ed7556539cddee99c6aa1621/src/Adapter/Common/AbstractCachePool.php)
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Security\Nonce;

use OAT\Library\Lti1p3Core\Security\Nonce\Nonce;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceInterface;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class NonceRepository implements NonceRepositoryInterface
{
    /**
     * These characters have been reserved by LTI for future extension: {}()/\@:
     * This array provides a mapping from reserved character to replacement string.
     */
    private const PHPCACHE_RESERVED_CHARACTERS_MAP = [
        'openbrace'     => 'openbrace-'    ,
        'closedbrace'   => 'closedbrace-'  ,
        'openbracket'   => 'openbracket-'  ,
        'closedbracket' => 'closedbracket-',
        'slash'         => 'slash-'        ,
        'backslash'     => 'backslash-'    ,
        'ampersand'     => 'ampersand-'    ,
        'colon'         => 'colon-'        ,

        // The key/value pairs below are the actual reserved characters and replacement strings.
        // The extra key/value pairs above have been added to prevent collisions of this kind:
        //    '('           (transform)=> 'openbracket'
        //    'openbracket' (transform)=> 'openbracket' (collision with above row!)
        //
        // With the extra keys above the potential collisions are avoided as follows:
        //    '('            (transform)=> 'openbracket'
        //    'openbracket'  (transform)=> 'openbracket-'
        //    'openbracket-' (transform)=> 'openbracket--'
        //
        // It is important that the extra keys are positioned before the actual keys in the array.
        // The reason is to avoid two transformations being done as the string replaces are done in order.
        // E.g.
        //    We want:       '(' => 'openbracket'.
        //    We don't want: '(' => 'openbracket' => 'openbracket-'.
        '{' => 'openbrace'    ,
        '}' => 'closedbrace'  ,
        '(' => 'openbracket'  ,
        ')' => 'closedbracket',
        '/' => 'slash'        ,
        '\\' => 'backslash'   ,
        '@' => 'ampersand'    ,
        ':' => 'colon'        ,
    ];

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
        $originalItemKey = sprintf('%s-%s', self::CACHE_PREFIX, $value);
        $transformedItemKey = self::replaceReservedCharacters($originalItemKey);
    echo "Finding nonce with key '$transformedItemKey'...";

        $item = $this->cache->getItem($transformedItemKey);

    echo "Found nonce '" . (new Nonce($item->get()))->getValue() . "'\n";
        return $item->isHit() ? new Nonce($item->get()) : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function save(NonceInterface $nonce): void
    {
        $originalItemKey = sprintf('%s-%s', self::CACHE_PREFIX, $nonce->getValue());
        $transformedItemKey = self::replaceReservedCharacters($originalItemKey);

    echo "Saving nonce with original key '$originalItemKey', transformed key '$transformedItemKey'\n";
        $item = $this->cache->getItem($transformedItemKey);

        $this->cache->save(
            $item->set($nonce->getValue())->expiresAt($nonce->getExpiredAt())
        );
    }

    /**
     * Return a transformed version of the given string.
     * The transformed version will have each reserved character found in the given string replaced with a string
     * representation that does not include any reserved characters.
     * This method guarantees that each item key will map to a single replacement key.
     *
     * Cache key validation rules from php-cache AbstractCachePool->validateKey():
     *  - Cache key must be string.
     *  - Cache key cannot be an empty string.
     *  - The key contains one or more characters reserved for future extension: {}()/\@:.
     *
     * @param string $itemKey
     * @return string
     */
    private static function replaceReservedCharacters(string $itemKey): string
    {
        return str_replace(
            array_keys(self::PHPCACHE_RESERVED_CHARACTERS_MAP),
            array_values(self::PHPCACHE_RESERVED_CHARACTERS_MAP),
            $itemKey
        );
    }
}
