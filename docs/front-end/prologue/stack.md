### Legacy

| Tech                                                            |              Purpose               |
| --------------------------------------------------------------- | :--------------------------------: |
| [Twig](https://twig.symfony.com/)                               |           PHP templating           |
| [Sass](https://sass-lang.com/)                                  |        CSS syntactic sugar         |
| [Foundation 5](https://foundation.zurb.com/sites/docs/v/5.5.3/) |             UI library             |
| [jQuery](https://jquery.com/)                                   |         JS syntactic sugar         |

Most of the front-end code in Schoolbox is written with the technologies above.

### Current Standard

| Tech                                                            |              Purpose              |
| --------------------------------------------------------------- | :-------------------------------: |
| [Twig](https://twig.symfony.com/)                               |       PHP templating engine       |
| [Sass](https://sass-lang.com/)                                  |        CSS syntactic sugar        |
| [Foundation 5](https://foundation.zurb.com/sites/docs/v/5.5.3/) |            UI Library             |
| [Vue](https://vuejs.org/)                                       |    Component based development    |
| [Axios](https://github.com/axios/axios)                         |           XHR requests            |
| [Webpack](https://webpack.js.org/)                              |             Bundling              |
| [NPM](https://www.npmjs.com/)                                   | Package management & task running |

As of early 2019 we have moved from writing our JS in jQuery to Vue. We have also moved off Bower for package management onto NPM, and switched to Webpack for bundling.

Twig will still be used to load individual pages. All it will really do is just create a container element for Vue and pass through any required data from the PHP page load to Vue. At this point there is no plan to convert Schoolbox to a single page app (or a collection of module based single page apps).

For now, Foundation & Sass is still being used for UI components and styling. A decision is yet to be made about whether or what Vue UI library we might use.
