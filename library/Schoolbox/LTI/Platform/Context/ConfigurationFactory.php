<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\LTI\Platform\Context;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Encoding\UnifyAudience;
use Lcobucci\JWT\Encoding\UnixTimestampDates;
use Lcobucci\JWT\Token\Builder;
use OAT\Library\Lti1p3Core\Security\Key\KeyInterface;

/**
 * This is to use UnixTimestampDates in ChainedFormatter instead of MicrosecondBasedDateConversion.
 * Some LTI tools (at least H5P) accepts only int as exp, iat dates.
 * As vendor/oat-sa/lib-lti1p3-core/src/Security/Jwt/Configuration/ConfigurationFactory.php is hard coded to use
 * MicrosecondBasedDateConversion, this class overrides it to use UnixTimestampDates.
 */
class ConfigurationFactory extends \OAT\Library\Lti1p3Core\Security\Jwt\Configuration\ConfigurationFactory
{
    public function create(?KeyInterface $signingKey = null, ?KeyInterface $verificationKey = null): Configuration
    {
        $configuration = parent::create($signingKey, $verificationKey);

        $builderFactory = static function () {
            return new Builder(
                new JoseEncoder(),
                new ChainedFormatter(
                    new UnifyAudience(),
                    new UnixTimestampDates()
                )
            );
        };

        return $configuration->withBuilderFactory($builderFactory);
    }
}
