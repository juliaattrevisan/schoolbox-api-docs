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

use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Connector\ICommunityPortal;
use UnexpectedValueException;

class CommunityPortal implements ICommunityPortal
{
    /** @var IConnector $databaseExternal */
    private $databaseExternal;

    /** @var string $key The password encryption key. This must be secret and not stored in code. */
    private $key;

    /** @var string $initializationVector The hard-coded IV for the password. This does not need to be secret. */
    private $initializationVector;

    /** @var bool $allowCreateUser */
    private $allowCreateUser;

    /**
     * @inheritdoc
     */
    public function __construct($config, $databaseExternal)
    {
        $this->allowCreateUser = $config['synergetic_portal_create_account'];
        $this->databaseExternal = $databaseExternal;
    }

    /**
     * @inheritdoc
     */
    public function setSecrets($key, $initializationVector)
    {
        $this->key = $key;
        $this->initializationVector = $initializationVector;
    }

    /**
     * Updates the community portal password for this user.
     *
     * @param string $communityId
     * @param string $newPassword
     * @return bool
     * @throws UnexpectedValueException if either key or IV was not set
     */
    public function changePassword($communityId, $newPassword)
    {
        $encryptedPassword = $this->encryptPassword($newPassword);

        // Check to see if they have an existing record in the 'CommunityLogins' table

        $query = $this->databaseExternal->executeQuery(
            "
                SELECT    * 
                FROM      dbo.communityLogins 
                WHERE     ID = :externalId
            ", 
            ['externalId' => $communityId]
        );

        if ($query->rowCount() > 0) {

            // Existing community login record, just update the password

            $query = "
                UPDATE    dbo.communityLogins
                SET       password = :password
                WHERE     ID = :externalId
            ";

            $rowsAffected = $this->databaseExternal->executeQuery($query, [
                'password' => $encryptedPassword,
                'externalId' => $communityId
            ])->rowCount();

        } elseif ($this->allowCreateUser) {

            // No existing community login record, need to insert one for this to have any chance of doing something

            // This adds the Schools default page permissions to the users

            $query =
            "
                INSERT INTO dbo.CommunityLoginsPage
                (
                    ID,
                    ConfigSeq,
                    EnabledFlag
                )
                SELECT  :externalId,
                        ConfigSeq,
                        CASE WHEN DefaultValue = 'TRUE' THEN 1 ELSE 0 END AS Enabled
                FROM    dbo.config
                WHERE   dbo.config.Key1 = 'CommunityPortal' AND
                        dbo.config.Key2 = 'Pages' AND
                        dbo.config.Key4 = 'EnabledFlag' AND
                        dbo.config.Type = 'BIT' AND
                        dbo.config.Value = 'True'
            ";

            $this->databaseExternal->executeQuery($query, [
                'externalId' => $communityId
            ]);

            // This sets the password

            $query = "
                INSERT INTO dbo.communityLogins
                (
                    ID,
                    Type,
                    password,
                    LastLoginDate,
                    LoginType,
                    SecurityTokenGuid,
                    SecurityTokenIssueDateTime,
                    PasswordHashed
                )
                VALUES
                (
                    :externalId,
                    'CP',
                    :password,
                    NOW(),
                    '',
                    NULL,
                    NULL,
                    ''
                )
            ";

            $rowsAffected = $this->databaseExternal->executeQuery($query, [
                'externalId' => $communityId,
                'password' => $encryptedPassword
            ])->rowCount();

        } else {

            // There was no community portal password to reset, and automatic creation is disabled- so just return true
            // and exit out of this happily.

            return true;
        }

        // If one or more rows were affected then this must have done something positive... right?

        return $rowsAffected > 0;
    }

    /**
     * Encrypts this plaintext password.
     *
     * @param string $plaintext
     * @return string
     * @throws UnexpectedValueException if either key or IV was not set
     */
    protected function encryptPassword($plaintext)
    {
        $this->assertKeyAndIvSet();

        return base64_encode(
            openssl_encrypt(
                mb_convert_encoding($plaintext, 'UCS-2LE'),
                'AES-256-CBC',
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
                'AES-256-CBC',
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
