<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile\Profile;
use Schoolbox\External\Connector\Watchlist;

/**
 * Grab all the Language proficiencies. We can presume that English
 * is in node 1, perhaps, but we double-check to be sure anyway.
 * ESL is when the node corresponding to English has a false setting
 * for is_primary.
 */
class Language
{
    /**
     * A const corresponding to the language node for English
     * This node can appear anywhere in the value list so you
     * have to inspect it manually
     */
    private const ENGLISH_DESCRIPTION = 'english';


    /**
     * Records might reference a language that doesn't exist in lookups
     *
     * Replace with a generic "Unknown" for now, as it's not apparent that
     * this happens in a Prod Veracross; their sandbox api has a bunch of fixer
     * scripts disabled for whatever reason, and there's a bunch of schools
     * in this area mangling content
     */
    private const UNKNOWN_DESCRIPTION = 'Unknown';


    /**
     * Remember the output we might have previously generated
     *
     * @var array|null
     */
    private ?array $lookup = null;


    /**
     * Constructor
     *
     * @param IApi $api
     * @param array $watchlist
     */
    public function __construct(private IApi $api, private array $watchlist)
    {
    }


    /**
     * Generate a compiled list of records, indexed by an identifier
     *
     * @return array
     */
    public function generate(): array
    {
        /* If we generated this lookup previously, just recycle it
         */
        if (null === $this->lookup) {
            $this->lookup = [];

            /* Rebuild lookups on each page
             */
            $lookupConverter = new ValueListConverter();

            /* English node, wherever that is, must have is_primary set to true
             * to qualify someone as native speaker. Everyone else is EAL
             */
            $generator = $this->api->retrieve('GET', 'admission/languages', Profile::SCOPE);

            foreach ($generator as $page) {
                $valueLists = $lookupConverter->generate($page['value_lists']);

                /* Avengers Assemble!
                 */
                foreach ($page['data'] as $record) {
                    $externalId = trim((string)$record['person_id']);

                    /* Only import for specific users
                     */
                    if (!(new Watchlist())->isOnWatchlist($externalId, $this->watchlist)) {
                        continue;
                    }

                    /* Overwrite with lookupped values
                     */
                    $language = $valueLists['language'][$record['language']] ?? self::UNKNOWN_DESCRIPTION;

                    /* Everyone is not a native speaker until otherwise decided
                     */
                    $eal = true;

                    /* Analyse the node to decide nativeness
                     */
                    if ($record['is_primary'] && (self::ENGLISH_DESCRIPTION === strtolower($language))) {
                        $eal = false;
                    }

                    /* Simplify usage since we only care if English was is_primary
                     */
                    $this->lookup[$externalId] = $eal;
                }
            }
        }

        return $this->lookup;
    }
}
