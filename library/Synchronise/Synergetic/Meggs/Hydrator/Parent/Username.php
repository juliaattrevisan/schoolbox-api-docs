<?php
/**
 * Hydrator for group for Oakburn.
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Synchronise_Synergetic_Meggs_Hydrator_Parent_Username implements Synchronise_Base_IHydrator {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {


        $newUser['username'] = $externalUser['StudentContactNetworkLogin'];

        return $newUser;
    }
}
