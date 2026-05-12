<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import CreateSessionModal from "./components/create-session-modal.vue";
import type { RegistrationSession } from "../js/types/ibRegistrationTypes";

interface Props {
    sessionsEndpoint: string;
    createEndpoint: string;
    reportBaseUrl: string;
}
const props = defineProps<Props>();

// ── DIPLOMA section state (systemPurposeId: 1) ───────────────────────────
// Future sections: add separate refs below this block, each with its own systemPurposeId.

const DIPLOMA_SYSTEM_PURPOSE_ID = 1;

const diplomaSessions = window.Vue.ref<RegistrationSession[]>([]);
const isDiplomaLoading = window.Vue.ref<boolean>(true);
const isDiplomaModalOpen = window.Vue.ref<boolean>(false);
const localDiplomaSessionIds = window.Vue.ref<number[]>([]);

/** Fetches sessions from the API and populates the DIPLOMA section. On failure, leaves the list empty (shows empty state). */
async function loadDiplomaSessions(): Promise<void> {
    isDiplomaLoading.value = true;

    const response = await apiRequest<Record<string, RegistrationSession[]>>({
        url: props.sessionsEndpoint,
        method: "GET",
    });

    isDiplomaLoading.value = false;

    if (!response.success || typeof response.data !== "object" || Array.isArray(response.data)) {
        return;
    }

    diplomaSessions.value = response.data[String(DIPLOMA_SYSTEM_PURPOSE_ID)] ?? [];
}

/**
 * Appends a newly created session to the DIPLOMA list and tracks it as local-only for the delete button.
 * @param session - The newly created registration session returned by the API
 */
function onDiplomaSessionCreated(session: RegistrationSession): void {
    diplomaSessions.value = [...diplomaSessions.value, session];
    localDiplomaSessionIds.value = [...localDiplomaSessionIds.value, session.id];
}

/**
 * Removes a locally-created session from the DIPLOMA list (client-side only).
 * @param sessionId - The ID of the session to remove
 */
function removeDiplomaSession(sessionId: number): void {
    diplomaSessions.value = diplomaSessions.value.filter((s) => s.id !== sessionId);
    localDiplomaSessionIds.value = localDiplomaSessionIds.value.filter((id) => id !== sessionId);
}

const reportBaseUrl = window.Vue.computed<string | null>(() =>
    props.reportBaseUrl ? props.reportBaseUrl.replace(/\/$/, "") : null,
);

window.Vue.onMounted((): void => {
    void loadDiplomaSessions();
});
</script>

<template>
    <div class="small-12 island l-flex-col l-gap-m">
        <!-- ── DIPLOMA SECTION ───────────────────────────────────────────────
             Hardcoded: Student Registration Information for Diploma Program Year 2.
             Future sections are added below as additional hardcoded blocks,
             each with its own reactive state (sessions, loading, error, modal).
             Empty state: entire section collapses into a single card (no external heading).
             Populated state: heading + add button appear above the session list.
        ──────────────────────────────────────────────────────────────────── -->
        <div v-if="isDiplomaLoading" class="loader" data-test="ib-registration-diploma-loading"></div>

        <template v-else>
            <empty-state v-if="diplomaSessions.length === 0" icon="icon-add">
                <p>Student Registration Information for Diploma Program Year 2 [DIPLOMA]</p>
                <p>
                    No registration sessions exist.
                    <button
                        type="button"
                        class="button"
                        data-test="ib-registration-diploma-add-link"
                        @click.prevent="isDiplomaModalOpen = true"
                    >
                        Create a new session
                    </button>
                </p>
            </empty-state>

            <!-- Populated state -->
            <template v-else>
                <div class="l-flex l-flex--align-center l-flex--justify-space f-no-margins">
                    <h2 class="subheader f-no-margins">
                        Student Registration Information for Diploma Program Year 2 [DIPLOMA]
                    </h2>
                    <button
                        type="button"
                        class="sbx-icon-only-button"
                        style="font-size: 1.5rem; line-height: 1"
                        data-test="ib-registration-diploma-add"
                        aria-label="Create new Diploma Program Year 2 examination session"
                        @click.prevent="isDiplomaModalOpen = true"
                    >
                        +
                    </button>
                </div>

                <div class="session-list-box">
                    <ul class="session-list" data-test="ib-registration-diploma-list">
                        <li
                            v-for="session in diplomaSessions"
                            :key="session.id"
                            class="session-item"
                            :data-test="`ib-registration-diploma-session-${session.id}`"
                        >
                            <div class="l-flex l-flex--align-center l-flex--justify-space l-gap-xs">
                                <div class="l-flex-col">
                                    <a v-if="reportBaseUrl" :href="`${reportBaseUrl}/${session.id}`">{{
                                        session.title
                                    }}</a>
                                    <span v-else>{{ session.title }}</span>
                                    <small
                                        >Session created by {{ session.createdBy }} on
                                        {{ new Date(session.createdAt).toLocaleDateString() }},
                                        {{ new Date(session.createdAt).toLocaleTimeString() }}</small
                                    >
                                </div>
                                <button
                                    v-if="localDiplomaSessionIds.includes(session.id)"
                                    type="button"
                                    class="sbx-icon-only-button"
                                    :aria-label="`Remove session ${session.title}`"
                                    @click="removeDiplomaSession(session.id)"
                                >
                                    <sbx-icon name="delete"></sbx-icon>
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </template>
        </template>

        <CreateSessionModal
            v-model:is-open="isDiplomaModalOpen"
            :create-endpoint="createEndpoint"
            :system-purpose-id="DIPLOMA_SYSTEM_PURPOSE_ID"
            @session-created="onDiplomaSessionCreated"
        />
    </div>
</template>

<style scoped>
.session-list-box {
    background-color: white;
    border-radius: var(--sbx-config-box-roundness, 0);
    padding: 0 1rem;
}

.session-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.session-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--sbx-color-border, #e8e8e8);
}

.session-item:last-child {
    border-bottom: none;
}
</style>
