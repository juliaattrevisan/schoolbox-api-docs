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
        $this->useContext('confirm', new Subcontext_ConfirmContext($parameters));
        $this->useContext('autocomplete', new Subcontext_AutocompleteContext($parameters));
    }

    /**
     * Resets the browser after a scenario has run.
     *
     * @AfterScenario
     */
    public function resetBrowser(Behat\Behat\Event\ScenarioEvent $event) {
        $this->getSession()->reset();
    }

    /**
     * Attempts to take a screenshot of the error if the test failed.
     *
     * @AfterStep
     */
    public function takeScreenshotOnFailure(Behat\Behat\Event\StepEvent $event) {
        if ($event->hasException()) {
            // Assume screenshot is a PNG
            file_put_contents(
                dirname(__DIR__).'/screenshots/' . date('YmdHis') . '.png',
                $this->getSession()->getDriver()->getScreenshot()
            );
        }
    }
}
