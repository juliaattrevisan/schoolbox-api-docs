<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Custom;

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
 * Currently, it supports translation of:
 * * LTI user variables (ID and username only) for both acting and actual user
 * * LTI context variables
 * * LTI resource link variables
 * * Tool platform variables
 *
 * @see https://www.imsglobal.org/spec/lti/v1p3/#custom-properties-and-variable-substitution
 */
class ClaimFactory
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
        $new->translations['$Context.type']  = implode(' ', $contextClaim->getTypes());
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
        $new                                                          = (clone $this);
        $new->translations['$ToolPlatformInstance.productFamilyCode'] = $platformInstanceClaim->getProductFamilyCode();
        $new->translations['$ToolPlatformInstance.version']           = $platformInstanceClaim->getVersion();
        $new->translations['$ToolPlatformInstance.guid']              = $platformInstanceClaim->getGuid();
        $new->translations['$ToolPlatformInstance.name']              = $platformInstanceClaim->getName();
        $new->translations['$ToolPlatformInstance.description']       = $platformInstanceClaim->getDescription();
        $new->translations['$ToolPlatformInstance.url']               = $platformInstanceClaim->getUrl();
        $new->translations['$ToolPlatformInstance.contactEmail']      = $platformInstanceClaim->getContactEmail();
        return $new;
    }

    /**
     * Build custom claim from the given set of custom fields.
     *
     * @param array|null $customFields
     * @return Claim|null
     */
    public function build(?array $customFields): ?Claim
    {
        if (empty($customFields)) {
            return null;
        }
        return new Claim(
            array_map(
                Closure::fromCallable([$this, 'translate']),
                $customFields,
            )
        );
    }

    /**
     * Build custom claim from the assessment's custom fields.
     *
     * @param LtiAssessment $assessment
     * @return Claim|null
     */
    public function buildForAssessment(LtiAssessment $assessment): ?Claim
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
