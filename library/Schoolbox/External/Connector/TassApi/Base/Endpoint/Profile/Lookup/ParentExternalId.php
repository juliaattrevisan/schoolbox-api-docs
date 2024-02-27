<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup;

/**
 * Builds a pair of schoolbox external ids from a tass family id, with
 * all the parent/spouse prefixes as needed.
 */
class ParentExternalId
{
    /**
     * Default spouse prefix
     *
     * @var string
     */
    private const DEFAULT_SPOUSE_PREFIX = 'S';


    /**
     * Constructor.
     *
     * @param string $spousePrefix Set in Admin. This var deconflicts shared usernames by putting a letter in front,
     *                             because tass doesn't treat parents independently of families.
     */
    public function __construct(private string $spousePrefix)
    {
        /* Calculate a spouse prefix if one was set; otherwise use a default
         * Shanked from that Tass Helper class
         */
        $this->spousePrefix = '' !== $spousePrefix ? $spousePrefix : self::DEFAULT_SPOUSE_PREFIX;
    }


    /**
     * Apply parent prefix based on their spouseness and suffix based on their sfa_num value
     * I guess sfa_num stands for Split Family Access number or something
     *
     * @param string $node
     * @param string $identifier
     * @param string $sfaNum
     * @return string
     */
    public function derive(string $node, string $identifier, string $sfaNum): string
    {
        /* Do some funny prefix stuff for parents
         */
        if (Family::FATHER_NODE === $node) {
            $externalId = "P{$identifier}";
        } else {
            $externalId = "{$this->spousePrefix}{$identifier}";
        }

        /* If this was a split family, chuck a $sfaNum into the mix
         *
         * After 99,999 students pass through a tass system, this scheme will stop working
         * due to collisions between old ids and new ones with prefix
         *
         * P100001 (parent of student 10000 + split family 1) == P100001 (parent of student 100001)
         */
        if ('' !== $sfaNum) {
            $externalId .= $sfaNum ;
        }

        return $externalId;
    }
}
