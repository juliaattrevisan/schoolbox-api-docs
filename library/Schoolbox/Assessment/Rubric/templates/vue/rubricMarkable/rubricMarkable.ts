// TODO: there'll eventually be a wrapper around this, like rubric-builder
import RubricMarkableGrid from "./rubric-markable-grid.vue";

document.querySelectorAll<HTMLDivElement>('[data-vue-app="rubric-markable"]').forEach((el) => {
    if (el.dataset.initialised) {
        return; // prevent double-initialisation if this script is included multiple times
    }

    el.dataset.initialised = "true";

    window.Vue.createApp({
        components: {
            RubricMarkableGrid,
        },
    }).mount(el);
});
