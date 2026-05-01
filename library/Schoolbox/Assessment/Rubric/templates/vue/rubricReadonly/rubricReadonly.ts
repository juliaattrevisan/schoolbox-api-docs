import RubricReadonly from "./rubric-readonly.vue";

document.querySelectorAll<HTMLDivElement>('[data-vue-app="rubric-readonly"]').forEach((el) => {
    if (el.dataset.initialised) {
        return; // prevent double-initialisation if this script is included multiple times
    }

    el.dataset.initialised = "true";

    window.Vue.createApp({
        components: {
            RubricReadonly,
        },
    }).mount(el);
});
