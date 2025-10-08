<?php

namespace External\Isams\Register\GroupType;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;

class RecordCollection extends GroupTypeBase
{
    public const GROUP_TYPE = 'RecordCollection';
    /**
     * Retrieving record collections
     *
     * @return array|null
     * @throws ClientExceptionInterface
     * @throws GuzzleException
     * @throws InvalidArgumentException
     * @throws \JsonException
     *
     */
    protected function retrieveRecords(): ?array
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

    /**
     * @inheritdoc
     * @throws ClientExceptionInterface
     * @throws GuzzleException
     * @throws InvalidArgumentException
     * @throws \JsonException
     */
    public function process(array $out): array
    {
        $data = $this->retrieveRecords();

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

                $groupType = self::GROUP_TYPE;
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
