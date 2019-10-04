<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Config;

use Schoolbox\Collection;
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
        $classes = new Collection();
        $groups = new Collection();

        $folders = $this->folderMapper
            ->findForUser($this->user)
            ->sortByCallback(function (\Schoolbox\Resource\Folder\Model $a, \Schoolbox\Resource\Folder\Model $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

        foreach ($folders as $folder) {
            $folder->isClass() ? $classes->add($folder) : $groups->add($folder);
        }

        $this->data = $this->format(
            $classes->merge($groups)
        );

        return $this;
    }

    /**
     * @param Collection $data
     * @return Collection
     */
    private function format(Collection $data): Collection
    {
        return $data->map(
            function (\Schoolbox\Resource\Folder\Model $v) {
                return [
                    'id'    => (int) $v->getId(),
                    'name'  => $v->getNameWithCodes(),
                    'code'  => $v->getCodeString()
                ];
            }
        );
    }
}
