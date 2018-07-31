<?php
/**
 * Codec
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\Comms\News\Audience\Model;

class Codec
{

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
        $segment = $entityType . Transactor::AUDIENCE_SEPARATOR . base64_encode($entityValue);

        if ($relationship !== Model::RELATIONSHIP_SELF) {
            $segment .= Transactor::AUDIENCE_SEPARATOR . $relationship;
        }

        return $segment;
    }


    /**
     * Takes a segment string and snaps it into constituent pieces
     *
     * @param string $segment
     * @return array
     */
    public static function decode(string $segment): array
    {
        $pieces = explode(Transactor::AUDIENCE_SEPARATOR, $segment);

        $entityType = $pieces[0];
        $entityValue = base64_decode($pieces[1]);

        /* This has a Parents or Teachers modifier if the audiencing segment has 3 pieces
         */
        $modifierCount = 3;
        $piecesCount   = \count($pieces);

        $relationship = Model::RELATIONSHIP_SELF;

        if ($piecesCount === $modifierCount) {
            if ($pieces[$piecesCount - 1] === Model::RELATIONSHIP_PARENTS_OF) {
                $relationship = Model::RELATIONSHIP_PARENTS_OF;
            } elseif ($pieces[$piecesCount - 1] === Model::RELATIONSHIP_TEACHERS_OF) {
                $relationship = Model::RELATIONSHIP_TEACHERS_OF;
            }
        }

        return [
            'entityType' => $entityType,
            'entityValue' => $entityValue,
            'relationship' => $relationship
        ];
    }
}