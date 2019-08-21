<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Config;

use Alaress\Session\Session;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception;
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
    private $di;

    private const GROUP     = 'group';
    private const CAMPUS    = 'campus';
    private const EVENTTYPE = 'eventType';
    private const SOURCE    = 'source';

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
     * @throws Exception\ServiceNotFound
     */
    public function generate(Collection $campuses, Collection $groups, Collection $eventTypes, Collection $sourceLists): self
    {
        try {
            /** @var Session $session */
            $session = $this->di->get('session');
            $filters = $session->get('calendarFilter');
        } catch (Exception $e) {
            throw new Exception\ServiceNotFound();
        }

        if ($filters) {
            $this->data = [
                self::GROUP     => $filters[self::GROUP]        ?? [],
                self::CAMPUS    => $filters[self::CAMPUS]       ?? [],
                self::EVENTTYPE => $filters[self::EVENTTYPE]    ?? [],
                self::SOURCE    => $filters[self::SOURCE]       ?? [],
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
