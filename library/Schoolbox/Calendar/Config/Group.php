<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Config;

use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\User;

/**
 * Class Group
 * @package Schoolbox\Calendar\Config
 */
class Group extends Base
{
    /**
     * @var User
     */
    private $user;

    /**
     * @var Mapper
     */
    private $folderMapper;

    /**
     * Group constructor.
     * @param User $user
     */
    public function __construct(User $user, Mapper $folderMapper)
    {
        $this->user         = $user;
        $this->folderMapper = $folderMapper;
    }

    /**
     * @return $this
     */
    public function generate(): self
    {
        $this->data = $this->format(
            $this->folderMapper->findForUser($this->user)->sortBy('name', 'ASC')
        );

        return $this;
    }

    /**
     * @param GenericCollection $data
     * @return \Schoolbox\Collection
     */
    private function format(GenericCollection $data): \Schoolbox\Collection
    {
        return $data->map(
            function (\Schoolbox\Resource\Folder\Model $v) {
                return [
                    'id'    => (int) $v->getId(),
                    'name'  => $v->getName(),
                    'code'  => $v->getCodeString()
                ];
            }
        );
    }
}
