import "@testing-library/jest-dom"; // so we can use jest-dom in any test file https://github.com/testing-library/jest-dom

import SbxPopOut from "@schoolboxVue/components/popOut/sbx-pop-out.ce.vue";
import SbxIcon from "@schoolboxVue/components/icon/sbx-icon.ce.vue";

/**
 * As Vue is now stored globally, provide reference to these functions so tests can still run without browser context
 */
import { ref, computed, watch, onMounted, onUnmounted, onBeforeUnmount, nextTick, defineCustomElement } from "vue";
window.Vue = {};
window.Vue.ref = ref;
window.Vue.computed = computed;
window.Vue.watch = watch;
window.Vue.onMounted = onMounted;
window.Vue.onUnmounted = onUnmounted;
window.Vue.onBeforeUnmount = onBeforeUnmount;
window.Vue.nextTick = nextTick;
window.Vue.defineCustomElement = defineCustomElement;

/**
 * Assign defaults to sbProps to prevent errors in tests
 * This isn't the full definition, it currently includes just the terms that are used in the tests
 */
window.sbProps = {
    colourDefaults: {
        constant: [],
        skin: [],
        skinUnique: [],
        swatches: [],
    }, // Since colourSettings.ts is used via an import chain, and directly accesses sbProps
};

/**
 * This is to handle any date display / manipulation within Schoolbox
 * As well as including the base package, any additional plugins that are
 * used within Schoolbox are also added.
 * https://day.js.org/
 */
import dayjs from "dayjs";
import advancedFormat from "dayjs/plugin/advancedFormat";
import customParseFormat from "dayjs/plugin/customParseFormat";
import duration from "dayjs/plugin/duration";
import relativeTime from "dayjs/plugin/relativeTime";
import isBetween from "dayjs/plugin/isBetween";
import arraySupport from "dayjs/plugin/arraySupport";
import weekOfYear from "dayjs/plugin/weekOfYear";
import isSameOrAfter from "dayjs/plugin/isSameOrAfter";

dayjs.extend(advancedFormat);
dayjs.extend(customParseFormat);
dayjs.extend(duration);
dayjs.extend(relativeTime);
dayjs.extend(isBetween);
dayjs.extend(arraySupport);
dayjs.extend(weekOfYear);
dayjs.extend(isSameOrAfter);

window.days = dayjs;

// Simulate jQuery's global $ and window.$
global.$ = global.window.$ = require("jquery");

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
}
register();
