<?php
/**
 * Hydrator for name for TASS parents.
 *
 * Because TASS doesn't have separate accounts for a mother and father,
 * their names must be jammed into one name.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Hydrator_Name_Parent implements Synchronise_Base_IHydrator
{

    protected $field = 'StudentContactPreferred';

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        // Example Name: "Cpt A Cripps & Mrs P Cripps"
        // Tass ain't giving me titles, but it does give me
        // "StudentContactPreferred" and "StudentContactSpousePreferred"
        // I'll just jam names together (probably not updating existing names)
        $newUser['firstname'] = '';

        if (isset($externalUser['par_name'])) {
            $newUser['lastname'] = $externalUser['par_name'];
        } elseif (!$externalUser['StudentContactSpousePreferred']) {
            // Single parent in main contact record
            $newUser['lastname'] = $externalUser['StudentContactPreferred'];
        } elseif (!$externalUser['StudentContactPreferred']) {
            // Single parent in spouse contact record
            $newUser['lastname'] = $externalUser['StudentContactSpousePreferred'];
        } else {
            // Two parents which may or may not have the same surname. Find out.
            list($contactFirstName, $contactSurname)
                = preg_split("/\\s+/", $externalUser['StudentContactPreferred'], 2);
            list($spouseFirstName, $spouseSurname)
                = preg_split("/\\s+/", $externalUser['StudentContactSpousePreferred'], 2);
            if ($contactSurname == $spouseSurname) {
                $newUser['lastname'] = sprintf(
                    '%s & %s %s',
                    $contactFirstName,
                    $spouseFirstName,
                    $contactSurname
                );
            } else {
                $newUser['lastname'] = sprintf(
                    '%s & %s',
                    $externalUser['StudentContactPreferred'],
                    $externalUser['StudentContactSpousePreferred']
                );
            }
        }
        return $newUser;
    }
}
