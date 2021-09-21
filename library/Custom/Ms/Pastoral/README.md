# Meriden School Pastoral Report (eReport)

The Meriden School Pastoral Report (commonly referred to as _eReport_) is a
custom report designed for Meriden School, so that their students' parents may
view and print a report of their student's:
* school and community activity participation in a given year
* overall approach and attitude to learning within a subject
* marks on a subject's assessment tasks, and how they compare to their cohort

The eReport unifies data from both their TASS database and from assessments
on their Schoolbox class pages.


## Accessing the eReport

A student's eReport is accessible from:
* the student's profile page
* the parent's students dashboard component


## Availability of the eReport

The report is currently only available to students who started year 7 in 2015
or later.

In addition, the eReport for a student for a year is currently only available
if they are or were in year 7, 8 or 9 that year.


## Pages

The eReport consists of two separate pages:


### Year Level

The Year Level page displays an overview of the student's school and community
participation for a given year.

This page displays a composite of data retrieved from TASS, and from the
student's tutor group page for that year.

The following data is retrieved from TASS:
* Tutor group home page class code
* Tutors
* Year coordinator
* Attendance
* Awards
* Co-curricular activities
* Community Service
* Positions of Office
* Subjects

The following data is retrieved from the tutor group home page:
* Attendance at athletics/swimming/camp
* Uniform
* Pastoral Activities Involvement
* Tutor Comment


#### More on tutor groups

The student's tutor group page is determined from the student's TASS enrolment
in a particularly coded class for the year.

The current year's tutor groups will have a class code matching
`-999[0-9]-[0-9]+`

Previous year's tutor groups will have an archived class code matching that
same pattern; it was previously the current year's tutor group page, but has
since been rolled over.

> There's no way to easily determine that a folder used to have a class code,
  so we have to rely on the class having been rolled over and therefore having
  an archived class code.

All data that is retrieved from the tutor group home page is retrieved from
specially named assessments that are on the page. These assessments exist in
the [Pastoral Report Template](https://eve.meriden.nsw.edu.au/homepage/3111)
course; once the student has been given feedback on one of those assessments,
and said feedback has been published, it will display on the year level page.


### Subject

The Subject page displays an overview of the student's approach and attitude to
learning, and their grade on assessment tasks within the subject as compared to
their entire cohort for that year.

The student's course grade, endeavour grade, approaches to learning and
assessment marks are retrieved from assessments from the class page.

The comparative data (grade distribution, assessment mean and standard
deviation) is derived from all marks given to the student's cohort for that
assessment.

> As with the tutor group report, neither a student's marks nor their cohort's
  are displayed on the subject report until they are published.


#### More on a student's cohort

The student's cohort for a subject is the other classes for that subject, for
the same year and year level. Think of it as the other students taking the same
subject that year, but who aren't in the same class.

> Cohort doesn't exist as a formal concept within Schoolbox. The eReport
  determines a class' cohort classes to be:
> * any folder with a matching subject part of the class code, which has the
    same parent folder.
    Example: the cohort of "2017 Classes > Year 7 > English (-1070-J)") is any
    sibling folder which has class code `-1070-[0-9]+`.

All assessment tasks are assumed to have been imported from a course. This
course relation is used to determine the cohort assessment; any assessment
on a cohort folder which is created from the same assessment within the base
course specification is considered a cohort assessment.


## Notes

The eReport is intended not just for viewing online, but for printing for a
parent's permanent record; for this reason, Meriden are notoriously
perfectionist regarding the visual layout and branding of the eReport.

The eReport's custom CSS is committed to our code repository
(www/static/css/ms/pastoral.scss), but is _not_ automatically compiled as
part of their skin; it must be copy-pasted into their custom CSS.
