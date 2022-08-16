<script setup>

/**
 * To be able to remove selected members from the group
 */

import { ref, computed, watch, onMounted } from 'vue';
import { useStore } from './store/manageGroupStore';
import { useSettingsStore } from './store/mgSettingsStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const store = useStore();
const settingsStore = useSettingsStore();
const updateModalEl = ref(null);

/**
 * we want to force the setting of the Access on the group
 * straight away if it's not already set
 */
onMounted(() => {
    if (settingsStore.isAccessNotSet) {
        settingsStore.isPromptOpen = true;
    }
});

/**
 * to open the modal when requested
 */
watch(() => settingsStore.isPromptOpen, (newIsOpen) => {
    newIsOpen ? updateModalEl.value.open() : updateModalEl.value.close();
});

/**
 * update the group settings
 */
function onUpdateSelected () {
    settingsStore.updateSettings();
}

/**
 * so we can track the progress of the member removal
 * and update the UI accordingly
 */
settingsStore.$onAction(({ name, after, onError }) => {
    if (name === 'updateSettings') {
        store.toastEl.activate('Updating...');
        after(() => {
            store.toastEl.timeout('Settings successfully updated', true, 5000);
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.timeout('Unable to update settings', false, 5000);
        });
    }
});

/**
 * To instantly notify the user that the members are being removed
 * by updating the button text
 *
 * @returns { String }
 */
const updateButtonText = computed(() => settingsStore.isProcessing ? 'Updating...' : 'Update');

/**
 * to prevent the user from moving away from the modal while
 * processing is taking place or access needs to be set
 *
 * @returns { Boolean }
 */
const isUpdateModalDisabled = computed(() => settingsStore.isProcessing || settingsStore.isAccessNotSet || !settingsStore.isAccessSaved);

/**
 * to close the modal
 */
function onCancelSelected () {
    settingsStore.cancelUpdateSettings();
}

/**
 * ensure the isPromptOpen is kept up to date
 * as we can currently also use foundation to close the modal
 */
function onModalClosed () {
    settingsStore.cancelUpdateSettings();
}

</script>

<template>
    <reveal-modal
        ref="updateModalEl"
        size="tiny"
        :is-close-disabled="isUpdateModalDisabled"
        :close-on-background-click="false"
        @closed="onModalClosed"
    >
        <h2>Update Group Settings</h2>
        <div v-if="settingsStore.isAccessNotSet" class="alert-box warning">Please select an Access type to continue.</div>
        <div class="row">
            <div class="small-12 medium-6 columns no-pad">
                <legend>Access</legend>
                <ul class="option-list">
                    <li>
                        <input id="type-1" type="radio" v-model="settingsStore.access" value="private" />
                        <label for="type-1" class="radio-label">
                            Private
                            <span data-tooltip class="has-tip tip-top hint" title="Membership is only by invitation from a group admin.">?</span>
                        </label>
                    </li>
                    <li>
                        <input id="type-2" type="radio" v-model="settingsStore.access" value="moderated" />
                        <label for="type-2" class="radio-label">
                            Moderated
                            <span data-tooltip class="has-tip tip-top hint" title="Users may request membership, but must be approved by a group admin.">?</span>
                        </label>
                    </li>
                    <li>
                        <input id="type-3" type="radio" v-model="settingsStore.access" value="free" />
                        <label for="type-3" class="radio-label">
                            Free
                            <span data-tooltip class="has-tip tip-top hint" title="Users may join and leave at any time, without approval.">?</span>
                        </label>
                    </li>
                </ul>
            </div><!-- small-12 medium-6 columns -->
            <div class="small-12 medium-6 columns no-pad">
                <legend>Notifications</legend>
                <ul class="option-list">
                    <li>
                        <input id="msg-enabled" type="checkbox" v-model="settingsStore.isNotificationsEnabled" />
                        <label for="msg-enabled" class="checkbox-label">Notifications enabled</label>
                    </li>
                    <li>
                        <input id="msg-force-follow" type="checkbox" v-model="settingsStore.isForceFollow" :disabled="!settingsStore.isNotificationsEnabled" />
                        <label for="msg-force-follow" class="checkbox-label">
                            Force Follow
                            <span data-tooltip class="has-tip tip-top hint" title="Group members may not opt out of receiving messages if Force Follow is enabled.">?</span>
                        </label>
                    </li>
                </ul>
            </div>
        </div><!-- small-12 medium-6 columns -->
        <ul class="flex-list buttons">
            <li><button @click.prevent="onUpdateSelected" class="button submit" :disabled="settingsStore.isProcessing || settingsStore.isAccessNotSet">{{ updateButtonText }}</button></li>
            <li><button @click.prevent="onCancelSelected" class="button" :disabled="isUpdateModalDisabled">Cancel</button></li>
        </ul>
    </reveal-modal>
</template>

<style scoped>

legend {
    margin-bottom: 0.5rem;
}

</style>
