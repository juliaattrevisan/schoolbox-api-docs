<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use Closure;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\PlatformInstanceClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\User;

/**
 * Factory for building custom claims.
 *
 * This factory also handles the variable substitution that is required in
 * custom claims.
 *
 * @see https://www.imsglobal.org/spec/lti/v1p3/#custom-properties-and-variable-substitution
 */
class CustomClaimFactory
{
    /**
     * Translations from variables to custom values.
     */
    private $translations = [];

    /**
     * @see https://www.imsglobal.org/spec/lti/v1p3/#lti-user-variables
     *
     * @param User $user
     * @return self
     */
    public function withUser(User $user): self
    {
        $new                                     = (clone $this);
        $new->translations['$User.id']           = $user->getExternalId();
        $new->translations['$User.username']     = $user->getUsername();
        $new->translations['$User.image']        = null;
        $new->translations['$User.org']          = null;
        $new->translations['$User.scope.mentor'] = null;
        return $new;
    }

    /**
     * @see https://www.imsglobal.org/spec/lti/v1p3/#lti-user-variables
     *
     * @param User $user
     * @return self
     */
    public function withActualUser(User $user): self
    {
        $new                                           = (clone $this);
        $new->translations['$ActualUser.id']           = $user->getExternalId();
        $new->translations['$ActualUser.username']     = $user->getUsername();
        $new->translations['$ActualUser.image']        = null;
        $new->translations['$ActualUser.org']          = null;
        $new->translations['$ActualUser.scope.mentor'] = null;
        return $new;
    }

    /**
     * @see https://www.imsglobal.org/spec/lti/v1p3/#lti-resourcelink-variables
     *
     * @param ResourceLinkClaim $resourceLinkClaim
     * @return self
     */
    public function withResourceLink(ResourceLinkClaim $resourceLinkClaim): self
    {
        $new                                            = (clone $this);
        $new->translations['$ResourceLink.id']          = $resourceLinkClaim->getIdentifier();
        $new->translations['$ResourceLink.title']       = $resourceLinkClaim->getTitle();
        $new->translations['$ResourceLink.description'] = $resourceLinkClaim->getDescription();
        return $new;
    }

    /**
     * @see https://www.imsglobal.org/spec/lti/v1p3/#lti-context-variable
     *
     * @param ContextClaim $contextClaim
     * @return self
     */
    public function withContext(ContextClaim $contextClaim): self
    {
        $new                                 = (clone $this);
        $new->translations['$Context.id']    = $contextClaim->getIdentifier();
        $new->translations['$Context.type']  = implode(',', $contextClaim->getTypes());
        $new->translations['$Context.label'] = $contextClaim->getLabel();
        $new->translations['$Context.title'] = $contextClaim->getTitle();
        return $new;
    }

    /**
     * @see https://www.imsglobal.org/spec/lti/v1p3/#tool-platform-variables
     *
     * @param PlatformInstanceClaim $platformInstanceClaim
     * @return self
     */
    public function withPlatformInstance(PlatformInstanceClaim $platformInstanceClaim): self
    {
        $new                                                  = (clone $this);
        $new->translations['$ToolPlatform.productFamilyCode'] = $platformInstanceClaim->getProductFamilyCode();
        $new->translations['$ToolPlatform.version']           = $platformInstanceClaim->getVersion();
        $new->translations['$ToolPlatform.guid']              = $platformInstanceClaim->getGuid();
        $new->translations['$ToolPlatform.name']              = $platformInstanceClaim->getName();
        $new->translations['$ToolPlatform.description']       = $platformInstanceClaim->getDescription();
        $new->translations['$ToolPlatform.url']               = $platformInstanceClaim->getUrl();
        $new->translations['$ToolPlatform.contactEmail']      = $platformInstanceClaim->getContactEmail();
        return $new;
    }

    /**
     * Build custom fields
     *
     * @param array|null $customFields
     * @return array|null
     */
    public function build(?array $customFields): ?array
    {
        if (!isset($customFields)) {
            return null;
        }
        return array_map(
            Closure::fromCallable([$this, 'translate']),
            $customFields,
        );
    }

    /**
     * @param LtiAssessment $assessment
     * @return array|null
     */
    public function buildForAssessment(LtiAssessment $assessment): ?array
    {
        return $this->build($assessment->getCustomFields());
    }

    /**
     * Performs variable substitution on this custom field's value.
     *
     * @param string $value
     * @return string
     */
    private function translate(string $value): string
    {
        return strtr(
            $value,
            array_filter(
                $this->translations,
                static function ($v) {
                    return isset($v);
                }
            )
        );
    }
}
