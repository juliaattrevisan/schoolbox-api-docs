<script setup lang="ts">
import { keysOf } from "@js/util/arrayUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { MarkbookActivity, ParticipantId } from "./types/payload";
import { useMarkbookStore } from "./store/markbookStore";

interface Props {
    activity: MarkbookActivity;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const participantsWithDrafts = window.Vue.computed(() => {
    if (mbStore.data === null) {
        return [];
    }

    return mbStore.data.classImpls
        .map((classImpl, classImplId) => {
            const aImpl = classImpl.assessments[props.activity.id];
            if (!aImpl || !aImpl.writable) {
                return null;
            }

            const students = mbStore.orderedClassImplStudents(classImpl);
            const participants = students
                .map((student) => {
                    const participant = aImpl.participants[student.id] ?? null;
                    const responseId = participant?.marks.draft?.responseId ?? null;
                    if (participant === null || responseId === null) {
                        return null;
                    }

                    return { student, participant, responseId };
                })
                .filter((p) => p !== null);

            return {
                classImplId,
                classImpl,
                aImpl,
                participants,
            };
        })
        .filter((c) => c !== null)
        .filter(({ participants }) => participants.length > 0);
});

const isPublishModalOpen = window.Vue.ref(false);
const isPublishing = window.Vue.ref(false);
const selectedParticipants = window.Vue.ref<Record<ParticipantId, boolean>>({});

/**
 * Open the modal to publish drafts, all participants are selected by default
 */
function openPublishModal(): void {
    isPublishModalOpen.value = true;

    // Reset the selected participants
    selectedParticipants.value = {};
    participantsWithDrafts.value.forEach(({ participants }) => {
        participants.forEach((participant) => {
            selectedParticipants.value[participant.student.id] = true;
        });
    });
}

/**
 * When the user clicks on "Publish All"
 */
async function onPublishAll(): Promise<void> {
    if (isPublishing.value) {
        // already publishing, ignore
        return;
    }

    // Retrieve the activity from the store, as we can't modify props directly
    const activity = mbStore.data?.assessments[props.activity.id] ?? null;
    if (activity === null) {
        throw new Error("Activity not found, cannot publish drafts");
    }

    const selectedParticipantIds = keysOf(selectedParticipants.value).filter((id) => selectedParticipants.value[id]);

    const selectedResponseIds = participantsWithDrafts.value.flatMap(({ participants }) => {
        return participants
            .filter((participant) => selectedParticipantIds.includes(`${participant.student.id}`))
            .map((participant) => participant.responseId);
    });

    if (selectedResponseIds.length === 0) {
        return;
    }

    const toast = window.sbProps.toastActivate("Publishing drafts...");
    isPublishing.value = true;
    try {
        if (activity._routes.postFeedbacks === null) {
            // shouldn't get here, but if you do...
            throw new Error(`Missing access to publish drafts for ${activity.title}`);
        }

        const resp = await fetch(activity._routes.postFeedbacks, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({ drafts: selectedResponseIds }),
        });

        if (!resp.ok) {
            throw new Error(`Failed to publish drafts for ${activity.title}`);
        }

        toast.timeout({
            text: "Published drafts",
            isSuccess: true,
        });
    } catch (e) {
        console.error("Failed to publish drafts:", e);
        toast.timeout({
            text: "Failed to publish drafts",
            isSuccess: false,
        });
    }

    /* update all marks/stats for the selected participants */
    let allSelected = true;
    participantsWithDrafts.value.forEach(({ participants, aImpl }) => {
        let allImplSelected = true;
        participants.forEach((participant) => {
            if (!selectedParticipants.value[participant.student.id]) {
                allSelected = false;
                allImplSelected = false;
                return;
            }

            const dataPublished = participant.participant.marks.published;
            const dataDraft = participant.participant.marks.draft;

            participant.participant.hasPublishedFeedback = true;

            /* persist the published data */
            dataPublished.comment = dataDraft.comment;
            dataPublished.hr = dataDraft.hr;
            dataPublished.hrInstructor = dataDraft.hrInstructor;
            dataPublished.norm = dataDraft.norm;
            dataPublished.responseId = dataDraft.responseId;
            dataPublished.resubmissionRequired = dataDraft.resubmissionRequired;

            /* and reset the draft data to defaults */
            dataDraft.comment = null;
            dataDraft.hr = null;
            dataDraft.hrInstructor = null;
            dataDraft.norm = null;
            dataDraft.responseId = null;
            dataDraft.resubmissionRequired = false;
        });

        // TODO: handle partial updates (not all participants selected)
        if (allImplSelected) {
            aImpl.stats.mean = aImpl.stats.draftMean;
            aImpl.stats.draftMean = null;
        }
    });

    if (allSelected && activity.stats) {
        activity.stats.mean = activity.stats.draftMean;
    }

    if (allSelected && activity.stats) {
        activity.stats.draftMean = null;
    }

    isPublishing.value = false;
    isPublishModalOpen.value = false;
}
</script>

<template>
    <button class="icon-approve" @click.prevent="openPublishModal">
        <sbx-icon name="approve"></sbx-icon>Publish Grades & Feedback
    </button>

    <basic-modal v-if="isPublishModalOpen" v-model:is-open="isPublishModalOpen" has-background-click>
        <h2>Publish Drafts</h2>

        <p v-if="participantsWithDrafts.length === 0">
            There aren't any valid and unpublished drafts for this activity.
        </p>
        <p v-else>The following drafts can be published:</p>

        <!-- <h5 :class="{ 'l-pad-s--top': i > 0 }">{{ classImpl.folder.title }}</h5> -->
        <section class="checklist">
            <template v-for="{ participants, classImplId } in participantsWithDrafts" :key="classImplId">
                <div v-for="participant in participants" :key="participant.student.id">
                    <input
                        :id="`mb-table-head-activity-publish-${props.activity.id}-${participant.student.id}`"
                        v-model="selectedParticipants[participant.student.id]"
                        type="checkbox"
                    />
                    <label
                        class="checkbox-label"
                        :for="`mb-table-head-activity-publish-${props.activity.id}-${participant.student.id}`"
                    >
                        {{ participant.student.givenName }} {{ participant.student.lastName }}
                    </label>
                </div>
            </template>
        </section>

        <template #footer>
            <div class="component-action">
                <section>
                    <button class="button" :disabled="isPublishing" @click="isPublishModalOpen = false">Cancel</button>
                    <button
                        v-if="participantsWithDrafts.length > 0"
                        class="button"
                        :disabled="isPublishing"
                        type="submit"
                        @click.prevent="onPublishAll"
                    >
                        {{ isPublishing ? "Publishing..." : "Publish All" }}
                    </button>
                </section>
            </div>
        </template>
    </basic-modal>
</template>

<style scoped>
.checklist {
    max-height: 50vh;
    overflow-y: auto;
}
</style>
