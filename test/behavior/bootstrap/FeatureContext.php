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
        $this->useContext('course_builder', new Subcontext_CourseBuilderContext($parameters));
        $this->useContext('assessment', new Subcontext_AssessmentContext($parameters));
    }

    /**
     * Resets the browser after a scenario has run.
     *
     * @AfterScenario
     */
    public function resetBrowser(Behat\Behat\Event\ScenarioEvent $event) {
        $this->getSession()->reset();
    }
}
