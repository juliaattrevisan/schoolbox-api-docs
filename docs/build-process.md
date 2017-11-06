# Build Process

## Package Managers

[Composer](https://getcomposer.org/): PHP package management.

[Bower](https://bower.io/): Front end package management. We will be moving off this, as it's no longer recommended (even by themselves). We'll be moving to either NPM or Yarn.

[NPM](https://www.npmjs.com/): Currently being used to add a few build tools + Bower. As mentioned above, this may take over front end package management from Bower.

## Grunt Task Runner

[Grunt](https://gruntjs.com/) is used as a task runner. This is what is used to compile our JS and CSS. You can find all of our Grunt configurations in `Gruntfile.js` in the root folder of our repo.

### Running Grunt tasks locally vs from Vagrant

You have two options for running grunt tasks - from your Vagrant box or from your local PC. From experience, tasks will run a lot quicker from your local PC, but YMMV. By default, Vagrant will have grunt installed, so you can ssh into it immediately and run grunt tasks.

#### Setting up Grunt on your local PC
To install grunt on your PC, you'll first need to [install Node.js](https://docs.npmjs.com/getting-started/installing-node).

> Vagrant boxes currently run an old version of NodeJS, which provides the executable `node` instead of `nodejs`. You may
need to install a legacy version of NodeJS to run Grunt locally. On Ubuntu you can do this with `sudo apt install nodejs-legacy`.

From there, you can go your Schoolbox folder locally, and run `npm install`. This will install Grunt and any required Grunt plugins.

> You may also need to install the Grunt CLI tool; `sudo npm install -g grunt-cli`

### Grunt Tasks

Grunt tasks can be split into two categories:
* Development tasks: useful when developing, allowing quick changes and debugging.
* Production tasks: used when deploying, compresses the package as much as possible.

The exception to this is `grunt bower`, which will just run `bower install` (will install or update any Bower packages as required).

#### Development tasks

`grunt js`

Concatenates all JS files, will not minify or uglify the code.

`grunt sass`

Concatenates all Sass files and compile them to CSS. Will bypass the skin builder process (see `grunt build.sass` below). This means that this requires `grunt build.sass` to have been run once, so that the skin builder variables are already in a compiled file which can be imported in Sass (this will happen on vagrant provision).

> In order to run this locally, Ruby and the SASS gem must be installed. Once Ruby is installed, run `sudo gem install sass` locally.
For maximum compatibility with Schoolbox's build process, run `sudo gem install sass --no-rdoc --no-ri --version "3.4.13"`.

`grunt dev`

Full development build. Will run `grunt bower` plus the two tasks above.

`grunt watch`

A continuous process that will watch for changes to Sass and JS files, and run the required build task above based on the change. Allows you to set and forget, so that you don't have to continually run the tasks above manually each time you make a change.

**Note:** This will not currently work well if running from your Vagrant box (will require us to use NFS with Vagrant). Follow the steps above to setup Grunt on your local PC, and run this from there.


#### Production tasks

`grunt build.js`

Concatenates and compresses (uglify) all JS files.

`grunt build.sass`

Runs `tools/skin/builder.php` - which grabs school configured skin variables and puts them into a file that can be imported into Sass, then compiles all Sass files into CSS.

`grunt`

Full production build. Runs `grunt bower`, `grunt build.js` & `grunt build.sass`.
