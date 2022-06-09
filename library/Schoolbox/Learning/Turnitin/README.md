# Turnitin

This third-party vendor replaces Plagscan for checking similarity in student submissions. While it can't detect _plagiarism_ specifically it can detect when content looks kinda the same and leaves it up to the teachers to decide what to do next. A high similiarity score doesn't indicate wrongdoing but it will suggest that a closer look might be warranted.

Before you start, you should ensure you have a grasp on the following terminology and concepts:

* Assessment (Due Work, specifically)
* Submission Box and Submission Box Files
* Student Submissions and Student Submission Files
* Plag/Plagscan
* CLI Tools
* cron

Turnitin has synonymous concepts with Schoolbox; I'll always refer to them with "Turnitin" as the prefix as illustrated below.

* **Submission Box** synonymous with **Turnitin Group**
* **Submission Box File** synonymous with **Turnitin Group Attachment**
* **Student Submission File** synonymous with **Turnitin Submission**

> Sometimes I'll have referred to a thing in code called a "container". This is the turnitin file record that we have to set up before uploading the guts of a file into it. You could think of it as synonymous with our `storage` records and the file itself sitting in a data folder somewhere else.

## 1 Setup

You need two pieces of data to activate Turnitin:

* Turnitin API Host `config.turnitin_api_host`
  * A url, e.g. https://schoolbox.tii-sandbox.com
* Turnitin API Key `config.turnitin_api_key`
  * an alphanumeric string, e.g. `88c7b32b6f...`

Once you've provided both those values, schoolbox will always try to use Turnitin over Plagscan. It will do this even if your actual values are wrong so double check those when you enter them or when you're debugging.

### 1.1 Accounts

Each school has a Turnitin account, but this might be shared between that school's production and staging instances. This means it's possible to pollute one instance from the other, so be mindful of that.

### 1.2 Plagscan

If Turnitin is configured, it will always try to run in preference to Plagscan.

## 2 Overall Design

Turnitin is only applicable to Due Works. It functions synonymously with Plag so you can simply enable `Similarity Check` on the Due Work configuration screen and that will do two things.

* Any attached files in the Due Work will be uploaded to Turnitin as exclusions; student submissions won't be detected as similar content to any of these files
* Any attached files in a student's submission will be uploaded to Turnitin which will then process the content and detect similarity, put it in a report and display it in an iframe for us

There are multiple steps and stages involved in those two simple actions described above and we'll look at them in greater detail next; it's worth noting now that they're all controllable via a set of command line tools listed below.

