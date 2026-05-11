import RubricMarkable from "./rubric-markable.vue";

document.querySelectorAll<HTMLDivElement>('[data-vue-app="rubric-markable"]').forEach((el) => {
    if (el.dataset.initialised) {
        return; // prevent double-initialisation if this script is included multiple times
    }

    el.dataset.initialised = "true";

    window.Vue.createApp({
        components: {
            RubricMarkable,
        },
    }).mount(el);
});
