<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile\Profile;
use Schoolbox\External\Connector\Watchlist;

class Citizenship
{
    private const UNKNOWN_DESCRIPTION = 'Unknown';

    /**
     * @var array|null
     */
    private ?array $lookup = null;

    public function __construct(private IApi $api, private array $watchlist)
    {
    }

    /**
     * Generate a compiled list of citizenship records, indexed by person id.
     *
     * @return array
     */
    public function generate(): array
    {
        if (null === $this->lookup) {
            $this->lookup = [];
            $lookupConverter = new ValueListConverter();
            $generator = $this->api->retrieve('GET', 'admission/citizenships', Profile::SCOPE);

            foreach ($generator as $page) {
                $valueLists = $lookupConverter->generate($page['value_lists']);

                foreach ($page['data'] as $record) {
                    $externalId = trim((string)$record['person_id']);

                    if (!(new Watchlist())->isOnWatchlist($externalId, $this->watchlist)) {
                        continue;
                    }

                    $citizenship = $valueLists['citizenship'][$record['citizenship']] ?? self::UNKNOWN_DESCRIPTION;

                    if (!isset($this->lookup[$externalId])) {
                        $this->lookup[$externalId] = [
                            'nationalities' => [],
                        ];
                    }

                    if (!\in_array($citizenship, $this->lookup[$externalId]['nationalities'], true)) {
                        $this->lookup[$externalId]['nationalities'][] = $citizenship;
                    }
                }
            }

            foreach ($this->lookup as &$citizenshipData) {
                $citizenshipData['nationalities'] = implode(', ', $citizenshipData['nationalities']);
            }

            unset($citizenshipData);
        }

        return $this->lookup;
    }
}
