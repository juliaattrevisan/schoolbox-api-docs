<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Config;

use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Class Campus
 * @package Schoolbox\Calendar\Config
 */
class Campus extends Base
{

    /**
     * @var CampusMapper
     */
    private $campusMapper;

    /**
     * @var User
     */
    private $user;

    /**
     * Campus constructor.
     * @param User         $user
     * @param CampusMapper $campusMapper
     */
    public function __construct(User $user, CampusMapper $campusMapper)
    {
        $this->user         = $user;
        $this->campusMapper = $campusMapper;
    }

    /**
     * @return Campus
     */
    public function generate(): self
    {
        $this->data = $this->format(
            $this->campusMapper->findCampusInvolvement($this->user)->sortBy('name', 'ASC')
        );

        return $this;
    }

    /**
     * @param GenericCollection $data
     * @return Collection
     */
    private function format(GenericCollection $data): Collection
    {
        return $data->map(
            function (\Schoolbox\Campus $v) {
                return [
                    'id'    => (int) $v->getId(),
                    'name'  => $v->getName(),
                ];
            }
        );
    }
}
