# Pastoral Care Record Comments

[Back](../README.MD)

## Links

[Schema Documentation](./README_SCHEMA.MD)  

## TLDR

The audit mapper/model are responsible for logging searches and access to pastoral care records and reports. There
is no user interface that displays this information, and it is purely recorded as an in-case-of-emergency record.

## Notifications

Pastoral care comments are also wired up to the notifications system. Whenever a comment in inserted/updated users
in the audience of the pastoral care record should receive a notification. It is important to note that the content
of the notification is intentionally vauge (e.g. "A pastoral comment has been updated"). This was done for
confidentiality reasons.

Several schools have asked for the name of the student to be added, as they complain that receiving multiple
identical vague notifications makes it impossible to know what has actually happened. Under no circumstances should 
the content include any information about the student or the pastoral record. As a later workaround this information
should be added to the main pastoral dashboard screen in a new section (e.g. "Recent Pastoral Notifications") where
it is actually appropriate to display potnetially confidential information. 

## No Parent Access

Comments are for staff only. They should never be visible to parents. 