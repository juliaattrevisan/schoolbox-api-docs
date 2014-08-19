<?php
/**
 * A basic hydrator that just sets a random value of a given length on a field.
 * Good for password generation.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_RandomPassword extends Synchronise_Base_Hydrator_CreateUpdate
{

    /**
     * Length of password, defaults to 8.
     *
     * @var string
     */
    protected $length = 8;

    /**
     * @param int $length How long is the password? Defaults to 8 chars.
     */
    public function __construct($length = null)
    {
        $this->length = $length;
    }

    /**
     * {@inheritdoc}
     */
    public function hydrateOnCreate($externalUser, $newUser)
    {
        $newUser['password'] = substr(
            preg_replace(
                '/[a-z0-9]/i',
                '',
                base64_encode(openssl_random_pseudo_bytes($this->length * 2))
            ),
            0,
            $this->length
        );
        return $newUser;
    }
}
