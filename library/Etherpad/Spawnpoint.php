<?php
/**
 * Spawnpoint
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Etherpad;


class Spawnpoint
{

    /**
     * The pad to respawn, from the local db
     *
     * @var null
     */
    private $pad = null;

    /**
     * An instance of etherpad authenticator
     *
     * @var Authenticator
     */
    private $authenticator = null;

    /**
     * A sitepage
     *
     * @var null
     */
    private $page = null;

    /**
     * Constructor
     *
     * @param $page
     */
    public function __construct($page)
    {
        $this->setPage($page);
        $this->setAuthenticator(new \Etherpad\Authenticator());
    }

    /**
     * Spawn a new pad
     * Save a stub record in our local db too
     *
     * @param $name
     * @param $homepageId
     * @return \Etherpad\Pad
     * @throws \Exception
     */
    public function spawn($name, $homepageId)
    {

        /* Check that you have write permissions
         */
        if ($this->getAuthenticator()->canCreate($homepageId)) {

            /* Make life a bit easier for Etherpad by using a hashed version
             * of the name, rather than let it get urlencoded in some strange
             * way by Etherpad itself
             *
             * Use an INSTANCE_ID value as a prefix, to help prevent namespace
             * collisions when generating $padName values (in case someone else
             * uses the same name as you - etherpad services are shared between
             * the live and dev environments, so we use INSTANCE_ID to keep 'em
             * separated to some extent)
             */
            $padName = md5(INSTANCE_ID . '_' . $name);

            /* Create a new group pad
             */
            $client = new \EtherpadLite\Client(\Config::get('etherpad_api_token'));

            /* Create a new pad, get group id
             * Create a group pad
             * Spawn a new group of users
             * Assign them to a group
             */
            $groupResult = $client->createGroup();

            if ($groupResult->getMessage() == 'ok') {
                $data    = $groupResult->getData();
                $groupId = $data['groupID'];

                /* Create a group pad
                 */
                $createResult = $client->createGroupPad($groupId, $padName);

                if ($createResult->getMessage() == 'ok') {

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
                    $newPad = new \Etherpad\Pad();
                    $newPad->setName($name);
                    $newPad->setBody('');
                    $newPad->setPadId($padId);
                    $newPad->setHomepageId($homepageId);
                    $newPad->setEnvironment(INSTANCE_ID);
                    $newPad->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
                    $newPad->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

                    $padMapper = new \Etherpad\PadMapper();
                    $padMapper->save($newPad);

                    return $newPad;
                } else {
                    throw new \Exception('Something strange happened when creating a new pad');
                }
            } else {
                throw new \Exception('Something strange happened when creating a group id');
            }
        } else {
            error(19);
        }
    }

    /**
     * Reincarnate an EPL instance of a pad that exists in our local db
     *
     * @throws \Exception
     */
    public function respawn($pad)
    {

        /* Remember the pad we tried to respawn
         */
        $this->setPad($pad);

        /* Check that you have write permissions
         */
        $authenticator = new \Etherpad\Authenticator();

        /* All people with read access can respawn a document
         */
        if ($authenticator->isParticipator($pad->getHomepageId())) {

            /* Make life a bit easier for Etherpad by using a hashed version
             * of the name, rather than let it get urlencoded in some strange
             * way by Etherpad itself
             *
             * Use an INSTANCE_ID value as a prefix, to help prevent namespace
             * collisions when generating $padName values (in case someone else
             * uses the same name as you - etherpad services are shared between
             * the live and dev environments, so we use INSTANCE_ID to keep 'em
             * separated to some extent)
             */
            $padName = md5(INSTANCE_ID . '_' . $this->getPad()->getName());

            /* Create a new group pad
             */
            $client = new \EtherpadLite\Client(\Config::get('etherpad_api_token'));

            /* Create a new pad, get group id
             * Create a group pad
             * Spawn a new group of users
             * Assign them to a group
             */
            $groupResult = $client->createGroup();

            if ($groupResult->getMessage() == 'ok') {
                $data    = $groupResult->getData();
                $groupId = $data['groupID'];

                /* Create a group pad
                 */
                $createResult = $client->createGroupPad($groupId, $padName);

                if ($createResult->getMessage() == 'ok') {

                    /* Get pad id to set to private and to pass it as a redirect element.
                     * Note that padId's come in the form g.abcdefg$padName
                     */
                    $data  = $createResult->getData();
                    $padId = $data['padID'];

                    /* Save new padId
                     */
                    $padMapper = new \Etherpad\PadMapper();
                    $this->getPad()->setPadId($padId);
                    $padMapper->save($this->getPad());

                    /* Store the local db's body text into the newly-spawned
                     * etherpad/redis instance
                     */
                    $client->setHTML($padId, '<!DOCTYPE html><html><head></head><body>' . $pad->getBody() . '</body></html>');

                    return $this->getPad();
                } else {
                    throw new \Exception('Something strange happened when creating a new pad');
                }
            } else {
                throw new \Exception('Something strange happened when creating a group id');
            }
        } else {
            error(19);
        }
    }

    /* Getters/Setters
     */

    /**
     * Set pad
     *
     * @param null $pad
     */
    private function setPad($pad)
    {
        $this->pad = $pad;
    }

    /**
     * Get pad
     *
     * @return null
     */
    private function getPad()
    {
        return $this->pad;
    }

    /**
     * Set authenticator
     *
     * @param \Etherpad\Authenticator $authenticator
     */
    private function setAuthenticator($authenticator)
    {
        $this->authenticator = $authenticator;
    }

    /**
     * Get authenticator
     *
     * @return \Etherpad\Authenticator
     */
    private function getAuthenticator()
    {
        return $this->authenticator;
    }

    /**
     * Set page
     *
     * @param null $page
     */
    private function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * Get page
     *
     * @return null
     */
    private function getPage()
    {
        return $this->page;
    }
}
