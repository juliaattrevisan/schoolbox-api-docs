<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Config;

use Schoolbox\Collection;

/**
 * Class Filter
 *
 * @package Schoolbox\Calendar\Config
 */
class Filter extends Base
{

    private const GROUP     = 'group';
    private const CAMPUS    = 'campus';
    private const EVENTTYPE = 'eventType';
    private const SOURCE    = 'source';


    /**
     * @var array|null
     */
    private ?array $filters;


    /**
     * Filter constructor.
     * @param array|null $filters
     */
    public function __construct(?array $filters)
    {
        $this->filters = $filters;
    }

    /**
     * @param Collection $campuses
     * @param Collection $groups
     * @param Collection $eventTypes
     * @param Collection $sourceLists
     * @return Filter
     */
    public function generate(Collection $campuses, Collection $groups, Collection $eventTypes, Collection $sourceLists): self
    {
        if ($this->filters) {
            $this->data = [
                self::GROUP     => $this->filters[self::GROUP]     ?? [],
                self::CAMPUS    => $this->filters[self::CAMPUS]    ?? [],
                self::EVENTTYPE => $this->filters[self::EVENTTYPE] ?? [],
                self::SOURCE    => $this->filters[self::SOURCE]    ?? [],
            ];
        } else {
            foreach ($campuses as $campus) {
                $this->data[self::CAMPUS][] = $campus['id'];
            }

            foreach ($eventTypes as $type) {
                $this->data[self::EVENTTYPE][] = $type['id'];
            }

            foreach ($sourceLists as $list) {
                $this->data[self::SOURCE][] = $list['ids'];
            }

            foreach ($groups as $group) {
                $this->data[self::GROUP][] = $group['id'];
            }
        }

        return $this;
    }
}
