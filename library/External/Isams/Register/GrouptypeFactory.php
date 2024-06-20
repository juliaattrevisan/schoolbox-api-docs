<?php

declare(strict_types=1);

namespace External\Isams\Register;

use External\Isams\Register\GroupType\GroupTypeBase;
use External\Isams\Register\GroupType\FieldGroup;
use External\Isams\Register\GroupType\RecordCollection;
use Schoolbox\User;

class GrouptypeFactory
{
    public function produce(
        string $type,
        CacheHelper $cacheHelper,
        User $user,
        array $register,
        array $config,
        array $metaFields,
    ): ?GroupTypeBase {
        return match ($type) {
            'FieldGroup' => new FieldGroup($cacheHelper, $user, $register, $config, $metaFields),
            'RecordCollection' => new RecordCollection($cacheHelper, $user, $register, $config, $metaFields),
            default => null
        };
    }
}
