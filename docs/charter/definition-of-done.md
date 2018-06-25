# Definition of Done

## Story, Task or Bug
* Peer Review
    * Code Standard 
    * Static analysis
    * No unit tests are failing (related or otherwise)
    * Code runs and is functional in accordance to acceptance criteria
* Quality Assurance
    * Ensure that system working to an acceptable level by asking the question "What would happen if I&hellip;", eg.
        * "&hellip;turned off my vagrant's internet connection?"
        * "&hellip;changed to a different SIS connector?"
        * "&hellip;provisioned a different dataset?"
        * "&hellip;soft-deleted every \[insert entity type here] in the system?"
        * "&hellip;turned off RabbitMQ or Redis?"
    * Check the acceptance criteria are still met

## Story or Task
* Code is written to standards
    * Unit tests
    * Selenium tests

## Task
* Documentation
    * README created or updated for any jQuery plugins
    * Pantry updated with any design language changes
    
## Story
* Documentation
    * README for module is created/updated

## Sprint
* Committed stories done
* Sprint Review
* Retrospective

## Minor Release
* Release notes are written
* Regression Testing
    * Based on release notes
    * Utilise and chaos test all functionality affected by release notes
* Build is packaged and passes all checks

## Beta Release
* Release notes are written
* Build is packaged and passes all checks
* Non Functional Requirements (installation, system, etc) have been documented.
* Important Change Management tips have been documented and reviewed.

## Major Release
* Beta Release
* UAT Testing
* Everything in minor release