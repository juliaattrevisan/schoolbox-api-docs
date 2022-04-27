import '!style-loader!css-loader!sass-loader!../www/static/css/foundation/chromeFoundation.scss'; //add in the base styles
import '../stories/assets/skin.css';

export const parameters = {
    layout: 'fullscreen', //allow the component to expand to the full width and height of the Canvas
    chromatic: { pauseAnimationAtEnd: true }, // Notifies Chromatic to pause the animations when they finish at a global level.
}

