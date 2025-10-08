<script setup lang="ts">
/**
 * On iOS, since we can't use the beforeinstallprompt event, we need to show a modal instead
 */
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { sbProps } from "@js/util/sbx/pageUtils";
import PwaMenuShareIcon from "./pwa-menu-share-icon.vue";
import { usePwaStore } from "./store/pwaStore";

const pwaStore = usePwaStore();

/**
 * Whether this is the first instance of the modal being mounted
 * This modal component may be mounted multiple times, but we only want one instance to exist
 */
const isFirstInstance = window.Vue.ref(false);
window.Vue.onMounted(() => {
    if (!pwaStore.hasMountedPwaIOSModal) {
        isFirstInstance.value = true;
        pwaStore.hasMountedPwaIOSModal = true;
    }
});
</script>

<template>
    <basic-modal v-if="isFirstInstance" v-model:is-open="pwaStore.isPwaIOSModalOpen" :has-background-click="true">
        <h1>Add the {{ sbProps.siteTitle }} web app to your home screen</h1>
        <p>
            To receive push notifications and enjoy a better experience, install the {{ sbProps.siteTitle }} web app on
            your Apple device.
        </p>

        <ol>
            <li>Select the <pwa-menu-share-icon /> <strong> Share </strong> button in the menu bar of your browser.</li>
            <li>From the list of options, select <strong>Add to Home Screen</strong>.</li>
        </ol>

        <p>
            If 'Add to Home Screen' option doesn't exist, you can scroll down the list and select
            <strong>Edit Actions</strong> and then <strong>Add to Home Screen</strong>.
        </p>

        <p>
            Once you've added the {{ sbProps.siteTitle }} web app to your home screen, you must then go to the web app
            on your home screen to enable notifications.
        </p>
        <p>If you're still unable to add it to your home screen, please contact your support team.</p>
    </basic-modal>
</template>
