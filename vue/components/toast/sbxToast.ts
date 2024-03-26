import SbxToast from "./sbx-toast.vue";

window.Vue.createApp({
    components: {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        SbxToast,
    },
}).mount("#toast-container");
