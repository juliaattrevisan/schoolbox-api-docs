Feature: Course Builder
  In order to allow teachers to quickly add coursework to classes
  As the head of curriculum
  I need to be able to create a course

Scenario: Set a homepage as the course home
  Given I log in as "alaress" with password "alaress"
  And   I set setting "Course Builder:Course Home" to "Faculties"

Scenario: Create a course rubric set
  Given I log in as "wwhite" with password "password"
  Then  I go to homepage "Faculties/Science and Technology/Subjects/Chemistry/Year 12/Unit 1"
  # Rubrics: unique for course.
  # When adding a task list to a unit page: rubrics from parent are copied across?
  Then  I add component "Course Rubrics"
  Then  I set rubrics
  And   I add group "Boringness" with criteria "Dullness,Blandness,Mediocrity"
  And   I add group "Understanding" with criteria "Enlightenment,Knowledge,Cognizance"
  And   I press button "Save Changes"
  # Descriptors should also be pre-filled.

Scenario: Create a course
  Given I log in as "wwhite" with password "password"
  # How are available courses/lessons chosen?
  # For now:
  # * Courses: any homepage underneath course home
  # * Lessons: any homepage directly underneath selected course with a task list component
  Given I go to homepage "Faculties/Science and Technology/Subjects/Chemistry/Year 12/Unit 1/Chemical Reactions"
  Then  I add component "Task List"
  # Start adding tasks
  # Types:
  # * Links
  # * Online assessments
  # * Due work
  # * Checkbox (teacher)
  # * Checkbox (student)
  Then  I add a link task named "Visit this link" to visit "http://192.168.36.10/"
  Then  I add an online assessment task
  # online assessment stuff is separate
  Then  I fill in "title" with "Chemical Reactions 1"
  And   I fill in "description" with "This is a description<b>!</b>"
  And   I press "Continue"
  Then  I fill in "title" with "Section 1"
  And   I fill in "description" with "The first section of the test"
  And   I press "Create Section"
  Then  I select "Multiple Choice" from "type_id"
  Then  I fill in "question" with "Choose the correct answer"
  And   I fill in "marks" with "10"
  # Answers options
  And   I fill in right answer "1" with "Answer 1"
  And   I fill in wrong answer "2" with "Answer 2"
  And   I fill in wrong answer "3" with "Answer 3"
  And   I fill in wrong answer "4" with "Answer 4"
  And   I press "Add Question"
  # That's it for the online assessment, go back to the homepage
  Then  I go to homepage "Faculties/Science and Technology/Subjects/Chemistry/Year 12/Unit 1/Chemical Reactions"

  # Add a due work task
  And   I add a due work task
  Then  I fill in "title" with "Dumb Due Work"
  And   I select "Rubric" from "marktype"
  And   I check rubric criterion "Boringness - Blandness"
  And   I check rubric criterion "Boringness - Mediocrity"
  And   I check rubric criterion "Boringness - Dullness"
  And   I check "accept_submission"
  And   I press "Create"
  # That's it for the due work, go back to the homepage
  Then  I go to homepage "Faculties/Science and Technology/Subjects/Chemistry/Year 12/Unit 1/Chemical Reactions"

  # Add another due work item, this one not submittable online
  And   I add a due work task
  Then  I fill in "title" with "Offline Due Work"
  And   I select "Letter Grade" from "marktype"
  And   I uncheck "accept_submission"
  And   I press "Create"
  Then  I go to homepage "Faculties/Science and Technology/Subjects/Chemistry/Year 12/Unit 1/Chemical Reactions"

  # Create a self-assess task
  Then  I add a self-assessment task named "Read chapter 3"
  # Create a teacher assess task
  Then  I add a teacher-only assessment task named "Attend 3 classes"

  # Add more task lists
  Then  I go to homepage "Faculties/Science and Technology/Subjects/Chemistry/Year 12/Unit 1/Atomic structure and bonding"
  And   I add component "Task List"
  And   I add a self-assessment task named "Read chapter 4"
  And   I add a teacher-only assessment task named "Attend a laboratory"
  And   I add a due work task
  Then  I fill in "title" with "Dumb Due Work for bonding"
  And   I select "Letter Grade" from "marktype"
  And   I check "accept_submission"
  And   I press "Create"

  Then  I go to homepage "Faculties/Science and Technology/Subjects/Chemistry/Year 12/Unit 1/Macroscopic properties of matter"
  And   I add component "Task List"
  And   I add a self-assessment task named "Read chapter 5"
  And   I add a due work task
  Then  I fill in "title" with "Dumb Due Work 2"
  And   I select "Letter Grade" from "marktype"
  And   I check "accept_submission"
  And   I press "Create"

