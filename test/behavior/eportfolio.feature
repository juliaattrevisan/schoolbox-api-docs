Feature: ePortfolio
  So that others can see my work and interests
  As a logged-in user
  I need to be able to create my own personal ePortfolio pages

Scenario: Create an ePortfolio template
  Given I am on "/"
  And   I am logged in as "alaress" with password "alaress"
  When  I am on "/adminv2/eportfolio"
  And   I create a template "New Template"
  And   I add "Textbox" to "left" at position "1"
  And   I rename component on "left" at position "1" to "About Me"
  And   I repurpose component on "left" at position "1" to "A short paragraph about me"
  And   I add "Blog" to "right" at position "1"
  And   I rename component on "right" at position "1" to "My Thoughts"
  And   I repurpose component on "right" at position "1" to "A blog about my thoughts"
  And   I add "Textbox" to "right" at position "2"
  And   I rename component on "right" at position "2" to "My Summary"
  And   I repurpose component on "right" at position "2" to "A longer summary about myself"
  And   I add "Image Gallery" to "top" at position "1"
  And   I rename component on "top" at position "1" to "My Pictures"
  And   I repurpose component on "top" at position "1" to "Photos of myself"
  Then  I am on "/eportfolio"
  Then  I click link "Add Page"
  And   I type "My Page" in "#name"
  And   I select "New Template" in "select[name='page_template']"
  And   I press "input[type='submit']"
  Then  I see component "My Pictures" on "top" in position "1"
  And   I see component "About Me" on "left" in position "1"
  And   I see component "My Thoughts" on "right" in position "1"
  And   I see component "My Summary" on "right" in position "2"
