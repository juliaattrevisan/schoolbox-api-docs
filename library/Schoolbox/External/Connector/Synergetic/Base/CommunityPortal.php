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

namespace Schoolbox\External\Connector\Synergetic\Base;

use Alaress\DebugBar\Instance as DebugBar;
use MSSQL;
use Schoolbox\External\Connector\CommunityPortalInterface;
use UnexpectedValueException;

class CommunityPortal implements CommunityPortalInterface
{
    /** @var MSSQL $database */
    private $database;

    /** @var string $key The password encryption key. This must be secret and not stored in code. */
    private $key;

    /** @var string $initializationVector The hard-coded IV for the password. This does not need to be secret. */
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
     * @param  string $communityId
     * @param  string $newPassword
     * @return bool
     * @throws UnexpectedValueException if either key or IV was not set
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
        return intval($this->database->getOne('SELECT @@ROWCOUNT')) > 0;
    }

    /**
     * Encrypts this plaintext password.
     *
     * @param  string $plaintext
     * @return string
     * @throws UnexpectedValueException if either key or IV was not set
     */
    protected function encryptPassword($plaintext)
    {
        $this->assertKeyAndIvSet();
        return base64_encode(
            openssl_encrypt(
                $plaintext,
                'AES-128-CBC',
                $this->key,
                OPENSSL_RAW_DATA,
                $this->initializationVector
            )
        );
    }

    /**
     * Decrypts this encrypted password.
     *
     * @param  string $encrypted
     * @return string
     * @throws UnexpectedValueException if either key or IV was not set
     */
    protected function decryptPassword($encrypted)
    {
        $this->assertKeyAndIvSet();
        return mb_convert_encoding(
            openssl_decrypt(
                $encrypted,
                'AES-128-CBC',
                $this->key,
                OPENSSL_RAW_DATA,
                $this->initializationVector
            ),
            'UTF-8'
        );
    }

    /**
     * Asserts that the key and IV were set in the constructor.
     *
     * @throws UnexpectedValueException if either was not.
     */
    protected function assertKeyAndIvSet()
    {
        if (empty($this->key)) {
            throw new UnexpectedValueException('Key was not set in constructor');
        }
        if (empty($this->initializationVector)) {
            throw new UnexpectedValueException('Initialization vector was not set in constructor');
        }
    }
}
