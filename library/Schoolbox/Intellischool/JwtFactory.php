<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Intellischool;

use Alaress_Date_Format;
use Firebase\JWT\JWT;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Factory for JWTs for authenticating with Intellischool.
 */
class JwtFactory
{
    /**
     * LTI endpoint for Intellischool services: this remains constant for all
     * Schoolbox instances.
     */
    public const LTI_ENDPOINT = 'https://core.intellischool.net/auth/lti';

    private const COHORT_PREFIX_CLASS = 'class';
    private const COHORT_PREFIX_SUBJECT = 'subject';
    private const COHORT_PREFIX_YEARLEVEL = 'year_level';
    private const ROLE_NAMESPACE = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person';

    /**
     * Private key for signing JWTs for Intellischool consumption.
     *
     * Yes, this is supposed to be a private key: JWTs are signed with a shared
     * private key, which is then decoded using the recipient's non-shared
     * public key.
     */
    private string $privateKey;

    /**
     * Signing algorithm for Intellischool JWT private key.
     */
    private string $privateKeySigningAlgorithm;

    /**
     * Deployment ID for this instance's Intellischool integration.
     */
    private string $deploymentId;

    /**
     * This instance's base URL for Intellischool learner dashboards.
     */
    private string $learnerDashboardBaseUrl;

    /**
     * This instance's base URL for Intellischool cohort dashboards.
     */
    private string $cohortDashboardBaseUrl;

    /**
     * The fqdn for the issuer
     */
    private string $jwtIssuer;

    /**
     * Constructor
     *
     * @param string $privateKey
     * @param string $privateKeySigningAlgorithm
     * @param string $deploymentId
     * @param string $learnerDashboardBaseUrl
     * @param string $cohortDashboardBaseUrl
     * @param string $jwtIssuer
     */
    public function __construct(
        string $privateKey,
        string $privateKeySigningAlgorithm,
        string $deploymentId,
        string $learnerDashboardBaseUrl,
        string $cohortDashboardBaseUrl,
        string $taskDashboardBaseUrl,
        string $jwtIssuer
    ) {
        $this->privateKey                 = $privateKey;
        $this->privateKeySigningAlgorithm = $privateKeySigningAlgorithm;
        $this->deploymentId               = $deploymentId;
        $this->learnerDashboardBaseUrl    = $learnerDashboardBaseUrl;
        $this->cohortDashboardBaseUrl     = $cohortDashboardBaseUrl;
        $this->jwtIssuer               = $jwtIssuer;
    }

    /**
     * Build a JWT for the learner profile dashboard.
     *
     * @param User $currentUser
     * @param User $learner
     * @return string
     */
    public function buildForLearnerProfile(User $currentUser, User $learner): string
    {
        $claims = $this->buildBaseClaims($currentUser);
        $claims['https://purl.imsglobal.org/spec/lti/claim/target_link_uri'] = sprintf(
            '%s/%s',
            $this->learnerDashboardBaseUrl,
            $learner->getExternalId(),
        );
        return JWT::encode($claims, $this->privateKey, $this->privateKeySigningAlgorithm);
    }

    /**
     * Build a JWT for the class dashboard.
     *
     * @param User   $currentUser
     * @param string $classCode
     * @return string
     */
    public function buildForClass(User $currentUser, string $classCode): string
    {
        return $this->buildForCohort($currentUser, self::COHORT_PREFIX_CLASS, $classCode);
    }

    /**
     * Build a JWT for the subject dashboard.
     *
     * @param User   $currentUser
     * @param string $subjectCode
     * @return string
     */
    public function buildForSubject(User $currentUser, string $subjectCode): string
    {
        return $this->buildForCohort($currentUser, self::COHORT_PREFIX_SUBJECT, $subjectCode);
    }

    /**
     * Build a JWT for the year level dashboard.
     *
     * @param User   $currentUser
     * @param string $yearLevel
     * @return string
     */
    public function buildForYearLevel(User $currentUser, string $yearLevel): string
    {
        return $this->buildForCohort($currentUser, self::COHORT_PREFIX_YEARLEVEL, $yearLevel);
    }

