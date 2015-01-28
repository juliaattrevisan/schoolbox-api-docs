<?php
/**
 * A hydrator that runs a wrapped hydrator on create only.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_OnCreateOnly extends Synchronise_Base_Hydrator_CreateUpdate
{
    /**
     * @var Synchronise_Base_IHydrator
     */
    protected $hydrator;

    /**
     * Constructor
     *
     * @param Synchronise_Base_IHydrator $hydrator A hydrator to run only on create
     */
    public function __construct(Synchronise_Base_IHydrator $hydrator)
    {
        parent::__construct();
        $this->hydrator = $hydrator;
    }

    /**
     * {@inheritdoc}
     */
    protected function hydrateOnCreate($externalUser, $newUser)
    {
        return $this->hydrator->hydrate($externalUser, $newUser);
    }
}
