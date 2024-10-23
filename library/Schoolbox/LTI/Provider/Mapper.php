<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\LTI\Provider;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class Mapper
 * @package Schoolbox\LTI\Provider
 * @method Model findById($identifierValue)
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('lti_provider');
        $this->hasModel(Model::class);

        // Common fields
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('lti_version', self::TYPE_STRING, self::COLUMN_ENUM, null, Model::LTI_VERSIONS);
        $this->uses('deployment_id', self::TYPE_STRING, self::COLUMN_UUID);
        $this->uses('target', self::TYPE_STRING, self::COLUMN_ENUM, null, Model::TARGETS);

        // LTI 1.3 fields
        $this->uses('client_id', self::TYPE_STRING);
        $this->uses('audience', self::TYPE_STRING);
        $this->uses('oidc_initiation_url', self::TYPE_STRING);
        $this->uses('launch_url', self::TYPE_STRING);
        $this->uses('deep_linking_url', self::TYPE_STRING);
        $this->uses('jwks_url', self::TYPE_STRING);
        $this->uses('public_key', self::TYPE_STRING);
        $this->uses('available_for_component', self::TYPE_BOOLEAN);
        $this->uses('available_for_assessment', self::TYPE_BOOLEAN);
        $this->joinsReverseMulti('lti_provider_id', RedirectUri\Mapper::class, 'redirectUris', 'redirectUri');

        // LTI 1.1 fields
        $this->uses('key', self::TYPE_STRING);
        $this->uses('secret', self::TYPE_STRING);
        $this->uses('xml', self::TYPE_STRING);
        $this->uses('icon', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('custom', self::TYPE_STRING);
        $this->uses('launch_type', self::TYPE_STRING);

        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Fetch and hydrate each provider's redirect URIs.
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $providers = parent::hydrateSelect($statement);

        // Fetch and group redirect URIs
        /** @var Collection<int, GenericCollection<RedirectUri\Model>> $groupedRedirectUris */
        $groupedRedirectUris = $this->getJoinMapper('redirectUri')
            ->findByColumn('lti_provider_id', $providers->getPropertyValues('id'))
            ->groupByCallback(fn (RedirectUri\Model $r) => $r->getLtiProvider()->getId());

        // Assign each provider's URI
        foreach ($providers as $provider) {
            if (isset($groupedRedirectUris[$provider->getId()])) {
                $provider->setRedirectUris($groupedRedirectUris[$provider->getId()]);
            }
        }

        return $providers;
    }

    /**
     * Find all LTI tools which can be used to select content for a homepage
     * component.
     *
     * @return GenericCollection|Model[]
     */
    public function findAllAvailableForComponent(): GenericCollection
    {
        return $this->findByPredicates(
            $this->getPredicateBuilder()
                 ->any(
                     $this->getPredicateBuilder()
                          ->equals('lti_version', Model::LTI_VERSION_1_1)
                          ->all(
                              $this->getPredicateBuilder()
                                   ->equals('lti_version', Model::LTI_VERSION_1_3)
                                   ->isTrue('available_for_component')
                          )
                 )
        );
    }

    /**
     * Find all LTI tools which can be used to select content for an assessment.
     *
     * @return GenericCollection|Model[]
     */
    public function findAllAvailableForAssessment(): GenericCollection
    {
        return $this->findByPredicates(
            $this->getPredicateBuilder()
                 ->equals('lti_version', Model::LTI_VERSION_1_3)
                 ->isTrue('available_for_assessment')
        );
    }
}
