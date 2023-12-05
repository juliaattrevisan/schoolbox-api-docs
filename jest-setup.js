import "@testing-library/jest-dom"; // so we can use jest-dom in any test file https://github.com/testing-library/jest-dom

/**
 * As Vue is now stored globally, provide reference to these functions so tests can still run without browser context
 */
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue";
window.Vue = {};
window.Vue.ref = ref;
window.Vue.computed = computed;
window.Vue.watch = watch;
window.Vue.onMounted = onMounted;
window.Vue.onUnmounted = onUnmounted;
window.Vue.nextTick = nextTick;

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
