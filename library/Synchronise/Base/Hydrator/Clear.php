<?php
/**
 * Hydrator for sync scripts: clears all fields from the new user entry which
 * have null values.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_Clear implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        return array_filter($newUser, function($v) { return isset($v); });
    }
}
