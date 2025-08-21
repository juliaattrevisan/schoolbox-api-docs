<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile\Profile;
use Schoolbox\External\Connector\Watchlist;
use Schoolbox\External\Profile as UserProfile;

class Photo
{
    /**
     * Remember the output we might have previously generated
     *
     * @var array|null
     */
    private ?array $lookup = null;


    /**
     * Constructor
     *
     * @param UserProfile\Mapper $baseProfileMapper
     * @param IApi $api
     * @param array $watchlist
     */
    public function __construct(
        private UserProfile\Mapper $baseProfileMapper,
        private IApi $api,
        private array $watchlist
    ) {
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
            /* A list of existing externalIds and when their profiles were last touched
             */
            $existing = [];

            /* Generate a mega lookup of modified times to save requesting this iteratively
             */
            $stmt = $this->baseProfileMapper->getDatabase()->executeQuery("
                SELECT external_id, updated_at, portrait_hash
                FROM {$this->baseProfileMapper->getBaseTableName()}
            ");

            foreach ($stmt->fetchAll() as $row) {
                $existing[$row['external_id']] = [
                    'modifiedAt' => new \Alaress_Date_Format($row['updated_at'], \Alaress_Date_Format::DATABASE_FORMAT),
                    'portraitHash' => $row['portrait_hash'],
                ];
            }

            /* Find out what veracross files exist and when they were last touched
             */
            $this->lookup = [];

            $generator = $this->api->retrieve('GET', 'person_photos', Profile::SCOPE);

            foreach ($generator as $page) {
                /* Avengers Assemble!
                 */
                foreach ($page['data'] as $record) {
                    $externalId = trim((string)$record['person_id']);

                    /* Only import for specific users
                     */
                    if (!(new Watchlist())->isOnWatchlist($externalId, $this->watchlist)) {
                        continue;
                    }

                    /* If the existing user never had a portrait hash,
                     * they never had a portrait pic: assign it
                     */
                    if (!isset($existing[$externalId]) || null === $existing[$externalId]['portraitHash']) {
                        $this->lookup[$externalId] = $record['download_url'];
                        continue;
                    }

                    /* If this photo was modified more recently than the profile itself,
                     * the profile picture changed some time after the profile was last updated;
                     * we need to download and assign this pic.
                     *
                     * Otherwise the photo is older than the profile and hasn't changed since
                     * at least one previous import; no changes required for this photo.
                     * It's already up to date.
                     */
                    $lastModified = new \Alaress_Date_Format($record['last_modified_date'], \Alaress_Date_Format::FORMAT_DATETIME_UTC_NO_MLS);
                    $existingModified = $existing[$externalId]['modifiedAt'];

                    if ($lastModified->gt($existingModified)) {
                        $this->lookup[$externalId] = $record['download_url'];
                    }
                }
            }
        }

        /* This lookup contains tuples for people whose photos need updating either because:
         *     - they never had one previously
         *     - or, the photo is newer than the last time we touched their profile
         *
         * The absence of a tuple means no changes required for that externalId
         */
        return $this->lookup;
    }
}
