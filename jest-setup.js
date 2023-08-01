import "@testing-library/jest-dom"; //so we can use jest-dom in any test file https://github.com/testing-library/jest-dom

import { ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue"; // as Vue is now stored globally, provide reference to these functions so tests can still run without browser context
window.Vue = {};
window.Vue.ref = ref;
window.Vue.computed = computed;
window.Vue.watch = watch;
window.Vue.onMounted = onMounted;
window.Vue.onUnmounted = onUnmounted;
window.Vue.nextTick = nextTick;
