<?php

declare(strict_types=1);

namespace External\Isams\Register;

use External\Isams\Register\GroupType\GroupTypeBase;
use External\Isams\Register\GroupType\FieldGroup;
use External\Isams\Register\GroupType\RecordCollection;
use Schoolbox\User;

class GroupTypeFactory
{
    /**
     * @param CacheHelper $cacheHelper
     * @param User        $user
     * @param array       $register
     * @param array       $metaFields
     */
    public function __construct(
        private CacheHelper $cacheHelper,
        private User $user,
        private array $register,
        private array $metaFields,
    ) {
    }

    /**
     * Creating the object for the group type object
     *
     * @param string $type
     * @param array  $config
     * @return GroupTypeBase|null
     */
    public function produce(
        string $type,
        array $config,
    ): ?GroupTypeBase {
        return match ($type) {
            FieldGroup::GROUP_TYPE => new FieldGroup(
                $this->cacheHelper,
                $this->user,
                $this->register,
                $config,
                $this->metaFields
            ),
            RecordCollection::GROUP_TYPE => new RecordCollection(
                $this->cacheHelper,
                $this->user,
                $this->register,
                $config,
                $this->metaFields
            ),
            default => null
        };
    }
}
