Welcome to the Schoolbox style guide & front-end documentation.

Here you will find information on how we develop for the front-end of Schoolbox, as well as examples and documentation of our UI components.

## Stack

### Legacy

| Tech                                                            |              Purpose               |
| --------------------------------------------------------------- | :--------------------------------: |
| [Twig](https://twig.symfony.com/)                               |           PHP templating           |
| [Sass](https://sass-lang.com/)                                  |        CSS syntactic sugar         |
| [Foundation 5](https://foundation.zurb.com/sites/docs/v/5.5.3/) |             UI library             |
| [jQuery](https://jquery.com/)                                   |         JS syntactic sugar         |
| [Bower](https://bower.io/)                                      |         Package management         |
| [Grunt](https://gruntjs.com/)                                   | Task runner for compiling JS & CSS |

Most of the front-end code in Schoolbox is written with the technologies above.

### Current Standard

| Tech                                                            |              Purpose              |
| --------------------------------------------------------------- | :-------------------------------: |
| [Twig](https://twig.symfony.com/)                               |       PHP templating engine       |
| [Sass](https://sass-lang.com/)                                  |        CSS syntactic sugar        |
| [Foundation 5](https://foundation.zurb.com/sites/docs/v/5.5.3/) |            UI Library             |
| [React](https://reactjs.org/)                                   |    Component based development    |
| [TypeScript](https://www.typescriptlang.org/)                   |        Strict typing in JS        |
| [Jest](https://jestjs.io/)                                      |            Testing JS             |
| [Axios](https://github.com/axios/axios)                         |           XHR requests            |
| [Webpack](https://webpack.js.org/)                              |             Bundling              |
| [NPM](https://www.npmjs.com/)                                   | Package management & task running |
| [React Styleguidist](https://react-styleguidist.js.org/)        |      Automated documentation      |

As of early 2019 we have moved from writing our JS in jQuery to React & TypeScript. We have also started to move off Bower for package management onto NPM, and switched to Webpack for bundling.

Twig will still be used to load individual pages. All it will really do is just create a container element for React and pass through any required data from the PHP page load to React. At this point there is no plan to convert Schoolbox to a single page app (or a collection of module based single page apps).

For now, Foundation & Sass is still being used for UI components and styling. A decision is yet to be made about whether or what React UI library we might use.
