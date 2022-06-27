<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\EmailDKIM;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

/**
 * Class Model
 *
 * @package Schoolbox\Admin\DKIM
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    private string $domain  = '';
    private string $private = '';
    private string $public  = '';

    public const  SELECTOR         = 'schoolbox';
    private const DOMAIN_KEY       = '_domainkey';
    private const VALUE_FORMAT     = 'v=DKIM1;k=rsa;p=%s';
    private const KEY_HEADER_BEGIN = '-----BEGIN PUBLIC KEY-----';
    private const KEY_HEADER_END   = '-----END PUBLIC KEY-----';

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @param string $domain
     * @return static
     */
    public function setDomain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * @return string
     */
    public function getPrivate(): string
    {
        return $this->private;
    }

    /**
     * @param string $private
     * @return static
     */
    public function setPrivate(string $private): self
    {
        $this->private = $private;
        return $this;
    }

    /**
     * @return string
     */
    public function getPublic(): string
    {
        return $this->public;
    }

    /**
     * @param string $public
     * @return static
     */
    public function setPublic(string $public): self
    {
        $this->public = $public;
        return $this;
    }

    /**
     * @return string
     */
    public function getDKIMName(): string
    {
        return sprintf('%s.%s.%s.', self::SELECTOR, self::DOMAIN_KEY, $this->getDomain());
    }

    /**
     * @return string
     */
    public function getDKIMValue(): string
    {
        $public = trim(str_replace(self::KEY_HEADER_END, '', str_replace(self::KEY_HEADER_BEGIN, '', $this->getPublic())));
        return sprintf(self::VALUE_FORMAT, $public);
    }

    /**
     * @return array
     */
    public function getDKIMValueSplit(): array
    {
        return str_split($this->getDKIMValue(), 255);
    }
}
