# Schoolbox Developer Documentation

This folder and its subfolders contain various documents that are pertinent to
the Schoolbox product team.

## Expanded Documentation on:

-   [MVC](mvc/readme.md)
-   [Notifications](notifications/README.md)
-   [Vue](vue/README.md)
-   [Webpack](webpack/README.md)

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

### Documentation for a CLI tool

CLI tool documentation should exist in the CLI tool's docblock.

The CLI tool's class' `help()` method should also provide end-user help,
audienced to those who will be running the tool (usually, Schoolbox support).

See [./tools/example.php](./tools/example.php) for an example.

### Documentation for a module

Module documentation should be provided in a `README.md` file in the module's
root folder.

This documentation should:

-   describe the module's purpose
-   give a brief overview of the entities that the module encapsulates

### Documentation for a process

Documentation about a process for building Schoolbox belongs in this folder or
in one of its sub-folders.

Examples of process documentation include:

-   Help for [getting started with Schoolbox development](./mvc/readme.md)
-   The Schoolbox product team's [charter](./charter)

### Documentation for outside of product

At this stage, there is little need for documentation outside of Schoolbox to be
considered by the Schoolbox product team.

Other teams may have their own documentation on the Schoolbox product, possibly
written in conjunction with the product team: that documentation is the
responsibility of the other team, though product team members may and should
feel encouraged to contribute to this external documentation.

External documentation on Schoolbox exists in the following places:

-   [Schoolbox Help end-user documentation](https://help.schoolbox.com.au/homepage/2896)
-   [Support Wiki](https://schoolbox.atlassian.net/wiki/spaces/SUP/overview)
-   [Infrastructure Services Wiki](https://schoolbox.atlassian.net/wiki/spaces/IST/overview)
