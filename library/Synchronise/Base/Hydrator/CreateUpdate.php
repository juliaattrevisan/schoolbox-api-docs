<?php
/**
 * Hydrator that determines if the user does or doesn't exist, and runs a
 * different method depending on whether they do or not.
 *
 * Should be added as late in the chain of hydrators as possible.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_CreateUpdate implements Synchronise_Base_IHydrator {

    /**
     * @var User
     */
    protected $userHandler;

    /**
     * Creates the hydrator.
     *
     * @todo inject user handler as a parameter instead of creating it here
     */
    public function __construct() {
        $this->userHandler = new User();
        $this->setup();
    }

    /**
     * Setup hook for this hydrator, after __construct is called.
     * By default does nothing.
     */
    protected function setup() {}

    /**
     * Does this user exist in the Schoolbox database?
     *
     * @param  array $externalUser @see $this->hydrate()
     * @param  array $newUser      @see $this->hydrate()
     * @return bool
     */
    protected function userExists($externalUser, $newUser) {
        $row = $this->userHandler->getBySynergyAndGroup(
            $newUser['synergy_id'], $newUser['group_id']
        );
        return !empty($row);
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        if (!$this->userExists($externalUser, $newUser)) {
            $newUser = $this->hydrateOnCreate($externalUser, $newUser);
        }
        else {
            $newUser = $this->hydrateOnUpdate($externalUser, $newUser);
        }
        return $newUser;
    }

    /**
     * Hydrator that only runs if the user doesn't exist in the Schoolbox
     * database.
     *
     * The base implementation does nothing, override it to do something only
     * when a user is created.
     *
     * @see $this->hydrate()
     */
    protected function hydrateOnCreate($externalUser, $newUser) {
        return $newUser;
    }

    /**
     * Hydrator that only runs if the user already exists in the Schoolbox
     * database.
     *
     * The base implementation does nothing, override it to do something only
     * when a user already exists.
     *
     * @see $this->hydrate()
     */
    protected function hydrateOnUpdate($externalUser, $newUser) {
        return $newUser;
    }
}
