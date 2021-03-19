# Schoolbox Developer Documentation

This folder and its subfolders contain various documents that are pertinent to
the Schoolbox product team.

## Where should documentation exist?

As a team member writing documentation, or looking for it, you should consider
where that documentation ought to exist.

As a general principle:

> Documentation should live as close to the problem space as it is possible to
> put it.

### Documentation for a class

Class documentation should exist in the docblock for that class.

The docblock should describe:

-   the purpose of the class
-   the concept that it represents

### Documentation for a class method

Method documentation should exist in the docblock for that method.

In addition to the standard `@param` and `@return` annotations, the docblock
should:

-   summarise what the method does
-   summarise the purpose of the method
-   detail any assumptions or gotchas within the method
