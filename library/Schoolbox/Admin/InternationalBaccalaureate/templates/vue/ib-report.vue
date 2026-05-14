<script setup lang="ts">
/**
 * Root page component for the Diploma Programme Year 2 registration report.
 * Loads candidate data from the API and renders each registration section.
 */
import { apiRequest } from "@js/util/sbx/apiUtils";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import IbReportSection from "./components/ib-report-section.vue";
import IbReportStandardTable from "./components/ib-report-standard-table.vue";
import IbReportFailedTable from "./components/ib-report-failed-table.vue";
import IbReportInProgressTable from "./components/ib-report-in-progress-table.vue";
import IbReportRegisteredTable from "./components/ib-report-registered-table.vue";
import IbUpdateLanguageModal from "./components/ib-update-language-modal.vue";
import { useIbReportUpdateLanguageStore } from "./store/ibReportUpdateLanguageStore";
import type {
    StudentRow,
    FailedRow,
    InProgressRow,
    RegisteredRow,
    SubjectEntry,
    ApiStudent,
    ApiSubject,
    ApiCandidatesResponse,
} from "../js/types/ibReportTypes";

interface Props {
    candidatesEndpoint: string;
    candidateExportEndpoint: string;
    searchLanguagesEndpoint: string;
    sessionTitle: string;
}
const props = defineProps<Props>();
const updateLanguageStore = useIbReportUpdateLanguageStore();

const isLoading = window.Vue.ref<boolean>(true);
const loadError = window.Vue.ref<string | null>(null);
const preparation = window.Vue.ref<StudentRow[]>([]);
const unconfirmed = window.Vue.ref<StudentRow[]>([]);
const confirmed = window.Vue.ref<StudentRow[]>([]);
const inProgress = window.Vue.ref<InProgressRow[]>([]);
const failed = window.Vue.ref<FailedRow[]>([]);
const registered = window.Vue.ref<RegisteredRow[]>([]);

/**
 * Maps a raw API subject entry to the frontend SubjectEntry shape.
 * Takes the first language when multiple are configured.
 * @param apiSubject The raw subject from the API.
 * @returns A SubjectEntry for use in the frontend.
 */
function mapSubjectEntry(apiSubject: ApiSubject): SubjectEntry {
    const lang = apiSubject.languages[0] ?? null;
    return {
        groupSubjectId: apiSubject.id,
        subject: apiSubject.subject,
        language: lang?.languageName ?? null,
        languageId: lang?.languageId ?? null,
    };
}

/**
 * Maps a raw API student entry to the frontend StudentRow shape.
 * @param s The raw student from the API.
 * @returns A StudentRow for use in the frontend.
 */
function mapStudentRow(s: ApiStudent): StudentRow {
    return {
        id: s.registrationSessionStudentId,
        name: s.userName,
        candidateDetailsStatus: s.hasMissingDetails ? "unconfirmed" : "confirmed",
        subjects: {
            hl: s.ibGroupSubjects.HL.map(mapSubjectEntry),
            sl: s.ibGroupSubjects.SL.map(mapSubjectEntry),
            core: s.ibGroupSubjects.Core.map(mapSubjectEntry),
        },
        updateLanguageEndpoint: s._routes.updateLanguages,
    };
}

/**
 * Fetches all candidate data from the API and populates section arrays.
 */
async function loadCandidates(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
        const response = await apiRequest<ApiCandidatesResponse>({
            url: props.candidatesEndpoint,
            method: "GET",
        });

        if (!response.success) {
            loadError.value = response.message ?? "Failed to load registration report.";
            return;
        }

        const { students, statuses } = response.data;
        const byKey: Record<string, ApiStudent[]> = {};
        for (const status of statuses) {
            byKey[status.key] = students[String(status.value)] ?? [];
        }

        preparation.value = (byKey["preparation"] ?? []).map(mapStudentRow);
        unconfirmed.value = (byKey["unconfirmed"] ?? []).map(mapStudentRow);
        confirmed.value = (byKey["confirmed"] ?? []).map(mapStudentRow);
        inProgress.value = (byKey["inProgress"] ?? []).map(mapStudentRow);
        failed.value = (byKey["failed"] ?? []).map(mapStudentRow);
        registered.value = (byKey["registered"] ?? []).map(mapStudentRow);
    } catch (e) {
        console.error("Failed to load candidates", e);
        loadError.value = "Failed to load registration report.";
    } finally {
        isLoading.value = false;
    }
}

updateLanguageStore.init(props.searchLanguagesEndpoint, preparation, unconfirmed, confirmed, failed);

window.Vue.onMounted((): void => {
    void loadCandidates();
});
</script>

<template>
    <div class="small-12 island l-flex-col l-gap-m">
        <div class="l-flex l-flex--align-center l-flex--justify-space f-no-margins">
            <h2 class="subheader f-no-margins">{{ props.sessionTitle }}</h2>
            <a
                :href="props.candidateExportEndpoint"
                class="sbx-icon-only-button"
                aria-label="Download candidate details as CSV"
                title="Download candidate details as CSV"
            >
                <sbx-icon name="download"></sbx-icon>
            </a>
        </div>

        <div v-if="isLoading" class="loader"></div>

        <data-load-error v-else-if="loadError" data-title="registration report" :further-info="loadError" />

        <div v-else class="l-flex-col l-gap-s">
            <ib-report-section title="Preparation" :count="preparation.length">
                <ib-report-standard-table :students="preparation" />
            </ib-report-section>

            <ib-report-section title="Students with unconfirmed details" :count="unconfirmed.length">
                <ib-report-standard-table :students="unconfirmed" />
            </ib-report-section>

            <ib-report-section title="Students with confirmed details ready for submission" :count="confirmed.length">
                <ib-report-standard-table :students="confirmed" />
            </ib-report-section>

            <ib-report-section title="In progress" :count="inProgress.length">
                <ib-report-in-progress-table :students="inProgress" />
            </ib-report-section>

            <ib-report-section title="Failed registrations" :count="failed.length">
                <ib-report-failed-table :students="failed" />
            </ib-report-section>

            <ib-report-section title="Registered" :count="registered.length">
                <ib-report-registered-table :students="registered" />
            </ib-report-section>
        </div>

        <div id="ib-report-pop-out-container"></div>

        <ib-update-language-modal />
    </div>
</template>
