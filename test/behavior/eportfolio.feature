Feature: ePortfolio
  So that others can see my work and interests
  As a logged-in user
  I need to be able to create my own personal ePortfolio pages

@javascript
Scenario: Create an ePortfolio template
  Given I go to "/test/reset.php"
  Then  I go to "/"
  And   I am logged in as "alaress" with password "alaress"
  When  I go to "/adminv2/eportfolio"
  And   I create a template "New Template"
  And   I add "Textbox" to "left" at position "1"
  And   I add "Blog" to "right" at position "1"
  And   I add "Textbox" to "right" at position "2"
  And   I add "Image Gallery" to "top" at position "1"
  # Currently need to reload so that component form inputs show up, because
  # components were added via AJAX.
  # Problem: reload doesn't wait for page to reload!
  Then  I go to "/adminv2/eportfolio"
  Then  I select "New Template" from "template-select"
  Then  I wait for AJAX
  And   I rename component on "left" at position "1" to "About Me"
  And   I repurpose component on "left" at position "1" to "A short paragraph about me"
  And   I rename component on "right" at position "1" to "My Thoughts"
  And   I repurpose component on "right" at position "1" to "A blog about my thoughts"
  And   I rename component on "right" at position "2" to "My Summary"
  And   I repurpose component on "right" at position "2" to "A longer summary about myself"
  And   I rename component on "top" at position "1" to "My Pictures"
  And   I repurpose component on "top" at position "1" to "Photos of myself"
  Then  I go to "/eportfolio"
  Then  I follow "add-page"
  And   I fill in "name" with "My Page"
  And   I select "New Template" from "page_template"
  And   I press "Create Page"
  Then  I see component "My Pictures" on "top" in position "1"
  And   I see component "About Me" on "left" in position "1"
  And   I see component "My Thoughts" on "right" in position "1"
  And   I see component "My Summary" on "right" in position "2"
