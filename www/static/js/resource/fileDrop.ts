import FileDrop from "./file-drop.vue";

document.querySelectorAll('[data-vue-app="file-drop"]').forEach((el) => {
    window.Vue.createApp({
        components: {
            FileDrop,
        },
    }).mount(el);
});