Scenario: Add a course to a class
  Given I log in as "cglenn" with password "password"
  # Test adding this new course to a group page. Students should be able to take this course.
  And   I follow link "Duke of Edinburgh"
  And   I add component "Course Task List"
  Then  I set course as "Science and Technology/Subjects/Chemistry/Year 12/Unit 1"
  # Set due dates for units
  Then  I set open date for unit "Chemical Reactions" as "today 12:00am"
  Then  I set close date for unit "Chemical Reactions" as "+9 days 12:00am"
  # Let's say this unit was due in the past
  Then  I set open date for unit "Atomic structure and bonding" as "today -9 days 12:00am"
  Then  I set close date for unit "Atomic structure and bonding" as "yesterday 12:00am"
  Then  I set open date for unit "Macroscopic properties of matter" as "today +10 days 12:00am"
  Then  I set close date for unit "Macroscopic properties of matter" as "today +19 days 12:00am"
  # Everything should be imported as if by magic

Scenario: Be a student in a course
  Given I log in as "sberryhill" with password "password"
  Then  I follow link "Duke of Edinburgh"
  Then  I should see the current unit "Chemical Reactions"
  And   I should see past unit "Atomic Structure and bonding"
  And   I should see future unit "Macroscopic properties of matter"

  # Check task completion - nothing yet
  And   the task "Visit this link" in "Chemical Reactions" should not be complete
  And   the task "Read chapter 3" in "Chemical Reactions" should not be complete
  And   the task "Attend 3 classes" in "Chemical Reactions" should not be complete
  And   the task "Chemical Reactions 1" in "Chemical Reactions" should not be complete
  And   the task "Dumb Due Work" in "Chemical Reactions" should not be complete
  And   the task "Offline Due Work" in "Chemical Reactions" should not be complete

  # What tasks are completable?
  And   I should be able to start task "Visit this link" in "Chemical Reactions"
  And   I should be able to start task "Read chapter 3" in "Chemical Reactions"
  And   I should not be able to start task "Attend 3 classes" in "Chemical Reactions"
  And   I should be able to start task "Chemical Reactions 1" in "Chemical Reactions"
  And   I should be able to start task "Dumb Due Work" in "Chemical Reactions"
  And   I should not be able to start task "Offline Due Work" in "Chemical Reactions"

  # Now I do some tasks in the Chemical Reactions unit
  When  I start the task "Visit this link" in "Chemical Reactions"
  And   I move backward one page
  Then  the task "Visit this link" in "Chemical Reactions" should be complete

  # Complete the chapter
  When  I start the task "Read chapter 3" in "Chemical Reactions"
  And   I reload the page
  Then  the task "Read chapter 3" in "Chemical Reactions" should be complete

  # Complete the online assessment
  When  I start the task "Chemical Reactions 1" in "Chemical Reactions"
  # Ideally, we could figure out a way to shortcut writing of online assessment behaviors
  And   I start the assessment
  And   I choose answer "Answer 1" for "Choose the correct answer" in "Section 1"
  And   I finish the assessment
  And   I return to the homepage
  Then  the task "Chemical Reactions 1" in "Chemical Reactions" should be complete

  # Complete the due work
  When  I start the task "Dumb Due Work" in "Chemical Reactions"
  And   I attach the file "test.txt" to "file"
  And   I press "Submit"
  And   I follow link "Duke of Edinburgh"
  Then  the task "Dumb Due Work" in "Chemical Reactions" should be complete

  # Can't complete any items in tasks in past or future
  And   I should not be able to start task "Read chapter 4" in "Atomic structure and bonding"
  And   I should not be able to start task "Attend a laboratory" in "Atomic structure and bonding"
  And   I should not be able to start task "Dumb Due Work for bonding" in "Atomic structure and bonding"
  And   I should not be able to start task "Read chapter 5" in "Macroscopic properties of matter"
  And   I should not be able to start task "Dumb Due Work 2" in "Macroscopic properties of matter"

Scenario: Mark a student's tasks as complete
  Given I log in as "cglenn" with password "password"
  Then  I follow link "Duke of Edinburgh"
  Then  I go to the progress report page
  And   I mark task "Attend 3 classes" in "Chemical Reactions" complete for "Scott Berryhill"
  And   I mark task "Read chapter 3" in "Chemical Reactions" incomplete for "Scott Berryhill"
  And   I mark task "Read chapter 4" in "Atomic structure and bonding" complete for "Scott Berryhill"
  And   I mark task "Read chapter 5" in "Macroscopic properties of matter" complete for "Scott Berryhill"
  # Mark an assessment as complete by giving the user a mark.
  Then  I follow link "Submissions"
  Then  I follow link "Offline Due Work"
  And   I give "Scott Berryhill" mark "A"

Scenario: Check tasks from teacher
  Given I log in as "sberryhill" with password "password"
  Then  I follow link "Duke of Edinburgh"
  And   the task "Visit this link" in "Chemical Reactions" should be complete
  And   the task "Read chapter 3" in "Chemical Reactions" should not be complete
  And   the task "Attend 3 classes" in "Chemical Reactions" should be complete
  And   the task "Chemical Reactions 1" in "Chemical Reactions" should be complete
  And   the task "Dumb Due Work" in "Chemical Reactions" should be complete
  And   the task "Offline Due Work" in "Chemical Reactions" should be complete
  And   the task "Read chapter 4" in "Atomic structure and bonding" should be complete
  And   the task "Read chapter 5" in "Macroscopic properties of matter" should be complete
