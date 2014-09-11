<?php
/**
 * A hydrator that generates passwords for users.
 *
 * Should be combined with Synchronise_Base_Hydrator_UnsetOnUpdate to clear
 * passwords for users that already exist.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_RandomPassword implements Synchronise_Base_IHydrator
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
     * Creates a random password, $this->length characters long.
     *
     * @return string
     */
    protected function generatePassword()
    {
        return substr(
            preg_replace(
                '/[^a-z0-9]/i',
                '',
                // base64ing random bytes gives us a bunch of chars that are
                // mainly alphanumeric.
                base64_encode(openssl_random_pseudo_bytes($this->length))
            ),
            0,
            $this->length
        );
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['password'] = $this->generatePassword();
        return $newUser;
    }
}
