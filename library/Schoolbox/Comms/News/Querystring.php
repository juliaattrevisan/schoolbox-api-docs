<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News;

/**
 * Class Querystring
 *
 * Rebuild a querystring or find out the particular pieces
 *  - compact mode
 *  - page number
 *  - limit
 *  - hide paginator
 * Encapsulate in a stringable class
 * Encapsulate helpers
 *
 * @package Schoolbox\Comms\News
 */
class Querystring
{

    /**
     * Querystring param for the Compact Mode option
     */
    public const COMPACT_MODE = 'c';

    /**
     * Querystring param for the current Page Number chosen
     */
    public const PAGE_NUMBER = 'p';

    /**
     * Querystring param for the current Limit chosen
     */
    public const LIMIT = 'l';

    /**
     * Querystring param for the current topic chosen
     */
    public const TOPIC_SLUG = 'topic';

    /**
     * Querystring param for the Hide Pagination option
     */
    public const HIDE_PAGINATOR = 'hp';

    /**
     * Querystring param for the Folder option
     */
    public const FOLDER = 'f';

    /**
     * Querystring param for the status filter (on the /me page)
     */
    public const STATUS = 'status';

    /**
     * Querystring param for the Referrer (used in stats)
     */
    public const REFERRER = 'ref';

    /**
     * Querystring param for return url
     */
    public const RETURN_URL = 'returnUrl';

    /**
     * Querystring param for entity type
     */
    public const ENTITY_TYPE = 'type';

    /**
     * Querystring param for component id
     */
    public const COMPONENT_ID = 'cid';

    /**
     * An array of key/value pairs corresponding to GET params
     *
     * Will be string if supplied, null otherwise
     *
     * @var string[]|null[]
     */
    private $querystring;

    /**
     * Querystring constructor.
     *
     * @param string[] $data
     */
    public function __construct(array $data)
    {
        $this->querystring = [
            self::COMPACT_MODE   => $data[self::COMPACT_MODE] ?? null,
            self::TOPIC_SLUG     => $data[self::TOPIC_SLUG] ?? null,
            self::PAGE_NUMBER    => $data[self::PAGE_NUMBER] ?? null,
            self::LIMIT          => $data[self::LIMIT] ?? null,
            self::HIDE_PAGINATOR => $data[self::HIDE_PAGINATOR] ?? null,
            self::FOLDER         => $data[self::FOLDER] ?? null,
            self::REFERRER       => $data[self::REFERRER] ?? null,
            self::STATUS         => $data[self::STATUS] ?? null,
            self::ENTITY_TYPE    => $data[self::ENTITY_TYPE] ?? null,
            self::RETURN_URL     => $data[self::RETURN_URL] ?? null,
            self::COMPONENT_ID   => $data[self::COMPONENT_ID] ?? null,
        ];
    }


    /**
     * Current compact mode setting
     *
     * @return mixed
     */
    public function getCompactMode(): ?string
    {
        return $this->querystring[self::COMPACT_MODE];
    }


    /**
     * Currently-chosen topic slug
     *
     * @return mixed
     */
    public function getSlug(): ?string
    {
        return $this->querystring[self::TOPIC_SLUG];
    }


    /**
     * Current page index
     *
     * @return int
     */
    public function getPageNum(): int
    {
        return $this->determinePageNum($this->querystring[self::PAGE_NUMBER]);
    }


    /**
     * Number of articles to obtain and display
     *
     * @return mixed
     */
    public function getLimit(): ?string
    {
        return $this->querystring[self::LIMIT];
    }


    /**
     * The option to determine whether paginator shows in the ui
     *
     * @return bool
     */
    public function getHidePaginator(): bool
    {
        return (bool)$this->querystring[self::HIDE_PAGINATOR];
    }


    /**
     * Get the folder id if one was specified
     *
     * @return null|int
     */
    public function getFolderId(): ?int
    {
        return $this->querystring[self::FOLDER] === null ? null : (int)$this->querystring[self::FOLDER];
    }


    /**
     * Get the status
     *
     * @return mixed
     */
    public function getStatus(): ?string
    {
        return $this->querystring[self::STATUS];
    }


    /**
     * Get the referrer string if one was specified
     *
     * @return mixed
     */
    public function getReferrer(): ?string
    {
        return $this->querystring[self::REFERRER];
    }


    /**
     * Get the entity type
     *
     * @return null|string
     */
    public function getEntityType(): ?string
    {
        return $this->querystring[self::ENTITY_TYPE];
    }


    /**
     * Get the return url
     *
     * @return null|string
     */
    public function getReturnUrl(): ?string
    {
        return $this->querystring[self::RETURN_URL];
    }


    /**
     * @return string|null
     */
    public function getComponentId(): ?string
    {
        return $this->querystring[self::COMPONENT_ID];
    }


    /**
     * @param null|string $referrer
     * @return $this
     */
    public function setReferrer(?string $referrer): self
    {
        $this->querystring[self::REFERRER] = $referrer;
        return $this;
    }


    /**
     * Set entity type
     *
     * @param null|string $entityType
     * @return $this
     */
    public function setEntityType(?string $entityType): self
    {
        $this->querystring[self::ENTITY_TYPE] = $entityType;
        return $this;
    }


    /**
     * Set the folder id if one was specified
     *
     * @param int $folderId
     * @return $this
     */
    public function setFolderId(int $folderId): self
    {
        $this->querystring[self::FOLDER] = (string)$folderId;
        return $this;
    }


    /**
     * Set the return url
     *
     * @param null|string $returnUrl
     * @return $this
     */
    public function setReturnUrl(?string $returnUrl): self
    {
        $this->querystring[self::RETURN_URL] = $returnUrl;
        return $this;
    }


    /**
     * @param string|null $componentId
     * @return $this
     */
    public function setComponentId(?string $componentId): self
    {
        $this->querystring[self::COMPONENT_ID] = $componentId;
        return $this;
    }


    /**
     * Array representation of the params
     *
     * @return string[]|null[]
     */
    public function toArray(): array
    {
        return $this->querystring;
    }


    /**
     * String representation of the original values provided
     * Remove keys that have empty values
     *
     * @return string
     */
    public function __toString(): string
    {
        return http_build_query(array_filter($this->querystring, static function (?string $value): bool {
            return trim($value ?? '') !== '';
        }));
    }


    /**
     * The page number to obtain; default to 1
     * Otherwise use the page number provided
     *
     * @param null|string $param
     * @return int
     */
    private function determinePageNum(?string $param): int
    {
        return (int)$param ?: 1;
    }


    /**
     * Encode a url
     * TODO: merging library/Schoolbox/Learning/Assessment/Redirect.php ?
     *
     * @param $url
     * @return null|string
     */
    public static function encode(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        return urlencode(base64_encode($url));
    }


    /**
     * Decode an encoded redirect url
     *
     * @param $encoded
     * @return null|string
     */
    public static function decode(?string $encoded): ?string
    {
        if ($encoded === null) {
            return null;
        }

        return base64_decode(urldecode($encoded));
    }
}
