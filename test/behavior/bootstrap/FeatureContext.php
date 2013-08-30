<?php

use Behat\MinkExtension\Context\MinkContext;
use Behat\Mink\Element;

/**
 * Features context.
 */
class FeatureContext extends BaseContext {

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters) {
        //
        // Reset the database and filesystem back to a well-known state
        $this->useContext('mink', new MinkContext($parameters));
        $this->useContext('core', new Subcontext_CoreContext($parameters));
        $this->useContext('component', new Subcontext_ComponentContext($parameters));
    }

    /**
     * Resets the database to a known clean state.
     *
     * @BeforeScenario
     */
    public function resetDatabase(Behat\Behat\Event\ScenarioEvent $event) {
//        $this->printDebug('Resetting database');
        $this->getMinkContext()->visit('/test/reset.php');
    }

    /**
     * Resets the browser.
     *
     * @AfterScenario
     */
    public function resetBrowser(Behat\Behat\Event\ScenarioEvent $event) {
//        $this->printDebug('Resetting browser session');
        $this->getSession()->reset();
    }
}
