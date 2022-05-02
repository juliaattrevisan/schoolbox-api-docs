import '!style-loader!css-loader!sass-loader!../www/static/css/foundation/chromeFoundation.scss'; //add in the base styles
import '../stories/assets/skin.css';
import { initialize, mswDecorator } from 'msw-storybook-addon';

// Initialize MSW
initialize({
    onUnhandledRequest: 'bypass'
});

// Provide the MSW addon decorator globally
export const decorators = [mswDecorator];

export const parameters = {
    layout: 'fullscreen', //allow the component to expand to the full width and height of the Canvas
    chromatic: { pauseAnimationAtEnd: true }, // Notifies Chromatic to pause the animations when they finish at a global level.
}

