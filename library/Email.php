<?php
/**
 * Adds validation and detection methods for email addresses and things related
 * to email addresses.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class Email
{

    /**
     * The email address to analyse
     *
     * @var null
     */
    private $email = null;

    /**
     * Constructor
     *
     * @param null $email
     */
    public function __construct($email = null)
    {
        if ($email !== null) {
            $this->setEmail($email);
        }
    }

    /**
     * Check if an email address is valid. Stolen and adapted from the Ormond
     * ContactForm class.
     *
     * This should obsolete isEmail()
     *
     * @return bool
     */
    public function isValid()
    {

        if (!filter_var($this->getEmail(), FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        /* Do domain lookup
         */
        list($alias, $domain) = explode('@', $this->getEmail());

        /* This prevents appending of localhost to lookup
         */
        $domain .= '.';

        /* Check for mx records or that it resolves
         */
        if (getmxrr($domain, $temp) || checkdnsrr($domain, 'ANY')) {
            return true;
        }

        return false;
    }

    /**
     * Format an email address from a User array, or an array which contains the
     * local_email, alt_email and username fields.
     *
     * This creates local_email versions if specified, otherwise returns the
     * alt_email field
     *
     * @param $user
     * @return bool|string
     */
    public function formatEmailFromArray($user)
    {
        if ($user['local_email'] && isset($user['username']) && $user['username']) {
            return $user['username'] . '@' . Config::get('mail_address');
        } else {
            if ($user['alt_email']) {
                return $user['alt_email'];
            }
        }

        return false;
    }

    /**
     * Set email
     *
     * @param  $val
     * @return void
     */
    private function setEmail($val)
    {
        $this->email = $val;
    }

    /**
     * Get email
     *
     * @return
     */
    public function getEmail()
    {
        return $this->email;
    }
}
