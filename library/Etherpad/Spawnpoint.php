<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Etherpad;

use Alaress_Date_Format;
use EtherpadLite\Client;
use InvalidArgumentException;
use PDOException;
use RuntimeException;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;

/**
 * Class Spawnpoint
 *
 * @package Etherpad
 */
class Spawnpoint
{

    /**
     * Fallback host when ETHERPAD_HOST wasn't defined
     */
    private const ETHERPAD_LOCALHOST = 'http://localhost:9001';


    /**
     * Messages that worked out equal this string
     */
    private const MESSAGE_OK = 'ok';


    /**
     * An instance of etherpad authenticator
     *
     * @var Authenticator
     */
    private $authenticator;


    /**
     * @var string
     */
    private $etherpadHost;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->authenticator = new Authenticator();
        $this->etherpadHost  = defined('ETHERPAD_HOST') ? ETHERPAD_HOST : self::ETHERPAD_LOCALHOST;
    }


    /**
     * Make life a bit easier for Etherpad by using a hashed version
     * of the name, rather than let it get urlencoded in some strange
     * way by Etherpad itself
     *
     * Use an INSTANCE_ID value as a prefix, to help prevent namespace
     * collisions when generating $padName values (in case someone else
     * uses the same name as you - etherpad services are shared between
     * the live and dev environments, so we use INSTANCE_ID to keep 'em
     * separated to some extent)
     * @param string $name
     * @return string
     */
    private function encodePadName(string $name): string
    {
        return md5(INSTANCE_ID . '_' . $name);
    }


    /**
     * Spawn a new pad
     * Save a stub record in our local db too
     *
     * @param string $name
     * @param int    $homepageId
     * @return null|Pad
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    public function spawn(string $name, int $homepageId): ?Pad
    {

        /* Check that you have write permissions
         */
        if ($this->authenticator->canCreate($homepageId)) {
            $padName = $this->encodePadName($name);

            /* Create a new group pad
             */
            $client = new Client(\Config::get('etherpad_api_token'), $this->etherpadHost);

            /* Create a new pad, get group id
             * Create a group pad
             * Spawn a new group of users
             * Assign them to a group
             */
            $groupResult = $client->createGroup();

            if ($groupResult->getMessage() === self::MESSAGE_OK) {
                $data    = $groupResult->getData();
                $groupId = $data['groupID'];

                /* Create a group pad
                 */
                $createResult = $client->createGroupPad($groupId, $padName);

                if ($createResult->getMessage() === self::MESSAGE_OK) {

                    /* Get pad id to set to private and to pass it as a redirect element.
                     * Note that padId's come in the form g.abcdefg$padName
                     */
                    $data  = $createResult->getData();
                    $padId = $data['padID'];

                    /* Must pass strings to change the status - like the rest of this box
                     * https://github.com/ether/etherpad-lite/issues/561
                     */
                    $client->setPublicStatus($padId, 'false');

                    /* Spawn a record in our own db
                     */
                    $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

                    $newPad = new Pad();
                    $newPad->setName($name);
                    $newPad->setBody('');
                    $newPad->setPadId($padId);
                    $newPad->setHomepageId($homepageId);
                    $newPad->setEnvironment(INSTANCE_ID);
                    $newPad->setCreatedAt($now);
                    $newPad->setUpdatedAt($now);

                    $padMapper = new PadMapper();
                    $padMapper->save($newPad);

                    return $newPad;
                }

                throw new RuntimeException('Something strange happened when creating a new pad');
            }

            throw new RuntimeException('Something strange happened when creating a group id');
        }

        error(19);

        /* Storm doesn't understand the error() function and insists this should return null
         * in accordance with the method signature
         */
        return null;
    }


    /**
     * Reincarnate an EPL instance of a pad that exists in our local db
     *
     * @param Pad $pad
     * @return null|Pad
     * @throws Exception
     * @throws RuntimeException
     */
    public function respawn(Pad $pad): ?Pad
    {
        /* Check that you have write permissions
         * All people with read access can respawn a document
         */
        if ($this->authenticator->isParticipator($pad->getHomepageId())) {
            $padName = $this->encodePadName($pad->getName());

            /* Create a new group pad
             */
            $client = new Client(\Config::get('etherpad_api_token'), $this->etherpadHost);

            /* Create a new pad, get group id
             * Create a group pad
             * Spawn a new group of users
             * Assign them to a group
             */
            $groupResult = $client->createGroup();

            if ($groupResult->getMessage() === self::MESSAGE_OK) {
                $data    = $groupResult->getData();
                $groupId = $data['groupID'];

                /* Create a group pad
                 */
                $createResult = $client->createGroupPad($groupId, $padName);

                if ($createResult->getMessage() === self::MESSAGE_OK) {

                    /* Get pad id to set to private and to pass it as a redirect element.
                     * Note that padId's come in the form g.abcdefg$padName
                     */
                    $data  = $createResult->getData();
                    $padId = $data['padID'];

                    /* Save new padId
                     */
                    $padMapper = new PadMapper();
                    $pad->setPadId($padId);
                    $padMapper->save($pad);

                    /* Store the local db's body text into the newly-spawned
                     * etherpad/redis instance
                     */
                    $client->setHTML($padId, "<!DOCTYPE html><html><head></head><body>{$pad->getBody()}</body></html>");

                    return $pad;
                }

                throw new RuntimeException('Something strange happened when creating a new pad');
            }

            throw new RuntimeException('Something strange happened when creating a group id');
        }

        error(19);

        /* Storm doesn't understand the error() function and insists this should return null
         * in accordance with the method signature
         */
        return null;
    }
}