* **[group.php](README.md#31-group-cli-tool)** manages attachments in Due Works, setting them up to be excluded from student submissions
* **[transmit.php](README.md#41-transmission-cli-tool)** uploads student submission files to Turnitin
* **[report.php](README.md#42-report-generation-cli-tool)** tell Turnitin to generate report and comparision percentages
* **[retrofit.php](README.md#43-retrofit-cli-tool)** upload old documents that should be in Turnitin, but aren't at the moment
* **[webhook.php](README.md#6-webhooks)** configure webhooks that Turnitin uses to tell us it did a thing

The first three tools `group transmit report` are integral to the Turnitin integration. All tools are found in the `/tools/turnitin` folder.

## 3 Exclusions

High similarity content can exist when students use a form guide or cover sheet as part of their submission; for example, a set of questions where students will write their answers underneath. Since all students share this set of questions, their similarity ratings will be expectedly high. To avoid this, teachers can upload files as attachments to the Due Work and students won't be compared against this data.

Simply attach the files. This will do the following:
* Set the status of this submission box's `turnitin_group` record to `pending`. This means the submission box has changed somehow, but we haven't yet figured out how
* An asynchronous process `group.php` will come along and look at the submission box's attached files `[1]` `[2]`
  * The asynchronous process `group.php` finds files that need to be added to Turnitin via api calls. These are added to `turnitin_group_attachment`
  * The asynchronous process `group.php` finds files that need to be removed from Turnitin via api calls. These are softdeleted from `turnitin_group_attachment`
  * The asynchronous process `group.php` will update the status of these `turnitin_group_attachment` records as the upload process executes.

 > `[1]` This is a lot of calls to be doing so you don't do it when you save the assessment. This would force users to wait a long time. This might also error out in a weird and unrecoverable manner. This would also apply when you import courses and if you import a lot of due works to a lot of places, you'll be waiting a long time.
>
> `[2]` This also lets users fiddle with their Attached Files in case they change their minds, upload more files, remove some etc. as they micromanage their Due Works. We can allow them these mutations and only send them up when we think they'll be done with it all, without incurring the penalty of uploading something that has been immediately changed.

### 3.1 Group CLI Tool

Exclusions can be processed via the cli tool `group.php` which you can run manually. The help text goes into more detail about usage which I won't repeat here.

```
vagrant ssh;
cd /usr/share/schoolbox/tools/turnitin;
php group.php --help

php group.php
```

`turnitin_group` records experience five statuses:

* **pending** This submission box has somehow changed, but we haven't yet figured out how
* **group** The submission box doesn't have a Turnitin Group so we're making one now and assigning it to this record.
* **group_attachment** There are attached files to look at and set up. Attachments are set up in two stages: a file record on Turnitin's end, and then we send the file contents itself into this record.
  * Any attached files that don't have a `turnitin_group_attachment` record will get one and a corresponding `group_attachment_id` from Turnitin
  * Any files that were previously attached will get their `turnitin_group_attachment` record softdeleted.
  * Any files that are still attached and are still in Turnitin will remain untouched (nothing to do)
* **uploading** we're sending the file contents to Turnitin.
* **complete** we finished sending the file contents to Turnitin.
* **error** something went wrong; check the `error_code` and `error_message` columns for more information about what went wrong

#### 3.1.1 Turnitin Group Identifiers

A Turnitin Group Identifier consists of the submission box id and the `config.site_type` var, to deconflict staging activity from production activity, since schools will share their Turnitin account across both instances.
* e.g. `187-vagrant` a Turnitin Group for submission box `187` in a vagrant
* e.g. `187-prod` a Turnitin Group for submission box `187` in a production instance
* A staging instance should only see things in the format `187-dev` and a production instance should only see things in the format `187-prod`; if they get mixed up, you're in trouble and something went wildly wrong

> There are other `site_types` that aren't fully described here; check the config table for more options that could exist. And yes, it's called `dev`, not `staging`. I don't know why.

### 3.2 Reprocessing

You can use the `group.php` tool to reprocess broken uploads and so on. Set the broken `turnitin_group` record's status to `pending` and re-run the `group.php` tool (optionally specifying the primary identifier of `turnitin_group` with `--group` flag). This will perform the exact same steps listed above, checking the Turnitin Group Identifier, cross-referencing uploaded files etc.

This tool does not care about the completion status in `turnitin_group_attachment` and will fix them accordingly, as described above.

## 4 Student Submission Files

Student Submissions are managed by two separate and distinct stages: **Transmission** and **Report Generation**.

When students create their submissions and attach their files, a `turnitin_report` record is created for that file. This is a processing queue, similar to `turnitin_group`. The file setup and file upload is handled by the **Transmission** phase.

The second phase, **Report Generation**, involves asking Turnitin about the status of the similarity report. It will do it in its own time so we can't hurry it up or force it to do very much. Anyway, once it finishes we look at the result and store some data locally for performance reasons, but also for archiving reasons since it's nice to show the percentage calculated even if Turnitin is unavailable (either a discontinued service or the internet died, etc.)

> There's a third pseudo-phase, **Retrofitting**, which enables previously-forgotten files to be put into the **Transmission** phase, kind of like a prequel which sets up the main act.

### 4.1 Transmission CLI Tool

The Transmission phase is handled by the cli tool `transmit.php` which you can run manually. The help text goes into more detail about usage which I won't repeat here.

```
vagrant ssh;
cd /usr/share/schoolbox/tools/turnitin;
php transmit.php --help

php transmit.php
```

`turnitin_report` records experiences these six statuses during the Transmission phase:

* **pending** This file was submitted to schoolbox by the student, but we still have to upload it to Turnitin
* **group** The submission box associated with this file doesn't have a `turnitin_group_id` so we're making one now and assigning it to the corresponding `turnitin_group` record. This is a precautionary measure because this tool might run before `group.php` which would normally obtain the `turnitin_group_id` value for us.
* **submission** We told Turnitin to set up a file record on their end, into which we upload the actual contents separately.
* An implicit step occurs here which is the Acceptance of EULA. Turnitin wanted us to prompt users every time for every file but that's insanity, so I programmatically accept it on everyone's behalf. There are no status changes for this; it's an implicit step that I arbitrarily take for UX reasons.
* **uploading** We started sending the file's contents to Turnitin.
* **loaded** We successfully sent the file's contents to Turnitin. This is the last step of Transmission.
* **error** something went wrong; check the `error_code` and `error_message` columns for more information about what went wrong

#### 4.1.1 Reprocessing

You can manually set the status of a `turnitin_report` record to `pending` which will allow `transmit.php` to reprocess it as if it had never been processed at all.

Alternatively, you can forcibly reprocess a record; using the `--force` flag will allow any `turnitin_report` stuck in `pending, group submission` statuses to be processed as if it had never been processed at all.

Additionally you can specify an individual record in `turnitin_report` to be subjected to the aforementioned.

This affords you a very specific tool which can resolve most scenarios where uploads just...didn't work, or if records randomly get stuck in a status.

> This tool is scheduled in cron. It runs using the --force flag, which will forcibly process anything that's been hanging around. Check the crontab for when this runs.

### 4.2 Report Generation CLI Tool

The Report Generation phase is handled by the cli tool `report.php` which you can run manually. The help text goes into more detail about usage which I won't repeat here.

```
vagrant ssh;
cd /usr/share/schoolbox/tools/turnitin;
php report.php --help

php report.php
```

`turnitin_report` records experiences these five statuses during the Report Generation phase:

* **loaded** We successfully sent the file's contents to Turnitin. This is the last step of Transmission.
* **prepared** Turnitin processed the contents we uploaded in the previous step and now lets us schedule a report generation.
* **analysing** Report generation was scheduled and/or in progress at the moment. There's no obvious timer for when this occurs, but it seems to happen pretty quickly upon demand.
* **complete** We finished generating the report and we saved some data locally. We can present information to our users now.
* **error** something went wrong; check the `error_code` and `error_message` columns for more information about what went wrong

There are actually multiple stages in the Report Generation phase. They are natural boundaries based around Turnitin taking as long as it takes to do whatever it does.  That sounds weird, so let me elaborate with the Upload example.

When we upload a file, Turnitin needs to do something on its end to finish the processing. We don't know how long it takes. We don't know when it finishes. We just have to keep asking to see if it's done. Then it goes from the `loaded` status and put into the `prepared` status.

> You'll see this is a distinct step in the tool itself: `$this->processLoadedReports($processor);`

Similarly, when we take a `prepared` report and tell Turnitin to generate a report, we don't know when that finishes either. We just have to keep asking until we find out we can move it from `prepared` to `analysing` and then from `analysing` to `complete`.

> You'll see this is a distinct step in the tool itself: `$this->processPreparedReports($processor);`

They'll run back to back in the intended order, so anything that got moved from `loaded` to `prepared` will immediately be processed toward `analysing`, shuffling everyone along the mortal coil so to speak.

#### 4.2.1 Reprocessing

You can manually set the status of a `turnitin_report` record to `loaded` which will allow `report.php` to reprocess it as if it had never been processed at all.

Additionally you can specify an individual record in `turnitin_report` to be subjected to the aforementioned.

This affords you a very specific tool which can resolve most scenarios where reports just...didn't generate, or if records randomly get stuck in a status.

> I haven't seen reports ever get stuck in a status in this phase, so this should be a rare usage. Most _stuck_ issues occur in the Transmission phase.

> This tool is scheduled in cron. Check the crontab for when this runs.

### 4.3 Retrofit CLI Tool

Sometimes teachers will enable Similarity Check after students have submitted their files and now they're expecting them to be similiarity checked. Since the Similarity Check switch was previously off, none of the files will have been sent to Turnitin; none of them will have ever been set up at all.

Fortunately it's quite easy to do that, retroactively.

```
vagrant ssh;
cd /usr/share/schoolbox/tools/turnitin;
php retrofit.php --help

php retrofit.php --assessment=1234
```

The Transmission phase only ever needed a `student_submission_file_id` in the `turnitin_report` table for `transmit.php` to do the necessary work.  The `retrofit.php` tool simply does this.

However, it does this in a specific way. It won't upload all of a student's previous submissions' files; it **only uploads the student's most recent submission's files**.

> This is how Plag worked so I kept it synonymous and familiar in terms of expected behaviour. It's also going to send less data to Turnitin and reduce server load and bandwidth usage.

In the following timeline, if a student makes these submissions:

```
Oct 5, 8:32pm
    - File 4
    - File 5

Oct 4, 1:00pm
    - Similarity Check enabled

Oct 4, 9:00am
    - File 1
    - File 2
    - File 3
```

...only **File 4** and **File 5** will be sent to Turnitin.

Retrofit will occur when Similarity Check becomes enabled when it previously wasn't. Retrofitted files will be given the same treatment as described in the **Transmission** and **Report Generation** phases, as if everything had been in place from the beginning.

To do it, you need to know the **assessment id** specifically. Supply it in the cli option as described in the tool.

### 4.4 Historical CLI Tool

A school will sometimes have come from a plagscan era, where they had older documents in a different vendor. These schools want the older documents to be included in Turnitin's similarity checks without manufacturing a Turnitin Report.
* These older documents' similarity checks are archived in storage and are serviced by whatever the previous vendor (Plagscan) did.
* If you made a Turnitin Report for them now, you wouldn't get the same result anyway, because you're similarity checking them out of chronological order.

Therefore, to include them into Turnitin's similarity repository, we follow a different code path that means we **index** the documents' contents instead of generating reports for them.

This functions in largely the same manner as previously described; we still need to upload the data into Turnitin, create Submissions, etc. With two key variances:
* When we create the Submission in Turnitin, we set a special flag `extract_text_only`.
* Instead of generating a report, we access a different endpoint which `indexes` the document's contents.
* A new status **indexing** can now appear.

This means we can reuse the existing paradigms for Transmit and Report. The database column `turnitin_report.historical` identifies when documents are historically added explicitly by us, or when they're created in the Turnitin system as normal by our users.

> Historical documents have the integer 1 in turnitin_report.historical

Historical additions are performed manually and explicitly by running the Historical CLI tool.

```
vagrant ssh;
cd /usr/share/schoolbox/tools/turnitin;
php historical.php --help

php historical.php
```

You can specify the start and end dates for the tool and it'll only historically add submission files in that date range. If you don't specify the dates, the following are assumed:
* created after "now minus one year, start of the day"
* created before "now, end of the day"

> Circumstantially this lets you historically add data "from the future" if you are clever enough to set the datetimes accordingly in student_submission_file.

For historical additions, `turnitin_report`records experiences these five statuses during the Report Generation phase which it shares with the Report tool:

* **loaded** We successfully sent the file's contents to Turnitin. This is the last step of Transmission.
* **prepared** Turnitin processed the contents we uploaded in the previous step and now lets us schedule a report generation.
* **indexing** The file's contents is being added to Turnitin's index, which is the data it uses for its similarity checks.
* **complete** We finished generating the report and we saved some data locally. We can present information to our users now.
* **error** something went wrong; check the `error_code` and `error_message` columns for more information about what went wrong

> Historical additions takes the students' the most recent non-deleted submission in a submission box. If you made fifty submissions, Historical will only add the most recent non-deleted submission. This includes submissions with multiple files.

### 4.5 Group Lister CLI Tool

I made this because we were missing some fields in Turnitin. We weren't supplying them. This tool lets you see what Turnitin knows about its Groups, exposing to you the `due_date` and `report_generation` field values. It does not mutate data and therefore does not have a locking or `perform` mechanism(s). It just interrogates the third party for info.

```
vagrant ssh;
cd /usr/share/schoolbox/tools/turnitin;
php grouplist.php
```

## 5 CLI Tools Appendix

Here are some general statements about the cli tools. I couldn't think of a nice way to fit it all in earlier, so I'll collate them here.

* **Each tool shares the same implementation as the schoolbox app.** For example, the Retrofit code that you use on the cli is exactly the same code as used by the schoolbox controllers.
  * The things that I used to build and diagnose are the same things that people use in their daily tasks so if something goes wrong in either place, it should be observable and identifiable (and loggable)
  * This means you can run the entire Turnitin process without its webhooks; you can do it all manually or via cron (same thing in my book)
  * This means you have a fallback in case your schoolbox is behind a firewall, if Turnitin didn't tell you it finished something etc. You can always initiate and re-initiate the worfklow manually.

* **You can smash the tools' usage, normally.** If you feel like being in the Matrix, you can run all three cli tools back-to-back, for the ultimate _make everything go_ maneuver:

```
vagrant ssh;
cd /usr/share/schoolbox/tools/turnitin;

php group.php; echo; php transmit.php; echo; php report.php
```

### 5.1 Crontab usage

The cli tools `group transmit report` are all in the crontab.

`transmit` tool will always run with `--force` to ensure that anything currently in the incomplete statuses will get a little shove. It's true that some files might be in those states normally and aren't stuck at all; they'll still get a little shove anyway.

### 5.2 Inspector

The CLI tools have a lock mechanism that prevents you from running them on top of each other. I've added their identifiers to the lock inspector so you can manually unlock it.

```
vagrant ssh;
cd /usr/share/schoolbox/tools/lock;

php inspector.php
```

The inspector prompts you interactively.

### 5.3 misc

I did consider an Explorer tool so you can query things in Turnitin that aren't exposed anywhere else - e.g. _what files does Turnitin know about for this submission box_ in case you suspect Turnitin knows something that schoolbox doesn't (desync). I haven't built that yet. You'll have to make your own for now, in a test.php

This is kind of nice to have; despite the logging being far more articulate now than in the old Plag days, it doesn't tell you absolutely everything.

## 6 Webhooks

This is the last cli tool but it's significant enough to warrant its own section in this document.

Webhooks are specific urls in the schoolbox instance to which Turnitin will `POST` payloads whenever it finishes certain steps. Remember those steps I described earlier where we had to repeatedly ask Turnitin if it did them - the upload finishing, the report preparation and generation - Turnitin will try to tell us when it finishes those steps by sending data to a url we elect.

>  However, we can't _guarantee_ this succeeds due to a variety of reasons:
>
>* It turned out that Turnitin is firewalled out of the school
>* The internet didn't work when Turnitin tried to tell us something
>* Schoolbox died randomly e.g. got upgraded, or we broke it
>
> Therefore the entire system was built around the premise of webhooks not working at all. The cli tools themselves can keep the whole process ticking along in case the webhooks didn't work.

### 6.1 Headers (Turnitin Event Types)

Using a special header key in the request, `X-Turnitin-EventType`, we can decide what Turnitin just told us about, and respond accordingly. There are three things it tells us about.

* It finished an upload and sent `X-Turnitin-EventType = SUBMISSION_COMPLETE`
* It finished making a report and sent `X-Turnitin-EventType = SIMILARITY_COMPLETE`
* It finished saving changes made to a report and sent `X-Turnitin-EventType = SIMILARITY_UPDATED`

### 6.2 Configuration

The url we elect is an absolute url, which makes it unique per schoolbox instance. It's always located in `/learning/turnitin/webhook` because that's really obvious, at least to me. However, Turnitin accounts need to be told about this: a procedure called _registering webhooks_. Oddly enough there's no user interface for that, unlike Plag, so we have to do it programmatically.

Fortunately, I've made that easy for you too, with the `webhook.php` cli tool, but it's a bit more nuanced than the others.

```
vagrant ssh;
cd /usr/share/schoolbox/tools/turnitin;
php webhook.php --help
```

#### 6.2.1 List of Webhooks currently registered

With this, you can see the list of webhooks currently registered:

```
php webhook.php
```

Typically you'll see just one webhook ever listed here with the arbitrary name that I gave it: _Unified Turnitin Endpoint_. It's a cool name, and it spells out `UTE`. There's a little description underneath about what it does:

```
https://vagrant.dev.schoolbox.cloud/learning/turnitin/webhook is set up to receive X-Turnitin-EventType values SIMILARITY_COMPLETE, SUBMISSION_COMPLETE, SIMILARITY_UPDATED
```

While it's not particularly obvious, this means you could reconfigure the Turnitin webhooks to set up three urls, each dedicated to receiving one of the `X-Turnitin-EventType` headers if you so choose. I've built the Turnitin system to accommodate either approach - unified, or disparate. See the `Turnitin Controller` for more details on how it does this.

Anyway, underneath the description is the Turnitin Webhook Identifier:

```
Identifier: 410f74b8-02cd-4232-b36a-9953fb2c87bb
```

You don't need that for anything but it does uniquely identify a specific webhook just in case you ever did.

### 6.3 Registering

You can register webhook with this simple step:

```
php webhook.php --reset
```

This will figure out the absolute url of the schoolbox instance and tell Turnitin about it. Whatever webhook was previously there will be deleted prior to the new one being added. That means you can use this if your domain name changes and you needed to update your webhook url.

For the most part you only need to run `php webhook.php --reset` at the time of setup and then you can not worry about it.

> You can't register multiple webhooks with this tool; you can only register (and re-register) the Unified Turnitin Endpoint. Do that if the absolute url of your instance changes.
