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
 * Handle the publishing of the selected entries
 * @param deleteComments Whether to delete comments associated with the learning moment
 */
async function onPublish(deleteComments: boolean): Promise<void> {
    if (!crmStore.selectedEntries || crmStore.selectedEntries.length === 0) {
        throw new Error("No entries selected for publishing");
    }

    crmStore.isModalOpen = false;
    await crStore.publishEntries(crmStore.selectedEntries, deleteComments);
    if (crmStore.onConfirm) {
        crmStore.onConfirm();
    }
}

/**
 * Handle the rejection of the selected entries
 */
async function onReject(): Promise<void> {
    if (!crmStore.selectedEntries || crmStore.selectedEntries.length === 0) {
        throw new Error("No entries selected for rejection");
    }

    crmStore.isModalOpen = false;
    await crStore.rejectEntries(crmStore.selectedEntries, rejectReason.value);
    rejectReason.value = "";
    if (crmStore.onConfirm) {
        crmStore.onConfirm();
    }
}

/**
 * Handle the deletion of the selected entries
 */
async function onDelete(): Promise<void> {
    if (!crmStore.selectedEntries || crmStore.selectedEntries.length === 0) {
        throw new Error("No entries selected for deletion");
    }

    crmStore.isModalOpen = false;
    await crStore.deleteEntries(crmStore.selectedEntries);
    if (crmStore.onConfirm) {
        crmStore.onConfirm();
    }
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
                <div v-if="entry.creator !== null" class="card small-12 compact">
                    <div class="l-flex-row l-flex--align-center">
                        <user-photo :user="entry.creator" class="l-mar-s--right" size="xsmall" />
                        <p>{{ entry.creator.fullName }}</p>
                    </div>
                </div>
                <div class="clearfix"></div>
                <!-- eslint-disable-next-line vue/no-v-html - this is scrubbed on the backend -->
                <div class="l-mar-s--top" v-html="entry.body"></div>
                <div class="clearfix"></div>
            </template>
        </template>

        <template v-else-if="crmStore.modalType === CohortReportModalType.CONFIRM_PUBLISH">
            <h2>Please confirm</h2>
            <p>
                There are comments on
                {{ crmStore.selectedEntries.length === 1 ? "this learning moment" : "these learning moments" }} that
                have already been made and will become visible to parents.
            </p>
            <p>Do you want to delete these comments?</p>

            <ul class="flex-list buttons">
                <li>
                    <button type="submit" class="submit" @click="() => onPublish(true)">Delete and continue</button>
                </li>
                <li>
                    <button type="submit" @click="() => onPublish(false)">Continue</button>
                </li>
            </ul>
        </template>

        <template v-else-if="crmStore.modalType === CohortReportModalType.CONFIRM_REJECT">
            <h2>Request update</h2>
            <p>
                You have requested the
                {{
                    crmStore.selectedEntries.length === 1
                        ? "student updates their learning moment before it is published"
                        : "students update their learning moments before they are published"
                }}.
            </p>
            <p>
                Provide feedback to the
                {{ crmStore.selectedEntries.length === 1 ? "student" : "students" }}. This will appear as a comment that
                can be deleted later.
            </p>

            <textarea v-model="rejectReason" name="rejectionReason"></textarea>
            <button type="submit" class="icon-surveys" @click="onReject">Request update</button>
        </template>

        <template v-else-if="crmStore.modalType === CohortReportModalType.CONFIRM_DELETE">
            <h2>Please confirm</h2>
            <p>
                Are you sure you wish to delete
                {{ crmStore.selectedEntries.length === 1 ? "this learning moment" : "these learning moments" }}?
            </p>
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
