<script setup>
/**
 * To be able to remove selected members from the group
 */

import { useStore } from "../store/manageGroupStore";
import { useRemoveMembersStore } from "../store/mgRemoveMembersStore";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { useMembersStore } from "../store/mgMembersStore";

const store = useStore();
const removeMembersStore = useRemoveMembersStore();
const membersStore = useMembersStore(); //to keep track of selectedMembers
const removeModalEl = window.Vue.ref(null);

/**
 * to open the modal when requested
 */
window.Vue.watch(
    () => removeMembersStore.isPromptOpen,
    (newIsOpen) => {
        newIsOpen ? removeModalEl.value.open() : removeModalEl.value.close();
    },
);

/**
 * remove the selected members
 */
function onRemoveSelected() {
    removeMembersStore.removeSelectedMembers();
}

/**
 * so we can track the progress of the member removal
 * and update the UI accordingly
 */
removeMembersStore.$onAction(({ name, after, onError }) => {
    if (name === "removeSelectedMembers") {
        store.toastEl.activate("Removing...");
        after(() => {
            store.toastEl.timeout("Members successfully removed", true, 5000);
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.timeout("Unable to remove members", false, 5000);
        });
    }
});

/**
 * To instantly notify the user that the members are being removed
 * by updating the button text
 *
 * @returns { String }
 */
const removeButtonText = window.Vue.computed(() => (removeMembersStore.isProcessing ? "Removing..." : "Yes"));

/**
 * to prevent the user from moving away from the modal while
 * processing is taking place
 *
 * @returns { Boolean }
 */
const isCloseModalDisabled = window.Vue.computed(() => removeMembersStore.isProcessing);

/**
 * to close the modal
 */
function onCancelSelected() {
    removeMembersStore.cancelRemoveSelectedMembers();
}

/**
 * ensure the isPromptOpen is kept up to date
 * as we can currently also use foundation to close the modal
 */
function onModalClosed() {
    removeMembersStore.cancelRemoveSelectedMembers();
}
</script>

<template>
    <reveal-modal
        ref="removeModalEl"
        :is-close-disabled="isCloseModalDisabled"
        :close-on-background-click="false"
        @closed="onModalClosed"
    >
        <h2>Remove {{ membersStore.selectedMembersPlural }}</h2>
        <p>
            Are you sure you wish to remove the
            <b>{{ membersStore.selectedMembersCount }} selected {{ membersStore.selectedMembersPlural }}</b> from the
            {{ store.name }} group?
        </p>
        <div v-if="membersStore.selectedGroupAdminCount > 0" class="alert-box alert">
            Warning: You are removing {{ membersStore.selectedGroupAdminCount }} Group
            {{ membersStore.selectedGroupAdminCount > 1 ? "Admins" : "Admin" }}. They will no longer be able to manage
            the group.
        </div>
        <ul class="flex-list buttons">
            <li>
                <button
                    @click.prevent="onRemoveSelected"
                    class="button submit"
                    :disabled="removeMembersStore.isProcessing"
                >
                    {{ removeButtonText }}
                </button>
            </li>
            <li>
                <button @click.prevent="onCancelSelected" class="button" :disabled="removeMembersStore.isProcessing">
                    Cancel
                </button>
            </li>
        </ul>
    </reveal-modal>
</template>

<style scoped></style>
