<?php

declare(strict_types=1);

namespace External\Isams\Register\GroupType;

class FieldGroup extends GroupTypeBase
{
    /**
     * Getting the student fields for data output
     *
     * @return array|mixed
     */
    private function retreiveRecords()
    {
        return $this->cacheHelper->getStudentFields($this->register['id'], $this->user->getExternalId());
    }

    public function process(array $out): array
    {
        $data = $this->retreiveRecords();
        foreach ($this->config['fields'] as $index => $knownField) {
            //If the field is in the approved list, then build it, else continue
            if (!array_key_exists($knownField, $data)) {
                continue;
            }

            [$name, $type, $value] = $this->prepareUserFieldData(
                $this->metaFields[$this->collectionName]['fields'][$knownField],
                $data[$knownField]
            );

            $out[$this->getType()][$this->collectionName]['data'][$index] = [
                'name' => $name,
                'type' => $type,
                'value' => $value
            ];
        }

        return $out;
    }
}
