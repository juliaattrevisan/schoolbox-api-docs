<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Type;

use InvalidArgumentException;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral\Type\Model;
use Schoolbox\User;

/**
 * Key converter for pastoral type/subtype filter.
 */
class KeyConverter extends Filter\AbstractKeyConverter
{
    /**
     * Constructor
     *
     * @param Filter\Model              $filter
     * @param GenericCollection|Model[] $types
     */
    public function __construct(Filter\Model $filter, private GenericCollection $types)
    {
        parent::__construct($filter);
    }

    /**
     * @inheritdoc
     */
    protected function getItem(string $key, User $user, string $page)
    {
        $this->assertValidKey($key, $user, $page);
        return $key;
    }

    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        $parts = self::extractTypeAndSubtype($key);

        // Assert type is in list
        $type = $this->types[$parts['type']] ?? null;
        if (!$type) {
            throw new InvalidArgumentException("No pastoral type found for key: $key");
        }

        // Is there a subtype?
        // * if not: assert the type has no subtypes
        // * if so: assert the subtype is one of the type's subtypes
        if (!isset($parts['subtype'])) {
            if ($type->getSubTypes()->count() !== 0) {
                throw new InvalidArgumentException(
                    "Pastoral type with subtypes cannot be selected without a subtype: $key"
                );
            }
        } else {
            $subtype = $type->getSubTypes()[$parts['subtype']] ?? null;
            if (!$subtype) {
                throw new InvalidArgumentException(
                    "Pastoral type does not contain subtype: $key"
                );
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function possibleItemCount(): int
    {
        $count = 0;
        foreach ($this->types as $type) {
            // * if the type has no sub-types, the type itself is selectable;
            //   thus incrementing count by 1
            // * if it has sub-types, each sub-type is selectable
            $count += max(1, $type->getSubTypes()->count());
        }
        return $count;
    }

    /**
     * Extract type and subtype from the key.
     *
     * @param string $key
     * @return array{type: ?int, subtype: ?int}
     */
    public static function extractTypeAndSubtype(string $key): array
    {
        $keyParts = explode('_', $key);
        return [
            'type'    => filter_var($keyParts[0], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'subtype' => filter_var($keyParts[1] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
        ];
    }
}
