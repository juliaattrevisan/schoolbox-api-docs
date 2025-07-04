<?php

declare(strict_types=1);

namespace External\Isams\Register\GroupType;

use Psr\SimpleCache\InvalidArgumentException;

class FieldGroup extends GroupTypeBase
{
    public const GROUP_TYPE = 'FieldGroup';

    /**
     * Getting the student fields for data output
     *
     * @return array|mixed
     * @throws InvalidArgumentException
     * @throws \JsonException
     */
    protected function retrieveRecords(): ?array
    {
        return $this->cacheHelper->getStudentFields($this->register['id'], $this->user->getExternalId());
    }

    /**
     * @inheritdoc
     *
     * @throws InvalidArgumentException
     * @throws \JsonException
     */
    public function process(array $out): array
    {
        $data = $this->retrieveRecords();
        foreach ($this->config['fields'] as $index => $knownField) {
            //If the field is in the approved list, then build it, else continue
            if (!array_key_exists($knownField, $data)) {
                continue;
            }

            [$name, $type, $value] = $this->prepareUserFieldData(
                $this->metaFields[$this->collectionName]['fields'][$knownField],
                $data[$knownField]
            );

            $out[self::GROUP_TYPE][$this->collectionName]['data'][$index] = [
                'name' => $name,
                'type' => $type,
                'value' => $value
            ];
        }

        return $out;
    }
}
