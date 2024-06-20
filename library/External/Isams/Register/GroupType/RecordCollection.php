<?php

namespace External\Isams\Register\GroupType;

class RecordCollection extends GroupTypeBase
{
    /**
     * Retrieving record collections
     *
     * @return array|mixed
     */
    private function retreiveRecords()
    {
        //Requested register collection we are extracting the data out of
        $recordCollection = $this->cacheHelper->getRecordCollections($this->register['id'], $this->collectionName);

        if (null === $recordCollection) {
            return null;
        }

        return $this->cacheHelper->getRecordCollectionsRecords(
            $this->register['id'],
            $this->user->getExternalId(),
            $recordCollection['id']
        );
    }

    public function process(array $out): array
    {
        $data = $this->retreiveRecords();

        if (null === $data) {
            /**
             * Skip if unable to find the specified Record Collections.
             */
            return $out;
        }

        // resort user records so they display similar to within iSAMS
        usort($data, function ($a, $b) {
            return $b['id'] <=> $a['id'];
        });

        foreach ($this->config['fields'] as $key => $knownField) {
            foreach ($data as $index => $record) {
                //If the field is in the approved list, then build it, else continue
                if (!array_key_exists($knownField, $record['fields'])) {
                    continue;
                }

                [$name, $type, $value] = $this->prepareUserFieldData(
                    $this->metaFields[$this->collectionName]['fields'][$knownField],
                    $record['fields'][$knownField]
                );

                $groupType = $this->getType();
                $out[$groupType][$this->collectionName]['headers'][$key] = $name;
                $out[$groupType][$this->collectionName]['data'][$index][$key] = [
                    'type' => $type,
                    'value' => $value
                ];
            }
        }
        return $out;
    }
}
