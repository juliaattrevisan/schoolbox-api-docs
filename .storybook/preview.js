/**
 * preview.js provides any extra scaffolding for all our stories, further info at: https://storybook.js.org/docs/react/configure/overview#configure-story-rendering
 * for us we are:
 *  pulling in all our CSS,
 *  MSW (https://storybook.js.org/addons/msw-storybook-addon) to be able to load dummy data into stories
 *  v-tables-3-select-rows so we can use it in stories
 *  adding window.Vue references as we load Vue globally in our vendors.js file in Schoobox browser context
 */

import "!style-loader!css-loader!sass-loader!../www/static/css/core/core.scss"; //add in the base styles
import "../stories/assets/skin.css"; //and then also the default Schoolbox skin
import { themes } from "@storybook/theming";
import { initialize, mswDecorator } from "msw-storybook-addon"; //to be able to load mock data into stories
import { setup } from "@storybook/vue3"; // so can add in v-tables-3-select-rows and pinia
import { ClientTable } from "v-tables-3-select-rows";
import { createPinia } from "pinia";
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue"; // as Vue is now stored globally, provide reference to these functions so storybook can still run without browser context
window.Vue = {};
window.Vue.ref = ref;
window.Vue.computed = computed;
window.Vue.watch = watch;
window.Vue.onMounted = onMounted;
window.Vue.onUnmounted = onUnmounted;
window.Vue.nextTick = nextTick;

initialize({
    // Initialize MSW
    onUnhandledRequest: "bypass", // If you want MSW to bypass unhandled requests and not do anything
});

export const decorators = [mswDecorator]; // Provide the MSW addon decorator globally, so any story can use

setup((app) => {
    app.use(ClientTable); //so can use v-tables-3-select-rows in stories
    app.use(createPinia()); //so can use pinia in stories
});

export const parameters = {
    backgrounds: {
        default: "demo",
        values: [
            {
                name: "demo",
                value: "rgb(229, 239, 247)",
            },
        ],
    },
    chromatic: {
        pauseAnimationAtEnd: true, // Notifies Chromatic to pause the animations when they finish at a global level.
    },
    docs: {
        theme: themes.dark,
    },
    layout: "fullscreen", //allow the component to expand to the full width and height of the Canvas
    options: {
        storySort: {
            // Sort stories alphabetically, but with Introduction related docs first
            method: "alphabetical",
            order: ["Introduction", "Getting Started", ["Contributing", "CSS Principles", "Design Tokens"], "*"],
        },
    },
};
