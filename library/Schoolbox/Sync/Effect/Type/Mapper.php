<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Effect\Type;


use Schoolbox;

class Mapper extends Schoolbox\Mapper
{
    /* As per ups_effect_type in the db
     * Used to determine Effect Fragment templates
     *
     * Mapped Key/Value Pairs
     */
    const EFFECT_TYPE_MAP = 1;

    /* Freeform Text Input/Single Select Dropdown Effect Type
     */
    const EFFECT_TYPE_LITERAL = 2;

    /* Skip Effect Type
     */
    const EFFECT_TYPE_SKIP = 3;

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasModel(Model::class);
        $this->hasTable('ups_effect_type');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
    }

    /**
     * Is this a Map Effect?
     *
     * @param $type
     * @return bool
     */
    public function isMap($type) {
        if ($type == self::EFFECT_TYPE_MAP) {
            return true;
        }

        return false;
    }

    /**
     * Is this a Skip Effect?
     *
     * @param $type
     * @return bool
     */
    public function isSkip($type) {
        if ($type == self::EFFECT_TYPE_SKIP) {
            return true;
        }

        return false;
    }

    /**
     *
     * This function will scan through UPS effects and remove the object element from any rules that contain the deleted object
     *
     * @param string $userProperty
     * @param int $id
     *
     * @return bool
     */
    public function removeRules(string $userProperty, int $id): bool
    {
        $upsEffects = $this->getDatabase()->query("SELECT * FROM ups_effect WHERE user_property = '$userProperty' ")->fetchAll();
        $statement  = $this->database->prepare("UPDATE ups_effect SET arguments = :arguments WHERE id = :effect_id");

        foreach ($upsEffects as $upsEffectJson) {

            $isArrOfArr = true;

            $key = 0;
            if ($upsEffectJson['effect_type_id'] === self::EFFECT_TYPE_MAP) {
                $key = 1;
            }

            $upsEffect = json_decode($upsEffectJson['arguments'], true);

            if (!isset($upsEffect[$key])) {
                return false;
            }

            if (!is_array($upsEffect[$key])) {
                $upsEffect[$key] = [$upsEffect[$key]];
                $isArrOfArr      = false;
            }

            // Remove object element from list if it is deleted
            $filteredValues = array_filter($upsEffect[$key], static function ($value) use ($id) {
                return $value != $id;
            });

            // Check if anything changed, then execute the query
            if ($filteredValues !== $upsEffect[$key]) {

                if ($isArrOfArr) {
                    $upsEffect[$key] = $filteredValues;
                } else {
                    $upsEffect[$key] = empty($filteredValues) ? '' : $filteredValues;
                }
                //Store updated effects back into the database
                $statement->bindParam(':arguments', json_encode($upsEffect));
                $statement->bindParam(':effect_id', $upsEffectJson['id']);
                $statement->execute();
            }
        }
        return true;
    }
}
