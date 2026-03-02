<script lang="ts" setup>
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useMomentFormStore, MomentFormModalType } from "./store/momentFormStore";

const mfStore = useMomentFormStore();
</script>

<template>
    <basic-modal v-if="mfStore.moment !== null" v-model:is-open="mfStore.isModalOpen" :has-background-click="true">
        <template v-if="mfStore.modalType === MomentFormModalType.CONFIRM_PUBLISH">
            <h2>Please confirm</h2>
            <p>
                There are comments on this learning moment that have already been made and will become visible to
                parents.
            </p>
            <p>Do you want to delete these comments?</p>

            <ul class="flex-list buttons">
                <li>
                    <button type="submit" class="submit" @click="() => mfStore.publish(true)">
                        Delete and continue
                    </button>
                </li>
                <li>
                    <button type="submit" @click="() => mfStore.publish(false)">Continue</button>
                </li>
            </ul>
        </template>

        <template v-else-if="mfStore.modalType === MomentFormModalType.CONFIRM_REJECT">
            <h2>Request update</h2>
            <p>You have requested the student updates their learning moment before it is published.</p>
            <p>Provide feedback to the student. This will appear as a comment that can be deleted later.</p>

            <textarea
                v-model="mfStore.rejectionReason"
                name="rejectionReason"
                data-test="learning-moment-request-update-reason"
            ></textarea>
            <button
                type="submit"
                class="icon-surveys"
                data-test="learning-moment-request-update-confirm"
                @click="() => mfStore.reject(true)"
            >
                Request update
            </button>
        </template>

        <template v-else-if="mfStore.modalType === MomentFormModalType.CONFIRM_DELETE">
            <h2>Please confirm</h2>
            <p>Are you sure you wish to delete this learning moment?</p>
            <ul class="flex-list buttons">
                <li>
                    <a
                        id="confirm-delete-learning-moment"
                        class="button submit"
                        data-test="learning-moment-delete-confirm"
                        @click="() => mfStore.deleteMoment(true)"
                    >
                        Delete
                    </a>
                </li>
                <li><a class="button" @click="mfStore.isModalOpen = false">Cancel</a></li>
            </ul>
        </template>
    </basic-modal>
</template>

<style scoped></style>
