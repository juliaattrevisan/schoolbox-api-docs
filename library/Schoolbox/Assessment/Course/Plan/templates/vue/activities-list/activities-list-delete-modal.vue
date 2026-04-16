<script setup lang="ts">
/**
 * The modal that appears when the user attempts to delete an activity
 * To be used alongside the activities list, all activities lists on the page will share this modal
 * One instance of <activities-list-delete-modal /> should be available on the page if an activities list is present
 */
import locale from "@js/terminology/rosettaStone";
import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal.vue";
import { useActivitiesListDeleteModalStore } from "./store/activitiesListDeleteModalStore";

const activitiesListDeleteModalStore = useActivitiesListDeleteModalStore();

/**
 * To instantly notify the user that the node is being deleted
 * by updating the button text
 */
const deleteButtonText = window.Vue.computed(() =>
    activitiesListDeleteModalStore.isProcessing ? "Deleting..." : "Yes, permanently delete",
);

/**
 * To prevent the user from moving away from the modal while processing is taking place
 */
const isModalDisabled = window.Vue.computed(() => activitiesListDeleteModalStore.isProcessing);
</script>

<template>
    <sequential-reveal-modal
        :is-open="activitiesListDeleteModalStore.isOpen"
        :is-close-disabled="isModalDisabled"
        @closed="activitiesListDeleteModalStore.close()"
    >
        <template #sequence-1>
            <h2>Please Confirm</h2>
            <p>
                Are you sure you wish to delete this
                {{ locale.activity.singular() }}? You will be unable to import this
                {{ locale.activity.singular() }}
                into classes.
            </p>
            <ul class="flex-list">
                <li>
                    <button
                        class="button submit sbx-button--has-icon"
                        data-test-confirm-delete-activity
                        :disabled="activitiesListDeleteModalStore.isProcessing"
                        @click.prevent="activitiesListDeleteModalStore.confirm()"
                    >
                        <sbx-icon name="approve"></sbx-icon>
                        {{ deleteButtonText }}
                    </button>
                </li>
                <li>
                    <button
                        class="button icon-close"
                        data-test-cancel-delete-activity
                        :disabled="activitiesListDeleteModalStore.isProcessing"
                        @click.prevent="activitiesListDeleteModalStore.close()"
                    >
                        No, keep it
                    </button>
                </li>
            </ul>
        </template>
    </sequential-reveal-modal>
</template>

<style scoped></style>
