<script setup lang="ts">
/**
 * Root page component for the Diploma Programme Year 2 registration report.
 * Loads candidate data from the API and renders each registration section.
 */
// TODO SA-22226: restore import when real API call is re-enabled
// import { apiRequest } from "@js/util/sbx/apiUtils";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import IbReportSection from "./components/ib-report-section.vue";
import IbReportStandardTable from "./components/ib-report-standard-table.vue";
import IbReportFailedTable from "./components/ib-report-failed-table.vue";
import IbReportInProgressTable from "./components/ib-report-in-progress-table.vue";
import IbReportRegisteredTable from "./components/ib-report-registered-table.vue";
import type { ReportResponse, StudentRow, FailedRow, InProgressRow, RegisteredRow } from "../js/types/ibReportTypes";

interface Props {
    candidatesEndpoint: string;
}
defineProps<Props>(); // TODO SA-22226: assign to `const props` when real API call is re-enabled

const isLoading = window.Vue.ref<boolean>(true);
const loadError = window.Vue.ref<string | null>(null);
const sessionTitle = window.Vue.ref<string>("");
const preparation = window.Vue.ref<StudentRow[]>([]);
const unconfirmed = window.Vue.ref<StudentRow[]>([]);
const confirmed = window.Vue.ref<StudentRow[]>([]);
const inProgress = window.Vue.ref<InProgressRow[]>([]);
const failed = window.Vue.ref<FailedRow[]>([]);
const registered = window.Vue.ref<RegisteredRow[]>([]);

// TODO SA-22226: remove mock data and restore real API call below
const MOCK_DATA: ReportResponse = {
    sessionTitle: "Diploma Programme Year 2 [DIPLOMA] - May 2026",
    preparation: [
        {
            id: 1,
            name: "Alexandra Hamilton",
            candidateDetailsStatus: "unconfirmed",
            subjects: {
                hl: [{ subject: "Language A: language & literature", language: "English" }],
                sl: [{ subject: "Mathematics: applications and interpretation", language: "English" }],
                core: [{ subject: "CAS", language: null }],
            },
        },
        {
            id: 2,
            name: "Benjamin Torres",
            candidateDetailsStatus: "confirmed",
            subjects: {
                hl: [
                    { subject: "Biology", language: "English" },
                    { subject: "Chemistry", language: "English" },
                ],
                sl: [{ subject: "History", language: "English" }],
                core: [
                    { subject: "Extended Essay", language: "English" },
                    { subject: "Theory of Knowledge", language: "English" },
                ],
            },
        },
    ],
    unconfirmed: [
        {
            id: 3,
            name: "Chloe Nguyen",
            candidateDetailsStatus: "unconfirmed",
            subjects: {
                hl: [{ subject: "Visual Arts", language: "French" }],
                sl: [{ subject: "Physics", language: "UNKNOWN LANGUAGE" }],
                core: [{ subject: "CAS", language: "French" }],
            },
        },
    ],
    confirmed: [
        {
            id: 4,
            name: "David Kim",
            candidateDetailsStatus: "confirmed",
            subjects: {
                hl: [
                    { subject: "Economics", language: "English" },
                    { subject: "Geography", language: "English" },
                ],
                sl: [{ subject: "Language B: Spanish", language: "Spanish" }],
                core: [{ subject: "Extended Essay", language: "English" }],
            },
        },
    ],
    inProgress: [
        {
            id: 5,
            name: "Elena Vasquez",
            ibisSubmittedAt: "2026-01-15T10:01:00+10:00",
            subjects: {
                hl: [{ subject: "Music", language: "English" }],
                sl: [{ subject: "Mathematics: analysis and approaches", language: "English" }],
                core: [{ subject: "Theory of Knowledge", language: "English" }],
            },
        },
    ],
    failed: [
        {
            id: 6,
            name: "Finn O'Brien",
            candidateDetailsStatus: "confirmed",
            subjects: {
                hl: [{ subject: "Language A: language & literature", language: "English" }],
                sl: [{ subject: "Biology", language: "English" }],
                core: [{ subject: "CAS", language: "English" }],
            },
            errorMessage: "Candidate personal code mismatch: IBIS returned code 0042 but candidate record shows 0041.",
        },
    ],
    registered: [
        {
            id: 7,
            name: "Grace Okonkwo",
            candidateDetailsStatus: "confirmed",
            subjects: {
                hl: [
                    { subject: "Chemistry", language: "English" },
                    { subject: "Physics", language: "English" },
                ],
                sl: [{ subject: "Mathematics: analysis and approaches", language: "English" }],
                core: [
                    { subject: "Extended Essay", language: "English" },
                    { subject: "CAS", language: "English" },
                ],
            },
            ibCandidateNumber: "001234-123",
        },
        {
            id: 8,
            name: "Hugo Leclercq",
            candidateDetailsStatus: "unconfirmed",
            subjects: {
                hl: [{ subject: "History", language: "French" }],
                sl: [{ subject: "Economics", language: null }],
                core: [{ subject: "Theory of Knowledge", language: "French" }],
            },
            ibCandidateNumber: "001234-124",
        },
    ],
};

/** Fetches all candidate data from the API and populates section arrays. */
async function loadCandidates(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    // TODO SA-22226: restore real API call:
    // const response = await apiRequest<ReportResponse>({
    //     url: props.candidatesEndpoint,
    //     method: "GET",
    // });
    // isLoading.value = false;
    // if (!response.success) {
    //     loadError.value = response.message ?? "Failed to load registration report.";
    //     return;
    // }
    // const data = response.data;

    await new Promise<void>((resolve) => setTimeout(resolve, 300));
    isLoading.value = false;
    const data = MOCK_DATA;

    sessionTitle.value = data.sessionTitle;
    preparation.value = data.preparation;
    unconfirmed.value = data.unconfirmed;
    confirmed.value = data.confirmed;
    inProgress.value = data.inProgress;
    failed.value = data.failed;
    registered.value = data.registered;
}

window.Vue.onMounted((): void => {
    void loadCandidates();
});
</script>

<template>
    <div class="small-12">
        <div class="l-flex l-flex--align-center l-flex--justify-space f-no-margins">
            <h1 class="f-no-margins">{{ sessionTitle }}</h1>
            <button type="button" class="ib-report__download-btn" disabled aria-label="Download">
                <sbx-icon name="download"></sbx-icon>
            </button>
        </div>

        <div v-if="isLoading" class="loader"></div>

        <data-load-error v-else-if="loadError" data-title="registration report" :further-info="loadError" />

        <template v-else>
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
        </template>
    </div>
</template>

<style scoped>
.ib-report__download-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.25rem;
    color: var(--sbx-config-color-accent, #0066b0);
    opacity: 0.85;
}
</style>
