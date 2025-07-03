<script setup lang="ts">
import { keysOf } from "@js/util/arrayUtils";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { ACTIVITY_TYPE_QUIZ } from "@schoolboxAssessment/Types/assessmentTypes";
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
            if (!aImpl || aImpl.writable === false) {
                // TODO: does course markbook need the writable property? this ignores undefined atm
                return null;
            }

            const students = mbStore.orderedClassImplStudents(classImpl);
            const participants = students
                .map((student) => {
                    const participant = aImpl.participants[student.id];
                    if (!participant) {
                        return null;
                    }

                    return {
                        student,
                        participant,
                    };
                })
                .filter((p) => p !== null)
                .filter(({ participant }) => mbStore.hasMeaningfulMarks(participant.marks.draft));

            return {
                classImplId,
                classImpl,
                participants,
            };
        })
        .filter((c) => c !== null)
        .filter(({ participants }) => participants.length > 0);
});

const isPublishModalOpen = window.Vue.ref(false);
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
    const selected = keysOf(selectedParticipants.value).filter((id) => selectedParticipants.value[id]);

    // TODO
    console.log("would have published drafts for", selected);

    isPublishModalOpen.value = false;
}
</script>

<template>
    <li class="separator">Activity</li>
    <li v-if="props.activity._links?.summary">
        <a :href="getReturnUrl(props.activity._links.summary)" class="icon-view"> View </a>
    </li>
    <li v-if="props.activity._links?.modify">
        <a :href="getReturnUrl(props.activity._links.modify)" class="icon-modify">
            {{ props.activity.assessmentType === ACTIVITY_TYPE_QUIZ ? "Modify Settings" : "Modify" }}
        </a>
    </li>
    <li v-if="props.activity._links?.modifyContent">
        <a :href="getReturnUrl(props.activity._links.modifyContent)" class="icon-modify"> Modify Content </a>
    </li>
    <li v-if="props.activity._links?.status">
        <a :href="getReturnUrl(props.activity._links.status)" class="icon-progress"> View Status </a>
    </li>
    <!-- TODO <li>
        <a href="" class="icon-upload"> Import Feedback </a>
    </li> -->
    <template v-if="props.activity._links?.status">
        <li class="separator">Bulk Actions</li>
        <li>
            <!-- TODO: Disable if not writable (assuming that's the equivalent of "readonly")? -->
            <button class="icon-approve" @click.prevent="openPublishModal">Publish Grades & Feedback</button>
        </li>
    </template>

    <basic-modal v-if="isPublishModalOpen" v-model:is-open="isPublishModalOpen" has-background-click>
        <h2>Publish Drafts</h2>

        <p v-if="participantsWithDrafts.length === 0">
            There aren't any valid and unpublished drafts for this activity.
        </p>
        <p v-else>The following drafts can be published:</p>

        <template v-for="{ classImpl, participants, classImplId } in participantsWithDrafts" :key="classImplId">
            <h3 v-if="participantsWithDrafts.length >= 2">{{ classImpl.folder.title }}</h3>
            <section class="checklist">
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
            </section>
        </template>

        <template #footer>
            <div class="component-action">
                <section>
                    <button class="button" @click="isPublishModalOpen = false">Cancel</button>
                    <button
                        v-if="participantsWithDrafts.length > 0"
                        class="button"
                        type="submit"
                        @click.prevent="onPublishAll"
                    >
                        Publish All
                    </button>
                </section>
            </div>
        </template>
    </basic-modal>
</template>

<style scoped></style>
