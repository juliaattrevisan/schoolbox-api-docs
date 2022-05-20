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

# Command Summary
This is a quick reference for the synchronisation commands. They're explained further down, but they are listed here for expedience.

```
vagrant ssh
cd /usr/share/schoolbox/tools/synchronise

// Import all the profile and timetable data
php sisImporter.php -vvv --perform

// Import calendar events, if DSN provided
php calendarImporter.php -vvv --perform

// Create user accounts in schoolbox
php userProvision.php -vvv --perform-update
```

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
cd /usr/share/schoolbox/tools/synchronise
php sisImporter.php -vvv --perform
```

## 2.1 Campus List

Visit the `Lists > Campus` screen in schoolbox admin. For `External Timetable Code`, select the appropriate timetable from the dropdown. This is the name of the TASS timetable that corresponds to a particular campus. The logs tell you which timetable code(s) were imported but since it's school-configurable, consult with them to decide which timetable should display for each campus.

## 2.2 UPS Ruleset

When it's done with that, you'll need to run the UPS rulesets as per tradition. Set up all the assignations as required, such as campuses, roles and other user details. Don't forget to activate the rulesets before running:

```
vagrant ssh
cd /usr/share/schoolbox/tools/synchronise
php userProvision.php -vvv --perform-update
```

## 2.3 Calendar Import

Note that the Calendar import is performed separately.

```
vagrant ssh
cd /usr/share/schoolbox/tools/synchronise
php calendarImporter.php -vvv --perform
```

It'll import events and try to match them up with the items you've configured in schoolbox at `Lists > Event Types`. If they match *exactly*, a TASS calendar event will display the corresponding colour. If it doesn't match exactly, the TASS calendar event will be given the first Event Type configured in schoolbox.

The importer will tell you which event types are missing in schoolbox so you can add them accordingly, then re-run the calendar importer.

## 2.4 Vagrants

Vagrants will most likely have *Data Cache Timeout* set to `300` so if you're testing, don't forget to set this to `0` to ensure you aren't seeing a cached version.

The default UPS rulesets are also somewhat incompatible with TASS and you'll need to amend them slightly; you'll have to

1. arbitrarily put everyone in a campus of your choice,
2. arbitrarily put everyone in a role of your choice,
3. give everyone an arbitrary username. I just give them the username `{:id}` in the General Information section.

# 3. Data

There are some idiosyncracies in the TASS universe that we had to manage surgically. This relates to treatment of Families, their contact details; and also Teaching Staff and how they relate to Non-Teaching Staff.

Note that in order for a contact to appear they must be enabled in the Parent Lounge of TASS.
(The parent record needs Parent Lounge access)

## 3.1 Families and Split Families

Since TASS operates on the concept of *families*, and schoolbox operates on the concept of *people*, we've had to do some micromanagement to identify individuals inside a family such that we can import them into schoolbox from TASS.

### 3.1.1 Parent and Spouse prefixes
Parents and their Spouses share the same identifier in TASS. However, in schoolbox, we don't allow that. To deconflict individuals we prefix a parent's externalId with a `P` and their spouse with an `S`.

> This is not user-configurable, though the spouse prefix exists as constant `DEFAULT_SPOUSE_PREFIX` in case that ever needed to be changed. A school did try once, but eventually it got too hard and we just went back to this scheme.

#### 3.1.1.1 Example

If a TASS Family had the externalId `FAM001`, the primary parent - typically the Father, but not always - would have the externalId `PFAM001`. Their spouse, if they exist, would have the externalId `SFAM001`.

### 3.1.2 Split Family Suffix

There's this idea in the TASS endpoints called an `sfa_num`. As far as I can tell, this is applicable only to split families and probably stands for *split family number*.

In addition to the prefixes for parents, in a split family, we also append a suffix.

#### 3.1.2.1 Example

Let's take our `FAM001` and pretend it's a split family. One of the parents has an `sfa_num` of `1`.

The primary parent has the externalId `PFAM001` and when we add the `sfa_num` suffix, it becomes `PFAM0011`.

The same applies to the spouse.

### 3.1.3 Email Addresses

The contact information is contained in an `address` array in specific indexes. The indexes are configurable by the school in their TASS. To make it work with schoolbox, they need to set the following two fields in https://vagrant.dev.schoolbox.cloud/adminv2/setting/External%20DB

1. TASS Address Number for Mother
2. TASS Address Number for Father

#### 3.1.3.1 Example

For a family, address data is returned in this array. This example will **illustrate** the concept. It's not *exactly* like this, but this will communicate the idea. The address array typically contains the email addresses for each of the parents.

A family record is returned from the `getParents` endpoint like this:

```
family = [
   externalId = FAM001,
   father [
      address[1] = aaaa
      address[2] = bbbb
      address[3] = cccc
      address[4] = dddd
      address[5] = eeee
   ],
   mother [
      address[1] = aaaa
      address[2] = bbbb
      address[3] = cccc
      address[4] = dddd
      address[5] = eeee
   ]
]

