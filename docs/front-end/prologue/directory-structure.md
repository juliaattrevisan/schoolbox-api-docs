### react-components

This stores all the common Schoolbox components that can used across many modules.

Examples:

- TextInput
- Pagination
- Toast

### library/Schoolbox/[Module name]/react-components

This stores components that are _module specific_.

Generally these are just an extension of a common UI component, or collection of common UI components that have a specific rules and use in the module.

Examples:

- NewsCard - this extends the common UI component Card, with a specific standard for showing a snippet of an article
- NewsComponent - this contains Tabs (common UI component), NewsCardList (module specific component), etc.

Before creating a component in here- please check if you're creating something that we could potentially use in other modules. Eg. SaveForLater is something that we made for News, but it may be useful in other components so we will put it into the common UI components directory.

### library/Schoolbox/[Module name]/templates

This directory is contains Twig pages and fragments for the module, and React components that are acting as pages for the module.

Examples:

- CreatedByMe.tsx - does an ajax request for articles created by the user, shows a NewsCardList, along with Pagination, etc.
- CreatedByMe.twig - holds a container div for the above React code to attach to, as well as including the bundled JS file for the module, and sending through any data needed by React that's available on page load.

### www/javascript (legacy)

Contains all our legacy JS/jQuery code.

### www/javascript/components (legacy)

This is the destination of packages installed via Bower package manager.

One common beginner mistake is making code changes here. These packages are kept separate from the Schoolbox repo, any changes made here will not be comitted or deployed.
