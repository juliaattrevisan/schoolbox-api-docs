# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev

# Build step for NPM's `www/static/js/bundle` folder.
#
# docker build -t schoolbox-npm -f docker/schoolbox-npm/Dockerfile .
FROM node:20-alpine

WORKDIR /usr/share/schoolbox

# Requirements for installing NPM packages
COPY ./.npmrc              /usr/share/schoolbox
COPY ./babel.config.js     /usr/share/schoolbox
COPY ./npm-shrinkwrap.json /usr/share/schoolbox
COPY ./package.json        /usr/share/schoolbox
COPY ./tsconfig.json       /usr/share/schoolbox
COPY ./webpack.common.js   /usr/share/schoolbox
COPY ./webpack.dev.js      /usr/share/schoolbox
COPY ./webpack.prod.js     /usr/share/schoolbox
COPY ./stories             /usr/share/schoolbox/stories
COPY ./types               /usr/share/schoolbox/types
RUN npm ci

# Additional requirements for building NPM packages are all over the shop:
# need to copy nearly everything
COPY ./library             /usr/share/schoolbox/library
COPY ./templates           /usr/share/schoolbox/templates
COPY ./vue                 /usr/share/schoolbox/vue
COPY ./www/static          /usr/share/schoolbox/www/static
RUN if [[ " prod production " =~ " $ENV " ]] ; then npm run publish ; else npm run build ; fi
