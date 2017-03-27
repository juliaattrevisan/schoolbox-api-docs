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

use Alaress_Date_Format;
use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Connector\ICommunityPortal;
use UnexpectedValueException;

class CommunityPortal extends Base implements ICommunityPortal
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
    public function __construct($databaseExternal, $key, $initializationVector)
    {
        $this->key = $key;
        $this->initializationVector = $initializationVector;
        $this->allowCreateUser = $allowCreateUser;
        $this->databaseExternal = $databaseExternal;
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

        $result = $this->database->query(
            "
                SELECT    * 
                FROM      [dbo].[CommunityLogins] 
                WHERE     [ID] = {$this->database->smartQuote($communityId)}
            "
        );

        if ($this->database->countRows($result) > 0) {

            // Existing community login record, just update the password

            $query = "
                UPDATE    [dbo].[CommunityLogins]
                SET       [Password] = {$this->database->smartQuote($encryptedPassword)}
                WHERE     [ID] = {$this->database->smartQuote($communityId)}
            ";

            $this->database->query($query);

        } elseif ($this->allowCreateUser) {

            // No existing community login record, need to insert one for this to have any chance of doing something

            // This adds the Schools default page permissions to the users

            $query =
            "
                INSERT INTO [dbo].[CommunityLoginsPage]
                (
                    [ID],
                    [ConfigSeq],
                    [EnabledFlag]
                )
                SELECT  {$this->database->smartQuote($communityId)},
                        ConfigSeq,
                        CASE WHEN DefaultValue = 'TRUE' THEN 1 ELSE 0 END AS Enabled
                FROM    [dbo].[Config]
                WHERE   [dbo].[Config].[Key1] = 'CommunityPortal' AND
                        [dbo].[Config].[Key2] = 'Pages' AND
                        [dbo].[Config].[Key4] = 'EnabledFlag' AND
                        [dbo].[Config].[Type] = 'BIT' AND
                        [dbo].[Config].[Value] = 'True'
            ";

            $this->database->query($query);

            // This sets the password

            $date = Alaress_Date_Format::now()->getDatabase();

            $query = "
                INSERT INTO [dbo].[CommunityLogins]
                (
                    [ID],
                    [Type],
                    [Password],
                    [LastLoginDate],
                    [LoginType],
                    [SecurityTokenGuid],
                    [SecurityTokenIssueDateTime],
                    [PasswordHashed]
                )
                VALUES
                (
                    {$this->database->smartQuote($communityId)},
                    'CP',
                    {$this->database->smartQuote($encryptedPassword)},
                    '{$date}',
                    '',
                    NULL,
                    NULL,
                    ''
                )
            ";

            $this->database->query($query);

        } else {

            // There was no community portal password to reset, and automatic creation is disabled- so just return true
            // and exit out of this happily.

            return true;
        }

        // If one or more rows were affected then this must have done something positive... right?

        return intval($this->database->getOne('SELECT @@ROWCOUNT')) > 0;
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
