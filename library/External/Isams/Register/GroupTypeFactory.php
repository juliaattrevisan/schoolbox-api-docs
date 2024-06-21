<?php

declare(strict_types=1);

namespace External\Isams\Register;

use External\Isams\Register\GroupType\GroupTypeBase;
use External\Isams\Register\GroupType\FieldGroup;
use External\Isams\Register\GroupType\RecordCollection;
use Schoolbox\User;

class GrouptypeFactory
{

    public function __construct(
        private CacheHelper $cacheHelper,
        private User $user,
        private array $register,
        private array $metaFields,
    ) {
    }

    public function produce(
        string $type,
        array $config,
    ): ?GroupTypeBase {
        return match ($type) {
            'FieldGroup' => new FieldGroup(
                $this->cacheHelper,
                $this->user,
                $this->register,
                $config,
                $this->metaFields
            ),
            'RecordCollection' => new RecordCollection(
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
