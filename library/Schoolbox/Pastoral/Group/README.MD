# Pastoral Care Groups

[Back](../README.MD)

## Links

[Schema Documentation](./README_SCHEMA.MD)  
[Pastoral Care Group Members](./Member/README.MD)  

## TLDR

Pastoral care groups were introduced in V17.0 to address the problem where staff leave the school, and we need a way
to dynamically update record permissions. Pastoral moderator can create pastoral groups from the pastoral dashboard
[http://192.168.36.10/pastoral/management/groups](http://192.168.36.10/pastoral/management/groups).

Groups can contain any arbitrary combination of staff members. It is not possible to add parents/students to
pastoral groups. The database schema allows for the cascading of groups- however there is no UI interface to allow this 
structure to be setup.