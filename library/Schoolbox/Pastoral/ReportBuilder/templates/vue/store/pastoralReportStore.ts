/**
 * Store the global state for the Pastoral Report Builder.
 */

import { defineStore } from "pinia";
import type { Tab, Config, Routes, SeverityColors } from "../../js/types/pastoralReportTypes";

type InitParams = {
    title: string;
    export: boolean;
    reportId: number;
    tabs: Tab[];
    config: Config;
    routes: Routes;
    localePastoral: string;
    showSeverity: boolean;
    severityColors: SeverityColors;
};

export const useStore = defineStore(`pastoralReportStore`, () => {
    const title = window.Vue.ref<string>("");
    const exportEnabled = window.Vue.ref<boolean>(false);
    const reportId = window.Vue.ref<number>(0);
    const tabs = window.Vue.ref<Tab[]>([]);
    const config = window.Vue.ref<Config>({ pastoralPointEnabled: false, pastoralActionEnable: false });
    const routes = window.Vue.ref<Routes>({
        getStudentRecord: "",
        getReportTags: "",
        getIndex: "",
    });
    const localePastoral = window.Vue.ref<string>("");
    const showSeverity = window.Vue.ref<boolean>(false);
    const severityColors = window.Vue.ref<SeverityColors>({});

    /**
     * Initializes the store with the provided parameters.
     * @param params - The parameters to initialize the store
     */
    function init(params: InitParams): void {
        title.value = params.title;
        exportEnabled.value = params.export;
        reportId.value = params.reportId;
        tabs.value = params.tabs;
        config.value = params.config;
        routes.value = params.routes;
        localePastoral.value = params.localePastoral;
        showSeverity.value = params.showSeverity;
        severityColors.value = params.severityColors;
    }

    return {
        init,
        title,
        exportEnabled,
        reportId,
        tabs,
        config,
        routes,
        localePastoral,
        showSeverity,
        severityColors,
    };
});
