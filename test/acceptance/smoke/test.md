# Schoolbox Smoke Test

# Purpose

This smoke test is intended to quickly test if there are no obvious
unrecoverable issues in Schoolbox's key features.

Unrecoverable issues include:
* data loss
  * it's generally not possible to reconstruct data that disappears, or worse
    yet never existed in the first place
* data leakage
  * short of a neuralyzer it's not possible to make an end user forget that
    they saw something that they shouldn't have seen


## Test Script


### CMS


#### News

as zsmith:
* go to http://192.168.36.10/news
* create a new news item, with:
  * headline
  * article
  * blurb
  * feature image
  * topic
  * limit its audience to teachers
* save it
* verify that:
  * it shows in the All topic
  * it shows in the topic I put it in
  * it doesn't show in other topics
  * its blurb (not article) shows in the topic view
  * its headline matches what I entered
  * in the article view, its article matches what I entered
  * when I modify it, everything I submitted is there

as drhodes:
* go to http://192.168.36.10/news
* verify that:
  * you can't see the news item; it's not audienced to you

as zsmith:
* go to http://192.168.36.10/news
* modify the news item you created
  * make it not yet published

as cglenn:
* go to http://192.168.36.10/news
* verify that:
  * you can't see the news item; it's not published yet


#### Text box

as zsmith:
* go to http://192.168.36.10/homepage/code/FREA1
* add a text box component
* add text to it
* enter:
  * a heading
  * content
    * including uploaded images
* save it
* verify that:
  * what I entered is there, including images
  * component's heading is what I entered
* modify it again
* verify that:
  * the heading is as I entered it
  * the content is as I entered it


#### Calendar

as zsmith:
* go to http://192.168.36.10/calendar/full
* create an event, with:
  * title
  * date
  * recurrence: every day until a particular day
  * event type
  * event location
  * details
  * level: personal
* save
* verify that:
  * it shows on my calendar
  * it doesn't show on other user's calendars
  * its title matches what I entered
  * it recurs every day until the day I specified
  * the type and location are preserved

as cglenn:
* go to http://192.168.36.10/calendar/full
* verify that the event doesn't show on your calendar; it's not yours
* go to zsmith's profile: http://192.168.36.10/search/user/151
* visit his calendar
* verify that you see his event

as zsmith:
* go to http://192.168.36.10/calendar/full
* modify the event you just created
  * make it not yet published

as cglenn:
* go to zsmith's profile: http://192.168.36.10/search/user/151
* visit his calendar
* verify that you don't see his event; it's not yet published


#### Files

as zsmith:
* go to http://192.168.36.10/homepage/code/FREA1
* add a files component
* upload a file (through the file upload form, accessible via "select a file"), with:
  * title
  * file
  * content
* save
* verify that:
  * file is what I uploaded
  * title is there
  * description is there
* upload a file (via drag/drop onto the component)
* verify that:
  * file is what I uploaded
* set one of the files you uploaded as private

as drhodes:
* go to http://192.168.36.10/homepage/code/FREA1
* verify that:
  * public files are visible
  * private files are not visible


### LMS


#### Due Work

> Note: this is more insidious than content creation, because it's not
  immediately obviously broken.
  For this reason, the script focuses more on what students can do, as what
  they interact with is less immediately obviously broken.

as zsmith:
* go to http://192.168.36.10/homepage/code/FREA1
* create a due work, with:
  * title
  * work type
  * description
  * attached files
  * accepts online submissions (multiple)
  * name
  * accepts online submissions
  * publish and open date: past
  * due and late date: future
  * mark visibility: staff only
  * rubric
    * select two indicators, add two levels
    * add descriptors for each available indicator
  * participation
    * only David Rhodes
* save
* verify:
  * everything I entered when creating is on the modify screen
* submit on behalf of David Rhodes
  * attach files
  * write something in the text area
* save
* go to David's submission history and verify:
  * files are attached
  * what I wrote is as I entered it

as drhodes:
* submit to that due work
  * attach files
  * write something in the text area
* save
* go to my submission history and verify:
  * files are attached
  * what I wrote is as I entered it

as zsmith:
* give drhodes feedback to this due work
  * attach files
  * write a comment
  * mark his rubric
* publish and verify that:
  * files are attached
  * comment is there
  * rubric is as I marked it

as drhodes:
* go to the submission history for the due work, and verify:
  * I don't see the feedback; mark visibility is staff only

as zsmith:
* modify the due work again
  * mark visibility: staff + student
  * due + late: past

as drhodes:
* go to the submission history for the due work, and verify:
  * I see the feedback
  * I can't submit; late date is past

as zsmith:
* modify the due work again
  * publish + open + due + late: future

as drhodes:
* go to http://192.168.36.10/homepage/code/FREA1
* verify:
  * I can't see the due work; it's not yet published


#### Quiz

as zsmith
* go to http://192.168.36.10/homepage/code/FREA1
* create a quiz, with:
  * title
  * work type
  * description
  * completion message
  * publish and open date: past
  * due and late date: future
  * mark visibility: staff only
* save
* add a section
* add at least two questions
* verify that:
  * base information is as I added it
  * sections and questions are as I entered them

as drhodes
* attempt that quiz
* complete my attempt

as zsmith:
* review drhodes' attempt
  * give drhodes full marks
  * give comments on each question
* finalise review
* verify that:
  * drhodes has full marks for each question

as drhodes:
* go to the summary for the quiz, and verify:
  * I don't see the feedback; mark visibility is staff only

as zsmith:
* modify the quiz again
  * mark visibility: staff + student
  * due + late: past

as drhodes:
* go to the summary for the quiz, and verify:
  * I see the feedback
  * I can't attempt; late date is past
* review my attempt
* verify that:
  * the answers were as I entered them

as zsmith:
* modify the quiz again
  * publish + open + due + late: future

as drhodes:
* go to http://192.168.36.10/homepage/code/FREA1
* verify:
  * I can't see the quiz; it's not yet published


#### Tasks

as zsmith
* go to http://192.168.36.10/homepage/code/FREA1
* create a task, with:
  * title
  * work type
  * description
  * publish date: past
  * due date: future
  * attached file
  * self-assessed: on
* save

as drhodes
* go to http://192.168.36.10/homepage/code/FREA1
* view the task
* verify that:
  * you see the image
* mark self as having finished task

as zsmith
* verify that:
  * drhodes has finished task


### Pastoral Care

as zsmith:
* go to http://192.168.36.10/pastoral
* search for David Rhodes
* create a record for him, with:
  * title
  * type + sub-type
  * severity
  * points
  * type
  * tags
  * general content:
    * text + attachment
  * confidential content
    * text + attachment
  * visibility
  * Teachers: general + confidential
  * Parents: general
* save
* verify that:
  * the record was created as I entered it

as cglenn (another teacher)
* go to http://192.168.36.10/pastoral
* search for David Rhodes
* view the record just created
* verify that:
  * i can see confidential content

as 132 (Jo Rhodes, David's mother)
* go to http://192.168.36.10/pastoral/student/230
* view the record just created
* verify that:
  * i can see general content
  * i can NOT see confidential content


### Email

as zsmith:
* go to http://192.168.36.10/mail/createMail.php
* in a new tab/window:
  * open Mailcatcher: http://192.168.36.10:1080
* compose an email, with
  * to
  * cc
  * bcc
  * subject
  * body
  * attachments
* send
* in Mailcatcher tab, verify that:
  * the mail went out as composed to all in to/cc/bcc
