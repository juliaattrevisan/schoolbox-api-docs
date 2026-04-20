<script setup lang="ts">
/**
 * IB Registration Reports page.
 *
 * Renders a hardcoded section per qualification scope. Currently only
 * Diploma Program Year 2 [DIPLOMA] is in scope. Future sections (e.g.
 * DP Year 1, VCE) are added as additional hardcoded blocks below the
 * existing one — each with its own reactive state.
 */
import { apiRequest } from "@js/util/sbx/apiUtils";
import CreateSessionModal from "./components/create-session-modal.vue";
import type { RegistrationSession } from "../js/types/ibRegistrationTypes";

interface Props {
    sessionsEndpoint: string;
    createEndpoint: string;
}
const props = defineProps<Props>();

// ── DIPLOMA section state ─────────────────────────────────────────────────
// Future sections: add separate refs below this block (e.g. diplomaYear1Sessions, etc.)

const diplomaSessions = window.Vue.ref<RegistrationSession[]>([]);
const isDiplomaLoading = window.Vue.ref<boolean>(true);
const isDiplomaModalOpen = window.Vue.ref<boolean>(false);
// IDs of sessions added locally via modal (not persisted — cleared on page refresh)
const localDiplomaSessionIds = window.Vue.ref<number[]>([]);

/**
 * Fetches registration sessions for the Diploma Program Year 2 section.
 * On failure, leaves the sessions list empty (shows empty state).
 */
async function loadDiplomaSessions(): Promise<void> {
    isDiplomaLoading.value = true;

    const response = await apiRequest<RegistrationSession[]>({
        url: props.sessionsEndpoint,
        method: "GET",
    });

    isDiplomaLoading.value = false;

    if (!response.success || !Array.isArray(response.data)) {
        return;
    }

    diplomaSessions.value = response.data;
}

/**
 * Appends a newly created session to the Diploma section list without reloading.
 * Tracks the session ID as local-only so a delete icon is shown.
 * @param session - The newly created registration session to append
 */
function onDiplomaSessionCreated(session: RegistrationSession): void {
    diplomaSessions.value = [...diplomaSessions.value, session];
    localDiplomaSessionIds.value = [...localDiplomaSessionIds.value, session.id];
}

/**
 * Removes a locally-created session from the list (client-side only).
 * @param sessionId - The ID of the session to remove
 */
function removeDiplomaSession(sessionId: number): void {
    diplomaSessions.value = diplomaSessions.value.filter((s) => s.id !== sessionId);
    localDiplomaSessionIds.value = localDiplomaSessionIds.value.filter((id) => id !== sessionId);
}

window.Vue.onMounted((): void => {
    void loadDiplomaSessions();
});
</script>

<template>
    <div class="small-12">
        <!-- ── DIPLOMA SECTION ───────────────────────────────────────────────
             Hardcoded: Student Registration Information for Diploma Program Year 2.
             Future sections are added below as additional hardcoded blocks,
             each with its own reactive state (sessions, loading, error, modal).
             Empty state: entire section collapses into a single card (no external heading).
             Populated state: heading + add button appear above the session list.
        ──────────────────────────────────────────────────────────────────── -->
        <section data-test="ib-registration-diploma-section">
            <div v-if="isDiplomaLoading" class="loader" data-test="ib-registration-diploma-loading"></div>

            <template v-else>
                <!-- Empty state -->
                <div
                    v-if="diplomaSessions.length === 0"
                    class="empty-state-flex-centered"
                    data-test="ib-registration-diploma-empty"
                >
                    <p>Student Registration Information for Diploma Program Year 2 [DIPLOMA]</p>
                    <p>
                        No registration sessions found.
                        <a
                            href="#"
                            data-test="ib-registration-diploma-add-link"
                            @click.prevent="isDiplomaModalOpen = true"
                            >Add</a
                        >
                        to create one.
                    </p>
                </div>

                <!-- Populated state -->
                <template v-else>
                    <div class="l-flex l-flex--align-center l-flex--justify-space f-no-margins">
                        <h2>Student Registration Information for Diploma Program Year 2 [DIPLOMA]</h2>
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
                                        <a href="#">{{ session.title }}</a>
                                        <small
                                            >Session created on
                                            {{ new Date(session.createdAt).toLocaleDateString() }} by
                                            {{ session.createdBy }},
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
        </section>

        <CreateSessionModal
            v-model:is-open="isDiplomaModalOpen"
            :create-endpoint="createEndpoint"
            @session-created="onDiplomaSessionCreated"
        />
    </div>
</template>

<style scoped>
.session-list-box {
    background-color: white;
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
