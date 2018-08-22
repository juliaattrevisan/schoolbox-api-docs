<?php
/**
 * Codec
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\Comms\News\Audience\BusinessRules;
use Schoolbox\Comms\News\Audience\Mapper;
use Schoolbox\Comms\News\Audience\Model;

class Codec
{

    /**
     * Separates the audiencing option type from its value
     * e.g. house:12345, role:5678
     */
    public const AUDIENCE_SEPARATOR = ':';


    /**
     * Takes a value string $value and encodes it
     *
     * @param string $value
     * @return string
     */
    public static function encodeValue(string $value): string
    {
        return base64_encode($value);
    }


    /**
     * Taks an encoded value string $value and decodes it
     *
     * @param string $value
     * @return string
     */
    public static function decodeValue(string $value): string
    {
        return base64_decode($value);
    }


    /**
     * Takes three strings, encodes them into the format below
     *
     * user:230:parents
     *
     * @param string $entityType
     * @param string $entityValue
     * @param string $relationship
     * @return string
     */
    public static function encode(string $entityType, string $entityValue, string $relationship): string
    {
        $segment = $entityType . self::AUDIENCE_SEPARATOR . self::encodeValue($entityValue);

        if ($relationship !== Model::RELATIONSHIP_SELF) {
            $segment .= self::AUDIENCE_SEPARATOR . $relationship;
        }

        return $segment;
    }

    /**
     * Takes a segment string and snaps it into constituent pieces
     *
     * @param string $segment
     * @return array
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public static function decode(string $segment): array
    {
        $pieces = explode(self::AUDIENCE_SEPARATOR, $segment);

        $entityType = $pieces[0];
        $entityValue = self::decodeValue($pieces[1]);

        /* If there isn't a $pieces[2], there was no relationship detected in $segment
         * Segments require three pieces - entity type, entity value, relationship
         *
         * A null relationship typically infers as RELATIONSHIP_SELF from frontend
         */
        $relationship = $pieces[2];

        if (null === $relationship) {
            $relationship = Model::RELATIONSHIP_SELF;
        }

        /* If the entity type isn't recognised, error out
         * BusinessRules requires a model, so we'll mash one together with the pieces we have
         */
        $model = Mapper::getInstance()->createModel()
            ->setEntityType($entityType)
            ->setEntityValue($entityValue)
            ->setRelationship($relationship);

        /* Verify that the constituent pieces are valid; otherwise die loudly because you're trying
         * to use some kind of illegal configuration somehow
         */
        if ((new BusinessRules())->isValidRelationship($model, $relationship)) {
            return [
                'entityType' => $entityType,
                'entityValue' => $entityValue,
                'relationship' => $relationship
            ];
        }

        return [];
    }
}
