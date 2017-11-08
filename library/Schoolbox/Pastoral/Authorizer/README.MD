# Pastoral Care Route Authorizers

[Back](../README.MD)

This folder contains the authorizers used to determine access to the routes within the pastoral care system.

#### IsCommentOwner

Check if the current logged in user was the author of the comment requested in the route

#### IsInAudience

Check if the current logged in user was in the audience of the pastoral record requested in the route. This does not
provide any indication of their ability to view the public/private field contents, and should only be used to
determine if the record should be completely hidden from the user.

#### IsOwner

Check if the current logged in user is the author/owner of the pastoral record requested in the route

#### IsParentAccessAllowed

Check if parent access to the pastoral care system is allowed. This is defined by the 'pastoral_parent_access' config
variable.

#### IsPastoralModerator

Check if the current logged in user has **at least** pastoral moderator "read" permissions. This can be used to
override visibility rules and make all content visible to pastoral moderators. 

#### IsPastoralModeratorWrite

Check if the current logged in user has **at least** pastoral moderator "write" permissions. This can be used to
override edit permissions, as all users with this permission have access to edit (NOT delete) all pastoral records
in the system. 

#### IsPastoralModeratorModerate

Check if the current logged in user has full pastoral moderator "moderate" permissions. Users with this permission
have complete access to read/modify/delete any pastoral record in the system. 

#### IsReportOwner

Check if the current logged in user is the author/owner of the pastoral report requested in the route 