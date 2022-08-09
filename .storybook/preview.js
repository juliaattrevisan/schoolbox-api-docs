/**
 * preview.js provides any extra scaffolding for all our stories, further info at: https://storybook.js.org/docs/react/configure/overview#configure-story-rendering
 * for us we are: 
 *  pulling in all our CSS, 
 *  MSW (https://storybook.js.org/addons/msw-storybook-addon) to be able to load dummy data into stories
 *  v-tables-3-select-rows so we can use it in stories
 */

import '!style-loader!css-loader!sass-loader!../www/static/css/core/core.scss'; //add in the base styles
import '../stories/assets/skin.css'; //and then also the default Schoolbox skin
import { initialize, mswDecorator } from 'msw-storybook-addon'; //to be able to load mock data into stories
import { app } from '@storybook/vue3'; //so can add in v-tables-3-select-rows and pinia 
import { ClientTable } from 'v-tables-3-select-rows';
import { createPinia } from 'pinia';


initialize({ // Initialize MSW
    onUnhandledRequest: 'bypass'
});

export const decorators = [mswDecorator]; // Provide the MSW addon decorator globally, so any story can use

app.use(ClientTable); //so can use v-tables-3-select-rows in stories
app.use(createPinia()); //so can use pinia in stories

export const parameters = {
    chromatic: { 
        pauseAnimationAtEnd: true // Notifies Chromatic to pause the animations when they finish at a global level.
    }, 
    layout: 'fullscreen', //allow the component to expand to the full width and height of the Canvas
}