# TASS Coalition
SUDS TASS is a little more complex than the other SUDS connectors. TASS is a coalition of *products* which provide specific API endpoint data. We are using four such products.

1. LMS Integration 
   https://github.com/TheAlphaSchoolSystemPTYLTD/lms-integration
2. Student Details 
   https://github.com/TheAlphaSchoolSystemPTYLTD/student-details
3. Identify Management
   https://github.com/TheAlphaSchoolSystemPTYLTD/IdM
4. School Calendar And Notices
   https://github.com/TheAlphaSchoolSystemPTYLTD/school-calendar-and-notices/blob/master/getStudentNotices.md

# 1. Connector Setup
As usual we need two pieces of data for SUDS to work: *School Data Endpoint Host* and *School Data Endpoint Key*.

## 1.1 Endpoint Host
Each TASS API endpoint will have its own host somewhere. This goes into _School Data Endpoint Host_. 

For example, the test server we used to develop is this:

```
https://apidev.tassweb.com.au/tassweb/api/
```

But a school will have a different url that's kind of like that.

## 1.2 Endpoint Key (DSN Configuration)   
Each product has a set of credentials are as follows.

1. Company Code
2. App Code
3. Token

> Each product has its own authentication requirements. You'll need to ask TASS for these when you install a new TASS-based instance at a school.

Due to this complexity, there's a special format in which to supply these credentials such that they fit inside the `School Data Endpoint Key` field in SUDS. This crushing-down of credentials forms a text string called a `DSN`. It's a set of key-value pairs, serialized into a special format.

It's best illustrated with an example DSN that we used to develop SUDS TASS.

```
studentdetails:companycode=10;appcode=SB02;token=h6CELiLaJ46mho8FunFSGg==,identitymanagement:companycode=10;appcode=SB03;token=OqCt7L26i78MGqOyPJZLdg==,lmsintegration:companycode=10;appcode=SB01;token=mtiZcrWvtwnuNBnY1F7wtA==,schoolcalendarandnotices:companycode=10;appcode=SB04;token=ZGWtM4C94q/1vxIwWHunSg==
```

This is the configuration string for all the products. We can break this down into its constituent parts such that we can see what's going on.

### 1.2.1 Example
The following is the configuration string for the *Student Details* product. It starts with the prefix `studentdetails` which tells schoolbox that this is the set of credentials for the *Student Details* product.

It also contains the Company Code, App Code and Token. Simply put the appropriate values into this format when you get them for a school. 

```
studentdetails:companycode=10;appcode=SB02;token=h6CELiLaJ46mho8FunFSGg==
```

All the other products follow the exact same format and structure and principle.

```
identitymanagement:companycode=10;appcode=SB03;token=OqCt7L26i78MGqOyPJZLdg==

lmsintegration:companycode=10;appcode=SB01;token=mtiZcrWvtwnuNBnY1F7wtA==

schoolcalendarandnotices:companycode=10;appcode=SB04;token=ZGWtM4C94q/1vxIwWHunSg==
```

To fit them into the SUDS user interface we simply combine these with a comma `,` to get the `DSN` text for *School Data Endpoint Key*.

> There's an Excel spreadsheet inside SA-3870 called `suds tass generator.xlsx` which allows you to do it via Excel, if that's more your speed. The `DSN` is highlighted in green so when you fill in the spreadsheet you can just copy and paste this cell into _School Data Endpoint Key_.

If you've set everything correctly, the usual Status indicator should light up green.

### 1.2.2 School Calendar And Notices
The *School Calendar And Notices* product is optional - not every school will import calendar events from TASS. If a school wants to import such events, they should provide the DSN for *School Calendar And Notices*. Otherwise, you can omit it entirely.

### 1.2.3 Endpoint Configuration
Each product, while it has its own DSN and credentials, also has its own security settings in TASS itself. For this reason you'll need to ensure that TASS gives permissions on each of the endpoints. Normally they do alright, but occasionally they forget to give access to one of the products.

Under such circumstances the endpoints will fail with an error message which usually says something about *Bad endpoint at...* - you'll have to ask TASS to double-check their security and permission settings.

# 2. Importing

Then run the sisImporter in the usual spot. It'll chomp through all the data.

```
vagrant ssh
cd /vagrant/tools/synchronise
php sisImporter.php --perform -vvv
```


## 2.1 Campus List

Visit the `Lists > Campus` screen in schoolbox admin. For `External Timetable Code`, select the appropriate timetable from the dropdown. This is the name of the TASS timetable that corresponds to a particular campus. The logs tell you which timetable code(s) were imported but since it's school-configurable, consult with them to decide which timetable should display for each campus.

## 2.2 UPS Ruleset

When it's done with that, you'll need to run the UPS rulesets as per tradition. Set up all the assignations as required, such as campuses, roles and other user details. Don't forget to activate the rulesets before running:

```
vagrant ssh
cd /vagrant/tools/synchronise
php`userProvision.php -vvv --perform-update`
```

## 2.3 Calendar Import

Note that the Calendar import is performed separately.

```
vagrant ssh
cd /vagrant/tools/synchronise
php calendarImporter.php --perform -vvv
```

It'll import events and try to match them up with the items you've configured in schoolbox at `Lists > Event Types`. If they match *exactly*, a TASS calendar event will display the corresponding colour. If it doesn't match exactly, the TASS calendar event will be given the first Event Type configured in schoolbox.

The importer will tell you which event types are missing in schoolbox so you can add them accordingly, then re-run the calendar importer.

## 2.4 Vagrants

Vagrants will most likely have *Data Cache Timeout* set to `300` so if you're testing, don't forget to set this to `0` to ensure you aren't seeing a cached version.

The default UPS rulesets are also somewhat incompatible with TASS and you'll need to amend them slightly; you'll have to

1. arbitrarily put everyone in a campus of your choice, 
2. arbitrarily put everyone in a role of your choice, 
3. give everyone an arbitrary username. I just give them the username `{:id}` in the General Information section.