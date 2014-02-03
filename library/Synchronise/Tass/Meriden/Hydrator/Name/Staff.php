<?php
/**
 * Hydrator for name for Meriden staff.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_Name_Staff implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        // tud21_text is often first name
//        print_r($externalUser);
        if (!empty($externalUser['tud21_text'])) {
            $newUser['firstname'] = $externalUser['first'];
        }

        // tch_name could be something like "$title $firstname $lastname"
        // or "$title $lastname"
        // Overwrite those fields, if they haven't been set already.
        $name = array();
        if (preg_match('/^(\\w+)\\s+(\\w+)\\s+(\\w+)$/', $externalUser['StaffFullName'], $matches)) {
            list(, $name['title'], $name['firstname'], $name['lastname']) = $matches;
        }
        elseif (preg_match('/^(\\w+)\\s+(\\w+)$/', $externalUser['StaffFullName'], $matches)) {
            list(, $name['title'], $name['lastname']) = $matches;
        }
        foreach ($name as $field => $value) {
            if (empty($newUser[$field])) {
                $newUser[$field] = $value;
            }
        }
        return $newUser;
    }
}
