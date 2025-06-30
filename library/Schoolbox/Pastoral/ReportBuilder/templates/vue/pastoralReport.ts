import PastoralReport from "./pastoral-report.vue";

const el = document.getElementById("pastoral-report-container");

const tabs = JSON.parse(el?.dataset.tabs || "[]");
const results = JSON.parse(el?.dataset.results || "[]");
const showSeverity = el?.dataset.showSeverity === "true";
const severityColors = JSON.parse(el?.dataset.severityColors || "{}");

window.Vue.createApp(PastoralReport, {
    tabs,
    results,
    showSeverity,
    severityColors,
}).mount("#pastoral-report-container");
s;
