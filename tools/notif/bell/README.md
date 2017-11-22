# Schoolbox Bell Daemon

This feature was commissioned by Melbourne Girls Grammar School (MGGS).

They wished to alert all senior students and staff (in practise, all users in 
the Senior Campus) of when a timetable period:
* is starting now
* has just ended
* is going to start in 5 minutes

This alert takes the form of a dialog window in Schoolbox, which may be 
dismissed in the same way as other dialog boxes.

## Requirements
* Only a single dialog will be raised for each event.
* The popup will disappear after 30 seconds, or as soon as the user manually
  dismisses it.
* The popups will have the following wording:
  * Started: "{$period.name}"
  * Ended: "{$period.name} has completed"
  * Starting soon: "{$period.name} starts in 5 minutes"
