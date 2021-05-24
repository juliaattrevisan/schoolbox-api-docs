<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Config;

use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;

/**
 * Class EventType
 * @package Schoolbox\Calendar\Config
 */
class EventType extends Base
{
    /**
     * @var EventTypeMapper
     */
    private $eventTypeMapper;

    /**
     * EventType constructor.
     * @param EventTypeMapper $eventTypeMapper
     */
    public function __construct(EventTypeMapper $eventTypeMapper)
    {
        $this->eventTypeMapper = $eventTypeMapper;
    }

    /**
     * @return EventType
     */
    public function generate(): self
    {
        $this->data = $this->format($this->eventTypeMapper->findAll());

        return $this;
    }

    /**
     * @param GenericCollection $data
     * @return Collection
     */
    private function format(GenericCollection $data): Collection
    {
        return $data->map(
            static function (\Schoolbox\Calendar\EventType $v) {
                return [
                    'id'    => (int)$v->getId(),
                    'name'  => $v->getType(),
                    'color' => $v->getColor(),
                ];
            }
        );
    }
}
