# Vue in Schoolbox

[Vue](https://vuejs.org/) is now being used increasingly more throughout Schoolbox. It's gradually replacing older code that relied more on jQuery and in particular Foundation. It's shown it does a pretty good job of parachuting in where ever you like and landing as soft or as heavy as needed. As the number of Vue files really starts to grow it's a good time to expand further on recommended best practices.

## Setting up a new Vue in Schoolbox

A guide to setting up the basics of Vue within Schoolbox is available [here.](00-intro.md)

## Essential Vue Rules

The below is a list of [rules](https://vuejs.org/v2/style-guide/) considered essential by Vue, scroll through them all or select an individual link:

-   [Always use multi-word component names](https://vuejs.org/v2/style-guide/#Multi-word-component-names-essential)
-   [Component data must be a function](https://vuejs.org/v2/style-guide/#Component-data-essential)
-   [Prop definitions should be as detailed as possible](https://vuejs.org/v2/style-guide/#Prop-definitions-essential)
-   [Always use key with v-for](https://vuejs.org/v2/style-guide/#Keyed-v-for-essential)
-   [Component style scoping](https://vuejs.org/v2/style-guide/#Component-style-scoping-essential)
-   [Use module scoping to keep private functions inaccessible from the outside](https://vuejs.org/v2/style-guide#Private-property-names-essential)

## Encouraged Schoolbox Rules

Have a look through all the other rules and keep them in mind while developing. From these I've proposed the below when adding to Schoolbox Vue. Primarily they're simply to reduce our cognitive load and make it easier trawling through Vue files now and into the future.

-   [Each component should be in its own file](https://vuejs.org/v2/style-guide/#Component-files-strongly-recommended)
-   [Filenames of single-file components should always be kebab-case](https://vuejs.org/v2/style-guide/#Single-file-component-filename-casing-strongly-recommended)
-   [Prop names should always use camelCase during declaration, but kebab-case in html/twig](https://vuejs.org/v2/style-guide/#Prop-name-casing-strongly-recommended)
-   [Directive shorthands should be used always](https://vuejs.org/v2/style-guide/#Directive-shorthands-strongly-recommended)
-   [Single-file components should always be in order script, template, style](https://vuejs.org/v2/style-guide/#Single-file-component-top-level-element-order-recommended)
-   [Component's should be structured with the following order](02-setup-entry.md#213-vue-component-basics)

## Vue pantry / testing

Coming soon to a cupboard near you, I'll update this further when implemented.

## Vue 3

Vue 3 has been released and offers some pretty neat new features as well as performance improvements. For now we'll wait on a Vue 2 to Vue 3 migration file that will be released sometime early this year. Once we've implemented that, we can ensure all our Vue 2 code is aok and move forward to Vue 3 following.
