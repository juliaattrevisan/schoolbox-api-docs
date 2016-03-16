<?php
/**
 * Synergetic Community Portal related queries
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 *
 * @specialThanksTo Damian Sloane <damian.sloane@alaress.com.au>
 */

use Alaress\DebugBar\Instance as DebugBar;

class External_Synergetic_CommunityPortal
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * The password encryption key. This must be secret and not stored in code.
     *
     * @var string
     */
    private $key;

    /**
     * The hard-coded IV for the password. This does not need to be secret.
     *
     * @var string
     */
    private $initializationVector;

    /**
     * Constructor
     *
     * @param string $key
     * @param string $initializationVector
     */
    public function __construct($key, $initializationVector)
    {
        $this->key                  = $key;
        $this->initializationVector = $initializationVector;
        $this->database             = MSSQL::getInstance();
        if (DebugBar::isEnabled()) {
            $this->database->installCollector();
            /** @var \DebugBar\DataCollector\DataCollectorInterface $collector */
            $collector = $this->database->getCollector();
            if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                DebugBar::getInstance()->addCollector($collector);
            }
        }
    }

    /**
     * Updates the community portal password for this user.
     *
     * @param string $communityId
     * @param string $newPassword
     */
    public function changePassword($communityId, $newPassword)
    {
        $encryptedPassword = $this->encryptPassword($newPassword);
        $query = "
            UPDATE CommunityLogins
            SET    Password = {$this->database->smartQuote($encryptedPassword)}
            WHERE  ID = {$this->database->smartQuote($communityId)}
        ";
        $this->database->query($query);
    }

    /**
     * Encrypts this plaintext password.
     *
     * @param  string $plaintext
     * @return string
     */
    protected function encryptPassword($plaintext)
    {
        // Pad converted text to a block with chr(bytes to pad)
        // @see https://php.net/manual/en/function.mcrypt-encrypt.php#47973
        $blockSize = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
        // Because this is the encoding their DB uses
        $convText  = mb_convert_encoding($plaintext, 'UCS-2LE');
        $padding   = $blockSize - (strlen($convText) % $blockSize);
        $convText  .= str_repeat(chr($padding), $padding);
        return base64_encode(
            mcrypt_encrypt(
                MCRYPT_RIJNDAEL_128,
                $this->key,
                $convText,
                MCRYPT_MODE_CBC,
                $this->initializationVector
            )
        );
    }

    /**
     * Decrypts this encrypted password.
     *
     * @param  string $encrypted
     * @return string
     */
    protected function decryptPassword($encrypted)
    {
        return mb_convert_encoding(
            mcrypt_decrypt(
                MCRYPT_RIJNDAEL_128,
                $this->key,
                base64_decode($encrypted),
                MCRYPT_MODE_CBC,
                $this->initializationVector
            ),
            'UTF-8'
        );
    }
}
