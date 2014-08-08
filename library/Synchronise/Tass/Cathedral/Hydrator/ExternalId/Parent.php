<?php
/**
 * Hydrator for external ID for Cathedral parents.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Hydrator_ExternalId_Parent implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        // Is this a single parent (add_num = 4)?
        // Append '-4' to the synergy ID.
        $externalId = 'P'.$externalUser['StudentContactID'];
        if (isset($externalUser['sfa_num'])) {
            $externalId .= $externalUser['sfa_num'];
        }
        $newUser['synergy_id'] = $externalId;
        return $newUser;
    }
}
