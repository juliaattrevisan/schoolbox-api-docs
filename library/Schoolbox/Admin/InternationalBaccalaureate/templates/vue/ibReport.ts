import IBReport from "./ib-report.vue";

const mountEl = document.getElementById("ib-report-app");

if (mountEl) {
    window.Vue.createApp(IBReport, {
        candidatesEndpoint: mountEl.dataset.candidatesEndpoint ?? "",
    }).mount(mountEl);
}
