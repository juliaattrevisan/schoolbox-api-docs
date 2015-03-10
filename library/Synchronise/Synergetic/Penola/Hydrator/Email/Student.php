<?php
/**
 * Hydrator for student's email.
 * * no external mail client
 * * mail address is "$username@student.penola.vic.edu.au"
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Penola_Hydrator_Email_Student implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['mail_handler'] = 0;
        $newUser['alt_email']    = $externalUser['NetworkLogin'].'@student.penola.vic.edu.au';
        return $newUser;
    }
}
