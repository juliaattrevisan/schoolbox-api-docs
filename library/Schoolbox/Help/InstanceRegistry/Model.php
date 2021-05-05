<?php
/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Help\InstanceRegistry;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

/**
 * Model for instance registry records.
 *
 * This record contains all information which this instance needs to know about
 * another Schoolbox instance.
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * The instance's instance ID: used as a unique identifier.
     */
    private string $instanceId;

    /**
     * The instance's school code (as stored in `config.site_school_code`):
     * will be used to determine the domain name to forward requests to after
     * extracting the school code from a user's external ID.
     */
    private string $siteSchoolCode;

    /**
     * The instance's domain name (as generated from `config.site_hostname` and
     * `config.site_domain`): will be used to craft HTTP requests to be sent to
     * this instance.
     */
    private string $domainName;

    /**
     * The instance's JWT public key (as stored in `config.jwt_public_key`):
     * used to verify the signature of JWTs which come from an instance.
     */
    private string $jwtPublicKey;

    /**
     * @return string
     */
    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    /**
     * @param string $instanceId
     * @return static
     */
    public function setInstanceId(string $instanceId): self
    {
        $this->instanceId = $instanceId;
        return $this;
    }

    /**
     * @return string
     */
    public function getSiteSchoolCode(): string
    {
        return $this->siteSchoolCode;
    }

    /**
     * @param string $siteSchoolCode
     * @return static
     */
    public function setSiteSchoolCode(string $siteSchoolCode): self
    {
        $this->siteSchoolCode = $siteSchoolCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getDomainName(): string
    {
        return $this->domainName;
    }

    /**
     * @param string $domainName
     * @return static
     */
    public function setDomainName(string $domainName): self
    {
        $this->domainName = $domainName;
        return $this;
    }

    /**
     * @return string
     */
    public function getJwtPublicKey(): string
    {
        return $this->jwtPublicKey;
    }

    /**
     * @param string $jwtPublicKey
     * @return static
     */
    public function setJwtPublicKey(string $jwtPublicKey): self
    {
        $this->jwtPublicKey = $jwtPublicKey;
        return $this;
    }
}
