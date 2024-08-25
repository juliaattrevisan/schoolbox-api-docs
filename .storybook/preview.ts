/**
 * preview.js provides any extra scaffolding for all our stories, further info at: https://storybook.js.org/docs/react/configure/overview#configure-story-rendering
 * for us we are:
 *  pulling in all our CSS,
 *  MSW (https://storybook.js.org/addons/msw-storybook-addon) to be able to load dummy data into stories
 *  v-tables-3-select-rows so we can use it in stories
 *  adding window.Vue references as we load Vue globally in our vendors.js file in Schoolbox browser context
 */

import "!style-loader!css-loader!sass-loader!../www/static/css/core/core.scss"; //add in the base styles
import "../stories/assets/skin.css"; //and then also the default Schoolbox skin
import "!style-loader!css-loader!sass-loader!../stories/assets/styles/styles.scss"; // add in any other styles we want to use in storybook only
import { themes } from "@storybook/theming";
import DocumentationTemplate from "./DocumentationTemplate.mdx"; // so we can use our own custom documentation template
import { initialize, mswLoader } from "msw-storybook-addon"; //to be able to load mock data into stories
import { Preview, setup } from "@storybook/vue3"; // so can add in v-tables-3-select-rows and pinia
import { ClientTable } from "v-tables-3-select-rows";
import { createPinia } from "pinia";
import { ref, computed, watch, onMounted, onUnmounted, onBeforeUnmount, nextTick, defineCustomElement } from "vue"; // as Vue is now stored globally, provide reference to these functions so storybook can still run without browser context
import SbxPopOut from "@schoolboxVue/components/popOut/sbx-pop-out.ce.vue";
import SbxIcon from "@schoolboxVue/components/icon/sbx-icon.ce.vue";
import SbxFreeStyle from "@schoolboxVue/components/generic/sbx-free-style.ce.vue";
window.Vue = {};
window.Vue.ref = ref;
window.Vue.computed = computed;
window.Vue.watch = watch;
window.Vue.onMounted = onMounted;
window.Vue.onUnmounted = onUnmounted;
window.Vue.onBeforeUnmount = onBeforeUnmount;
window.Vue.nextTick = nextTick;
window.Vue.defineCustomElement = defineCustomElement;

initialize({
    // Initialize MSW
    onUnhandledRequest: "bypass", // If you want MSW to bypass unhandled requests and not do anything
});

setup((app) => {
    app.use(ClientTable); // so can use v-tables-3-select-rows in stories
    app.use(createPinia()); // so can use pinia in stories
});

/**
 * Register all web components here
 * We currently can't use the same initWebComponents as imports are automatically
 * hoisted to the top of the file, and window.Vue is not defined at that point
 */
export function register() {
    const SbxPopOutCE = window.Vue.defineCustomElement(SbxPopOut);
    window.customElements.define("sbx-pop-out", SbxPopOutCE);
    const SbxIconCE = window.Vue.defineCustomElement(SbxIcon);
    window.customElements.define("sbx-icon", SbxIconCE);
    const SbxFreeStyleCE = window.Vue.defineCustomElement(SbxFreeStyle);
    window.customElements.define("sbx-free-style", SbxFreeStyleCE);
}
register();

const preview: Preview = {
    loaders: [mswLoader], // so we can load mock data into stories
    parameters: {
        backgrounds: {
            default: "demo",
            values: [
                {
                    name: "demo",
                    value: "var(--color-background)",
                },
            ],
        },
        chromatic: {
            pauseAnimationAtEnd: true, // Notifies Chromatic to pause the animations when they finish at a global level.
        },
        docs: {
            theme: themes.dark,
            page: DocumentationTemplate, // use our own custom documentation template
        },
        layout: "fullscreen", //allow the component to expand to the full width and height of the Canvas
        options: {
            storySort: {
                // Sort stories alphabetically, but with Introduction related docs first
                method: "alphabetical",
                order: [
                    "Introduction",
                    "Getting Started",
                    ["Design Systems", "CSS Principles", "Design Tokens", "Contributing"],
                    "*",
                    "Draft",
                ],
            },
        },
    },
    tags: ["autodocs"],
};

export default preview;
