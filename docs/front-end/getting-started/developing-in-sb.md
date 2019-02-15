## Switching to Vagrant

When it comes time to implement your components into Schoolbox- you'll need to switch to developing in Vagrant.

To get started, run `npm run watch` from the project root. This will watch for changes to your JS files and recompile the bundle. Vagrant doesn't have hot reloading like the style guide server, so you will need to page refresh to see your changes.

## Creating a React-based page

In order to start composing component-based pages you need to do the following:

- Create a new route in PHP (see our MVC documentation in /docs/mvc/ for how to do this).
- In the twig file for your new route- create a container element with an ID that you can attach JS to
  - eg. `<div data-component="CreatedByMe"></div>`
  - Please use [PascalCase](http://wiki.c2.com/?PascalCase) for the ID in this case, as a pattern for us to recognise that this is attaching to React
  - If you need to send any data from PHP/Twig through to React- you can do that with `data-prop-*` attributes.
    - (eg. data-prop-article-title="Example title"), which can then be used as `props.articleTitle` in React.
    - This hooking up of data to React is done via a plugin called [React Habitat](https://github.com/DeloitteDigitalAPAC/react-habitat)
    - **Note:** if you want to send a number or json- your attribute needs to be slightly different. See [this part of the readme](https://github.com/DeloitteDigitalAPAC/react-habitat#user-content-passing-properties-props-to-your-components)
- Create a React component that will server as the container for the composition of components for your page.
  - eg. library/Schoolbox/Comms/News/templates/CreatedByMe.tsx which imports all the components it needs for the page and displays them.
- Create a module JS file (if one doesn't exist already) which will import all React page components for the module and attach them to containers like the one above.
  - eg. library/Schoolbox/Comms/News/news.tsx imports CreatedByMe (React page component) and attaches it to #CreatedByMe if it exists on the page.
- Create an entry point for the module in Webpack (if one doesn't exist already). This will compile your module JS file into a JS bundle, which you will then include in Twig.
  - eg. news.bundle.js is created by Webpack from the result of the news.tsx entry file.
- Include the JS bundle from Webpack on your Twig file.
  - eg. `<script src="/javascript/components/news.bundle.js"></script>`
