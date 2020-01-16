# Endpoint
The Example folder can be copied into /library/Schoolbox/External/Connector and have its namespaces updated accordingly. This will jump-start an integration with a third party SIS Student Information System.

## 1 Api
This class is used to make a connection to a third-party endpoint and access its raw data. If you need to cache that raw data, compose an *Api* instance inside another class.

For example, in iSAMS and Kamar, the wrapper class was called *Batchxml*; it used an *Api* instance to get the raw data and then stored a copy locally in memory after it parsed xml nodes so that it wouldn't have to repeatedly fetch the same data.

This separates the responsibilities of acquiring data and storing data for temporary processing.

## 2 Factory
This class stores the basic processing recipe, executing (in order)
* Prepare
* Profile
* Timetable Definition
* Timetable Classes and Enrolments

Optionally, it can perform any other steps that you need; it should be used as a basic skeleton which you will flesh out during your integration build. Note that while you can modify the order of processing, there are some sequential dependencies that prescribe the order given and yields unsavoury results if you try to mess with it too much. You're best off taking these four steps and building your implementation around them.

These steps are defined below.

### 2.1 Prepare
This sets up initial conditions for each import (if necessary) such as clearing tables, removing relations, creating folders, etc.

### 2.2 Profile
This assigns fields from your Endpoint into student, parent and staff profiles. It persists the data and trims out any unrelated profile records.

### 2.3 Timetable Definition
This decides what the timetable definition should be and persists it. Timetable Classes and Enrolments require the Definition to exist.

### 2.4 Timetable Classes and Enrolments
This discovers which users are in a class, from student attendees to staff teachers. This relation exclusively uses external ids and avoids the requirement that schoolbox user accounts exist prior to import.

#### 2.4.1 Multiple teachers for a class
If a class has multiple teachers, decide which one can be the authoritative teacher (they'll appear on the Timetable screen) and add the others as attendees just like you would with students; schoolbox knows how to deal with this situation and classifies those as teachers of the class.

#### 2.4.2 Iterators
Some endpoints don't make it easy for you to collate data about a class; information may be scattered across multiple xml nodes or even multiple files. While you could pack a whole bunch of complex logic into a foreach() loop you should consider building an *Iterator* which abstracts away and performs that complex logic instead. This allows you to subclass your traversal mechanism without having to change the way it's ultimately processed.

For examples you can visit iSams' *ScheduleIterator* or Kamar's *SubjectIterator*. These traverse an xml landscape to collate information about a class such as its students, teachers, period information, etc. into an array struct which can then be easily processed in a parent foreach().

Further reading:

https://en.wikipedia.org/wiki/Iterator_pattern

https://www.php.net/manual/en/class.iterator.php

### 2.5 Cleanup
Optionally you can introduce a Cleanup class which has the express purpose of tidying up details after processing, such as moving files or setting flags.

## 3 Specification
This checks an endpoint to ensure it's providing the data we are expecting to find. For xml contexts, their Specifications check that certain nodes exist in payloads.

This is used to improve the Healthcheck concept - not only must a third party service be accessible, but it must also return the data struct we are expecting to process.

In schoolbox v19.2 you'll see its outcome in an Endpoint Status screen, but you can also run it on the cli.

## 4 Status
This class exposes data in the Endpoint Status screen in schoolbox v19.2 This determines whether the endpoint was accessible and also generates a more detailed report based on Specification so that Professional Services can do basic diagnostics while they help a school set up their integration.


