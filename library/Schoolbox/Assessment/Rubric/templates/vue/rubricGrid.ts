import RubricGrid from "./rubric-grid.vue";

document.querySelectorAll<HTMLDivElement>('[data-vue-app="rubric-grid"]').forEach((el) => {
    if (el.dataset.initialised) {
        return; // prevent double-initialisation if this script is included multiple times
    }

    el.dataset.initialised = "true";

    window.Vue.createApp({
        components: {
            RubricGrid,
        },
    }).mount(el);
});
