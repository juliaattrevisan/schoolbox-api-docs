<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Config;

use Alaress\Session\Session;
use Aura\Di\ContainerInterface;
use Schoolbox\Collection;

/**
 * Class Filter
 * @package Schoolbox\Calendar\Config
 */
class Filter extends Base
{
    /**
     * @var ContainerInterface
     */
    protected $di;

    /**
     * Filter constructor.
     * @param ContainerInterface $di
     */
    public function __construct(ContainerInterface $di)
    {
        $this->di = $di;
    }

    /**
     * @param Collection $campuses
     * @param Collection $groups
     * @param Collection $eventTypes
     * @param Collection $sourceLists
     * @return Filter
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function generate(Collection $campuses, Collection $groups, Collection $eventTypes, Collection $sourceLists): self
    {
        /** @var Session $session */
        $session = $this->di->get('session');

        $calendarFilters = $session->get('calendarFilters');

        $this->data = [
            'group'     => [],
            'campus'    => [],
            'eventType' => [],
            'source'    => [],
        ];

        if ($calendarFilters) {
            $this->data = [
                'group'     => $calendarFilter['group']        ?? [],
                'campus'    => $calendarFilter['campus']       ?? [],
                'eventType' => $calendarFilter['eventType']    ?? [],
                'source'    => $calendarFilter['source']       ?? [],
            ];
        } else {
            foreach ($campuses as $campus) {
                $this->data['campus'][] = $campus['id'];
            }

            foreach ($eventTypes as $eventType) {
                $this->data['eventType'][] = $eventType['id'];
            }

            foreach ($sourceLists as $list) {
                $this->data['source'][] = $list['ids'];
            }

            foreach ($groups as $group) {
                $this->data['group'][] = $group['id'];
            }
        }

        return $this;
    }
}