    /**
     * Build a JWT for the Admin dashboard.
     *
     * @return string
     */
    public function buildForAdminView(User $currentUser): string
    {
        $claims = $this->buildBaseClaims($currentUser);
        $claims['https://purl.imsglobal.org/spec/lti/claim/target_link_uri'] = 'https://analytics.intellischool.cloud/admin';
        return JWT::encode($claims, $this->privateKey, $this->privateKeySigningAlgorithm);
    }

    /**
     * Build a JWT for the given cohort dashboard.
     *
     * @param User   $currentUser
     * @param string $cohortType
     * @param string $cohortIdentifier
     * @return string
     */
    public function buildForCohort(User $currentUser, string $cohortType, string $cohortIdentifier): string
    {
        $claims = $this->buildBaseClaims($currentUser);
        $claims['https://purl.imsglobal.org/spec/lti/claim/target_link_uri'] = sprintf(
            '%s/%s:%s',
            $this->cohortDashboardBaseUrl,
            $cohortType,
            urlencode($cohortIdentifier),
        );
        return JWT::encode($claims, $this->privateKey, $this->privateKeySigningAlgorithm);
    }

    /**
     * Build base claims for the current user.
     *
     * @param User $currentUser
     * @return array
     */
    private function buildBaseClaims(User $currentUser): array
    {
        $userClaims = [
            // TODO is this the correct sub claim?
            'sub'                                             => $currentUser->getEmail(),
            'name'                                            => $currentUser->getFullName(),
            'given_name'                                      => $currentUser->getFirstname(),
            'middle_name'                                     => null,
            'family_name'                                     => $currentUser->getLastname(),
            'email'                                           => $currentUser->getEmail(),
            'https://purl.imsglobal.org/spec/lti/claim/roles' => [],
        ];
        switch ($currentUser->getRole()->getType()) {
            case Role::STUDENT:
                $userClaims['https://purl.imsglobal.org/spec/lti/claim/roles'][] = self::ROLE_NAMESPACE . '#Student';
                break;
            case Role::STAFF:
                $userClaims['https://purl.imsglobal.org/spec/lti/claim/roles'][] = self::ROLE_NAMESPACE . '#Staff';
                break;
            case Role::PARENT:
                // TODO is this the right role for parents?
                $userClaims['https://purl.imsglobal.org/spec/lti/claim/roles'][] = self::ROLE_NAMESPACE . '#Other';
                break;
            case Role::GUEST:
                $userClaims['https://purl.imsglobal.org/spec/lti/claim/roles'][] = self::ROLE_NAMESPACE . '#Guest';
                break;
            default:
                $userClaims['https://purl.imsglobal.org/spec/lti/claim/roles'][] = self::ROLE_NAMESPACE . '#None';
                break;
        }

        $defaultClaims = [
            'iss'                                                           => $this->jwtIssuer,
            'aud'                                                           => 'https://core.intellischool.net/auth/lti',
            'https://purl.imsglobal.org/spec/lti/claim/version'             => '1.3.0',
            'https://purl.imsglobal.org/spec/lti/claim/message_type'        => 'LtiResourceLinkRequest',
            'https://purl.imsglobal.org/spec/lti/claim/deployment_id'       => $this->deploymentId,
            'https://purl.imsglobal.org/spec/lti/claim/resource_link'       => ['id' => '1',],
            'https://purl.imsglobal.org/spec/lti/claim/launch_presentation' => ['document_target' => 'iframe'],
        ];

        $now        = Alaress_Date_Format::now();
        $timeClaims = [
            'iat' => $now->getUnixTimeStamp(),
            'exp' => (clone $now)->addSeconds(300)->getUnixTimeStamp(),
        ];
        return array_merge($userClaims, $timeClaims, $defaultClaims);
    }

    /**
     * Build a JWT for the Dashboard Component
     * @link https://analytics.intellischool.cloud/console/schoolbox
     * @return string
     */
    public function buildForDashboardComponent(User $currentUser): string
    {
        $claims = $this->buildBaseClaims($currentUser);
        $claims['https://purl.imsglobal.org/spec/lti/claim/target_link_uri'] = 'https://analytics.intellischool.cloud/console/schoolbox';
        return JWT::encode($claims, $this->privateKey, $this->privateKeySigningAlgorithm);
    }
}
