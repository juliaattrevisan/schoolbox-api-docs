# Getting Started

This guide will presume you to know about Modules and Controllers so if you haven't completed those tutorials, please do so. You should also be familiar with the basic programming principles listed in the Quickstart guide.

## Basic Idea

Since we've set up a somewhat unconventional hybrid situation with the two frontend frameworks, it's kind of hard to find information about how to do things in Vue land.

We use phplib+twig for frontend in schoolbox. From those environments we need to set up a Vue entry point, which is where a Vue application can launch.

We'll pass values from the backend to the frontend, and then from there, to Vue land. It's actually a three-stage process for us.

Once you're in a Vue application, all the usual theory applies. We'll explore those as we go. We'll render the data we passed to Vue and do some things with it.

## Table of Contents

-   [Intro](00-intro.md)
-   [Setup Twig Container](01-setup-twig-container.md)
-   [Setup Entry](02-setup-entry.md)
-   [Payload](03-payload.md)
-   [Generate Payload](04-generate-payload.md)
-   [Computed Methods](05-computed-methods.md)
-   [Subcomponents](06-subcomponents.md)
-   [Async Payload](07-async-payload.md)
-   [Pagination](08-pagination.md)
