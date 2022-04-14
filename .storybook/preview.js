import '../www/static/css/core/chromeFoundation.css'; //currently webpack with npm run watch is compiling faster than the below
// import '!style-loader!css-loader!sass-loader!../www/static/css/foundation/chromeFoundation.scss';

export const parameters = {
    layout: 'fullscreen', //allow the component to expand to the full width and height of the Canvas
    chromatic: { pauseAnimationAtEnd: true }, // Notifies Chromatic to pause the animations when they finish at a global level.
}

