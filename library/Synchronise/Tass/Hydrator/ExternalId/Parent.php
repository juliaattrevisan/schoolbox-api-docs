<?php
/**
 * Hydrator for external ID for TASS parents.

 * Used to be exclusive to the Cathedral sync, but was made generic to all TASS
 * syncs because TASS parent external IDs must always be in the format
 * "(S|P)\d{7,8}".

 * The first 7 digits are the parent's actual TASS ID, whereas the 8th
 * (if present) is the parent's split family number, used for checking if the
 * parent has actual parenting rights on a child.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Hydrator_ExternalId_Parent implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        // Is this a single parent (add_num = 4)?
        // Prepend S, otherwise prepend P.
        $prefix = 'P';
        if ($externalUser['add_num'] == 4) {
            $prefix = 'S';
        }
        $externalId = $prefix.$externalUser['StudentContactID'];
        // Add split family number, if present
        if (isset($externalUser['sfa_num'])) {
            $externalId .= $externalUser['sfa_num'];
        }
        $newUser['synergy_id'] = $externalId;
        return $newUser;
    }
}