```

This `address` array contains all the contact information for all the relevant people. It (mostly) exists in the same way for both mother and fathers ia family.

To identify a Mother's contact information, we can set the *TASS Address Number for Mother* to `4`. This makes her email address `dddd` when it imports.

We can do the same for the Father, setting *TASS Address Number for Father* to `5`, making his email address `eeee` when it imports.

> For more specific tech implementation details, see Family.php. Though the above illustrates the concepts, it turns out the data structure is actually a bit different when you come to coding it out when you encounter a split family. It's too technical to describe here, but there is a giant docblock in Family.php which explains it.

> To make things even stranger, one set of email addresses can come from `gehParents` endpoint while another can exist in `getCommunicationRulesDetails` endpoint.

---

!TODO Can we just...get rid of tass_addnum_* altogether and rely solely on getCommunicationRulesDetails to get the email addresses?

---

### 3.1.4 Phone Numbers in a Nuclear Family vs a Split Family

Bear with me on this one. It's a weird setup, so it'll take some time to explain and for you to understand.

Phone Numbers are derived from the `getCommunicationRulesDetails` endpoint. This endpoint returns a special mixed data structure containing a smooshing-together of:

1. Mother fields, each of which are prefixed with `m_`
2. Father fields, each of which are prefixed with `f_`
3. "Misc fields", whose fields aren't prefixed at all

There are two fields in `/adminv2/setting/External%20DB`. These fields correspond to the `address_description` in TASS and you'll see it in the `getCommunicationRulesDetails` endpoint.

1. TASS Address Description for Mother
2. TASS Address Description for Father

Let's elaborate how these two concepts meet by listing some axioms.

1. When a family record matches the *TASS Address Description for Mother* the misc fields are considered relevant to the Mother in a split family.
2. When a family record matches the *TASS Address Description for Father* the misc fields are considered relevant to the Father in a split family.
3. When a family record matches neither of these fields, the misc fields are distributed among the family's members and you're in a nuclear family.

In a nuclear family, the "Misc fields" are shared among the parents. It is presumed that any field ending in `_1` belongs to the Mother while anything ending in `_2` belongs to the Father.

#### 3.1.4.1 Example, Nuclear Family

Let's say the `getCommunicationRulesDetails` endpoint returned this family record:

```
"mobile_phone_2": "",
"adressee": "Paula Clark",
"sms_flg_2": "N",
"m_initials": "P",
"m_suffix": "suf",
"m_surname": "Clark",
"m_description": "Mother/Parent 1",
"m_preferred_name": "Paula",
"m_other_name": "PauOth",
"m_title": "Mrs",
"m_first_name": "Paula",
"address_description": "xxxxxxxxxxxxx",
"email_2": "",
"mobile_phone_1": "0427203657",
"f_initials": "E",
"f_suffix": "",
"f_surname": "Clark",
"f_description": "Father/Parent 2",
"f_preferred_name": "Edward",
"f_other_name": "",
"f_title": "",
"f_first_name": "Edward",
"addressee": "Paula Clark",
"email": "peterr@tassweb.com.au; realripper@tpg.com.au",
"business_phone": "3201 1302",
"parent_code": "000055",
"salutation": "Mr and Mrs Clark",
"home_phone": "3870 9987",
"relationship": "Step",
```

...and that the following settings were applied:

1. TASS Address Description for Mother = 'mum'
2. TASS Address Description for Father = 'dad'

These two admin fields don't match `address_description` at all, therefore you're looking at a nuclear family.

* `mobile_phone_1` belongs to the Mother
* `mobile_phone_2` belongs to the Father
* `email` belongs to the Mother
* `email_2` belongs to the Father
* They both live in the same place

#### 3.1.4.2 Example, Split Family
Let's say the `getCommunicationRulesDetails` endpoint returned this structure:

```

"mobile_phone_2": "",
"adressee": "Paula Clark",
"sms_flg_2": "N",
"m_initials": "P",
"m_suffix": "suf",
"m_surname": "Clark",
"m_description": "Mother/Parent 1",
"m_preferred_name": "Paula",
"m_other_name": "PauOth",
"m_title": "Mrs",
"m_first_name": "Paula",
"address_description": "mum",
"email_2": "",
"mobile_phone_1": "0427203657",
"f_initials": "E",
"f_suffix": "",
"f_surname": "Clark",
"f_description": "Father/Parent 2",
"f_preferred_name": "Edward",
"f_other_name": "",
"f_title": "",
"f_first_name": "Edward",
"addressee": "Paula Clark",
"email": "peterr@tassweb.com.au; realripper@tpg.com.au",
"business_phone": "3201 1302",
"parent_code": "000055",
"salutation": "Mr and Mrs Clark",
"home_phone": "3870 9987",
"relationship": "Step",
```

...and that the following settings were applied:

1. TASS Address Description for Mother = 'mum'
2. TASS Address Description for Father = 'dad'

The *TASS Address Description for Mother* matches the `address_description` value. Therefore you're looking at a mum in a spilt family.

* `mobile_phone_1` belongs to the Mother
* `mobile_phone_2` also belongs to the Mother
* `email` belongs to the Mother
* `email_2` also belongs to the Mother
* `home_phone, business_phone` belongs to the Mother

...and we have to pick the first meaningful value of the mobile phones and email addresses.

The father would get the same treatment for his data.

## 3.2 Teaching Staff and Employees

All Teaching Staff members are Employees.
Not all Employees are Teaching Staff.

Employees that aren't teaching students - "non-teaching staff" - consist of humans such as Librarians, Tech Support, Administrators, Counsellors, etc. who will utilise schoolbox in some way but they aren't necessarily teachers of students in a classroom setting.

To distinguish between them, we give non-teaching staff a special prefix `E`.

> It was possible to have an Employee's externalId conflict with a Student's externalId (they are typically numeric as far as we have seen) and thus we used this prefix to deconflict.

Non-teaching staff will never have a timetable.
Only teaching staff have a timetable.

### 3.2.1 Example

Let's say there was an Employee with the identifier `123`.  When we import them into schoolbox, their externalId will be `E123`. This deconflicts them with a student whose externalId is also `123`.

## 3.3 Photos
Photos are imported into `sis_user_profile` via the `scheduler` when `Load Photos From SIS` config flag is on. This reuses the `GetStudents` endpoint with additional args that provide photo binary data which we cache locally into storage.
