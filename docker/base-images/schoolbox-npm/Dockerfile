# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev

# Build step for NPM's `www/static/js/bundle` folder.
FROM node:20-alpine

WORKDIR /usr/share/schoolbox

# Requirements for installing NPM dependencies for building JS
COPY ./.npmrc              /usr/share/schoolbox
COPY ./npm-shrinkwrap.json /usr/share/schoolbox
COPY ./package.json        /usr/share/schoolbox
RUN npm ci

# Additional requirements for building JS are all over the shop:
# need to copy nearly everything from Schoolbox repository.
COPY ./babel.config.js   /usr/share/schoolbox
COPY ./tsconfig.json     /usr/share/schoolbox
COPY ./webpack.common.js /usr/share/schoolbox
COPY ./webpack.dev.js    /usr/share/schoolbox
COPY ./webpack.prod.js   /usr/share/schoolbox
COPY ./library           /usr/share/schoolbox/library
COPY ./stories           /usr/share/schoolbox/stories
COPY ./templates         /usr/share/schoolbox/templates
COPY ./types             /usr/share/schoolbox/types
COPY ./vue               /usr/share/schoolbox/vue
COPY ./www/static        /usr/share/schoolbox/www/static
RUN if [[ " prod production " =~ " $ENV " ]] ; then npm run publish ; else npm run build ; fi
