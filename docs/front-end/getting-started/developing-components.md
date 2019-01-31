## Running the style guide locally

The best way to develop components is via the style guide that you are looking at right now.

If you are currently viewing this page at vagrant.schoolbox.com.au:8883- you are going to want to switch to running this server on your local machine though instead.

To do that- run `npm run styleguide` from the project root, then open localhost:8883. The reason for this is because Vagrant is not good at polling for changes to files, so the style guide does not update when you make changes.

Once you have this running- you should be able to create and modify components and they will 'hot' reload in your browser (meaning you don't have to page refresh).
