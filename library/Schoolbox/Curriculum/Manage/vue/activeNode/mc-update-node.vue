<script setup>

/**
 * to be able to update a nodes name, code and description
 */

import { computed, watch } from 'vue';
import { useStore } from '../store/manageCurriculumStore';
import { useActiveNodeStore } from './store/mcActiveNodeStore';
import { useUpdateNodeStore } from './store/mcUpdateNodeStore';
import { useCKEditor } from '../composable/useCKEditor';


const props = defineProps({
    /**
     * to gradually add more marginLeft to indicate node level depth
     */
    depth: {
        type: Number,
        required: true,
    },
});

const store = useStore();
const activeNodeStore = useActiveNodeStore();
const updateNodeStore = useUpdateNodeStore();


/**
 * to gradually and more marginLeft to indicate node level depth
 *
 * @returns { Number }
 */
const marginLeft = computed(() => `calc(${props.depth * 2}rem)`);

/**
 * Store a reference to the ckeditor used for the description
 *
 * @returns { String }
 */
const ckeditorInstance = computed(() => `description-${activeNodeStore.node?.id}` );

/**
 * Use a customised ckeditor for the description field
 */
const descriptionField = useCKEditor(updateNodeStore.activeNodeCopy.description, ckeditorInstance.value);

/**
 * And watch for changes on this field so we can update the description in the store
 */
watch(descriptionField, (newValue) => {
    updateNodeStore.activeNodeCopy.description = newValue;
});

/**
 * To instantly notify the user that the node is being updated
 * by updating the button text
 *
 * @returns { String }
 */
const updateButtonText = computed(() => updateNodeStore.isProcessing ? 'Updating...' : 'Update');

/**
 * submit save
 */
function onUpdateSelected () {
    updateNodeStore.update();
}

/**
 * to display the toast on subsection added / failed
 */
updateNodeStore.$onAction(({ name, after, onError }) => {
    if (name === 'update') {
        store.toastEl.activate('Updating...');
        after((result) => {
            store.toastEl.timeout(result.message, true, 5000);
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.timeout('Unable to update node', false, 5000);
        });
    }
});

/**
 * close the update view
 */
function onCancelSelected () {
    updateNodeStore.cancel();
}

/**
 * Only enable the button once the conditions are met
 *
 * @returns { Boolean }
 */
const isUpdateDisabled = computed(() => {
    return updateNodeStore.activeNodeCopy.name.trim().length === 0 || updateNodeStore.isProcessing;
});

</script>

<template>
    <div class="node-container">
        <section>
            <fieldset class="content">
                <legend>Update {{ activeNodeStore.title }}</legend>
                <div class="small-12 columns">
                    <label for="name" class="required">Name</label>
                    <input type="text" id="name" v-model="updateNodeStore.activeNodeCopy.name" maxlength="2048" required>
                </div>
                <div class="small-12 columns">
                    <label for="code">Code</label>
                    <input type="text" id="code" v-model="updateNodeStore.activeNodeCopy.code" maxlength="64">
                </div>
                <div class="small-12 columns">
                    <label for="description">Description</label>
                    <textarea :id="ckeditorInstance" rows="6" maxlength="65000"></textarea>
                </div>
                <div class="small-12 columns">
                    <ul class="flex-list">
                        <li>
                            <button @click.prevent="onCancelSelected" @keyup.enter="onCancelSelected" class="button icon-close" :disabled="updateNodeStore.isProcessing">Cancel</button>
                        </li>
                        <li>
                            <button @click.prevent="onUpdateSelected" @keyup.enter="onUpdateSelected" class="button icon-modify submit" :disabled="isUpdateDisabled">{{ updateButtonText }}</button>
                        </li>
                    </ul>
                </div>
            </fieldset>
        </section>
    </div>
</template>

<style lang="scss" scoped>

.node-container {
    border-bottom: 1px solid var(--content-ui-background);
    border-top: 1px solid var(--content-ui-background);
    margin: -1px 0 0 v-bind(marginLeft); //-1px so it butts up against the node border above
    position: relative;
    z-index: 1;
}

legend {
    font-size: 1rem;
    margin-bottom: 1rem;
}

</style>