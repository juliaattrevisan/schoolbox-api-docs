import IBRegistration from "./ib-registration.vue";

const mountEl = document.getElementById("ib-registration-app");

if (mountEl) {
    window.Vue.createApp(IBRegistration, {
        sessionsEndpoint: mountEl.dataset.sessionsEndpoint ?? "",
        createEndpoint: mountEl.dataset.createEndpoint ?? "",
    }).mount(mountEl);
}
