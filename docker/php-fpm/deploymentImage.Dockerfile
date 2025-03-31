# Build args:
# ENV: if prod or production, build with prod dependencies only;
#      otherwise, build with dev dependencies
ARG ENV=dev

# Deployable dockerfile; assumes that:
# * an image from `Dockerfile` has been built, and is named `schoolbox-php-fpm`
# * this is run from the base dir of the `schoolbox` project
#
# It may be built with the commands:
# docker build -t schoolbox-php-fpm docker/php-fpm/
# docker build -t schoolbox-php-fpm-deploy -f docker/php-fpm/deploymentImage.Dockerfile .

# Copy necessary files and folders only
FROM schoolbox-php-fpm AS package

WORKDIR /usr/share/schoolbox

COPY ./conf          /usr/share/schoolbox/conf
COPY ./dav           /usr/share/schoolbox/dav
COPY ./library       /usr/share/schoolbox/library
COPY ./packages      /usr/share/schoolbox/packages
COPY ./sql           /usr/share/schoolbox/sql
COPY ./templates     /usr/share/schoolbox/templates
COPY ./tools         /usr/share/schoolbox/tools
COPY ./updates       /usr/share/schoolbox/updates
COPY ./www           /usr/share/schoolbox/www

# Composer build requirements: outputs to vendor
COPY ./composer.json /usr/share/schoolbox
COPY ./composer.lock /usr/share/schoolbox

# NPM build requirements: outputs to www/static/js/bundle
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
COPY ./vue                 /usr/share/schoolbox/vue


# NPM build
FROM node:20-alpine AS npm

WORKDIR /usr/share/schoolbox
COPY --from=package /usr/share/schoolbox /usr/share/schoolbox

# Need to somehow be logged in to Github in order to download some packages
RUN npm ci
RUN if [[ " prod production " =~ " $ENV " ]] ; then npm run publish ; else npm run build ; fi


# Composer build
FROM schoolbox-php-fpm AS final

WORKDIR /usr/share/schoolbox

COPY --from=package /usr/share/schoolbox /usr/share/schoolbox
COPY --from=npm     /usr/share/schoolbox/www/static/js/bundle /usr/share/schoolbox/www/static/js/bundle

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
RUN if [[ " prod production " =~ " $ENV " ]] ; then composer install -a --no-dev ; else composer install -a ; fi
RUN rm /usr/local/bin/composer
