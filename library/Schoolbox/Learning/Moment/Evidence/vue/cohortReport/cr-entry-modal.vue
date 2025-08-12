<script lang="ts" setup>
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import { useCohortReportStore } from "./store/cohortReportStore";
import { CohortReportModalType, useCohortReportModalStore } from "./store/cohortReportModalStore";

const crStore = useCohortReportStore();
const crmStore = useCohortReportModalStore();

const rejectReason = window.Vue.ref<string>("");

window.Vue.watch(
    () => [...(crmStore.selectedEntries || []), crmStore.isModalOpen],
    () => {
        rejectReason.value = "";
    },
);

/**
 * Handle the rejection of the selected entries
 */
async function onReject(): Promise<void> {
    if (!crmStore.selectedEntries || crmStore.selectedEntries.length === 0) {
        throw new Error("No entries selected for rejection");
    }

    crmStore.isModalOpen = false;
    // TODO: Support bulk rejection
    await crStore.rejectEntry(crmStore.selectedEntries[0].id, rejectReason.value);
    rejectReason.value = "";
}

/**
 * Handle the deletion of the selected entries
 */
function onDelete(): void {
    crmStore.isModalOpen = false;
    alert("todo");
}
</script>

<template>
    <basic-modal
        v-if="crmStore.selectedEntries !== null"
        v-model:is-open="crmStore.isModalOpen"
        :has-background-click="true"
    >
        <template v-if="crmStore.modalType === CohortReportModalType.READ_MORE">
            <template v-for="entry in crmStore.selectedEntries" :key="entry.id">
                <div class="card small-12 compact">
                    <div class="l-flex-row l-flex--align-center">
                        <user-photo :user="entry.creator" class="l-mar-s--right" size="xsmall" />
                        <p>{{ entry.creator.fullname }}</p>
                    </div>
                </div>
                <div class="clearfix"></div>
                <!-- eslint-disable-next-line vue/no-v-html - this is scrubbed on the backend -->
                <div class="l-mar-s--top" v-html="entry.body"></div>
            </template>
        </template>

        <template v-else-if="crmStore.modalType === CohortReportModalType.CONFIRM_REJECT">
            <h2>Request update</h2>
            <p>You have requested the student updates their learning moment before it is published.</p>
            <p>Provide feedback to the student. This will appear as a comment that can be deleted later.</p>

            <textarea v-model="rejectReason" name="rejectionReason"></textarea>
            <button type="submit" class="icon-surveys" @click="onReject">Request update</button>
        </template>

        <template v-else-if="crmStore.modalType === CohortReportModalType.CONFIRM_DELETE">
            <h2>Please confirm</h2>
            <p>Are you sure you wish to delete this Learning Moment?</p>
            <ul class="flex-list buttons">
                <li>
                    <a
                        id="confirm-delete-learning-moment"
                        class="button submit"
                        data-test="learning-moment-evidence-delete-confirm"
                        @click="onDelete"
                    >
                        Delete
                    </a>
                </li>
                <li><a class="button" @click="crmStore.isModalOpen = false">Cancel</a></li>
            </ul>
        </template>
    </basic-modal>
</template>

<style scoped></style>
