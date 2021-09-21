# NESA Report
This report contains a list of Users and the Assessments they completed. These Assessments use a particular set of Rubrics to communicate the user's grade, in their Feedback. The grade itself, however, isn't what we want; we are looking for the Descriptor that was chosen in each Rubric, for each Assessment, for each User.

## Usage
* Reprovision this branch
  * use large.tar.gz (you need the latest one). You might need to increase the Memory value in vagrant.yaml to succeed. Mine is set to 8gb
  * Ensure you use the Schoolbox connector in vagrant.yaml
* Log in as alaress admin
* You need to give people some marks. Visit the Alaress Teacher Course at http://192.168.36.10/homepage/1826 and mark some rubrics for people (Due Works begin in Unit 4)
* The people you marked - they need to be in the `user_professionaldevelopment` table. You can give people this data by visiting their profile screen, or add it manually to the aforementioned table.
  * Change the `site_school_code` to `help`. Due to a recent change, the Professional Development form only exists for `help`.
  * I usually fiddle with http://192.168.36.10/search/user/13 and put him in NSW, with an arbitrary number.
* Visit http://192.168.36.10/help/nesa
* Accept the defaults, then click *Generate*
* Click *Generate CSV*

You can Accredit someone with the Star on the right-hand column of the report screen.

# How it works

## 1. Provisioning
You need to use the latest *large.tar.gz* and the *schoolbox* connector first.

## 2. Selection
The first screen is Selection, http://192.168.36.10/help/nesa. This lets you choose the Indicators that you want in the report. I made this screen for me to understand what I was dealing with (had some random problems at the start) but eventually repurposed it a bit. Anyway, all the defaults are meaningful and represent the typical usage.
* *States* are only available when there is a user with a Professional Development record registered in that state. NSW are ticked by default because nowhere else matters for now.
* *Show unaccredited teachers* is used to exclude people who've been accredited in the past from showing up in result set. Every person costs time in data acquisition and rendering. Plus, we need to send the *difference* in accredited teachers to NESA, from month to month.

Just hit *Generate* to go forwards

## 3. Acquisition
This takes the list of users and the folders they're in (determined by `userAccess`) and finds the intersection with all the folders that use the Teacher Course. This intersection then becomes the data source for the report. I've built a special query to avoid hydration - there is no need for any unit or course data; we only care about Assessments because that's where our Rubrics lives.

Every person's Rubric is investigated for every Assessment in the Teacher Course. That's collated in a stack of (user, indicator, descriptor outcome) records, and given to Aggregation.

## 4. Aggregation
This takes the stack from Acquisition and combines them into a user-centric record, throwing all the Rubric indicators into one pile. I split this into two classes because at some point, Rubrics will be on a feedback level and I'll have to traverse all the feedbacks.

The results from Aggregation are given to Report.

## 5. Report
This renders the aggregated data into a user-friendly view. You can accredit people from this screen with the button on the right. This will exclude a user from all results unless you untick *Show only unaccredited teachers* checkbox.

From here you can generate a csv using the same settings that spawned the Report.

## 6. CSV
Dumps you a csv representation of the Report using the same data set.

# Known Issues
* *Data set is enormous*. Due to the phenomenal size of the data set in help.schoolbox.com.au, there is absolutely zero chance that a conventional mapper/model/hydration approach will succeed. I tried. This forces me to use an alternative approach to store and process information.
* *Hashtables*. I use a fair number of these to collate and organise data. Due to size of help db, I use hashtable structures to remember things, from indicators and their resultant descriptors, to professional development records, and instead compose them all together when rendering views.
* *Participation hole*. There isn't an existing method which lets me get all the assessments with which a user is involved. Because rubrics are currently stored on an assessment level, not a feedback level, I need to investigate and hydrate Assessments. This eventually forces me to manually find an intersection between "folders that a user is in" and "folders that came from the Teacher Course" to limit the amount of hydration shenanigans. I did invent a new Participation hydration method to plug this hole though.
* *Extras*. To further limit the amount of investigation and hydration, I added a mechanism that Aims can use to basically exclude people from the result set. This is the "Accreditation" route, which basically marks people as processed, never to be examined again in the general workflow (only really care about unaccredited folks) Everything is arranged to minimise the number of people, folders, assessments and rubrics as much as I can. Maybe there's more ways to do it, but I haven't seen them yet.
* *Future considerations*. I expect self-assessment stuff to affect this substantially. When rubrics exist in feedback, they'll add a whole extra dimension to this - not only will I have to look at user+assessment+rubric, but also the Entire History of Rubrics to fulfil a business rule. urgh. I made a crontab to preempt and address this, but I've deleted it for now  (you can see it in the commit history though)


