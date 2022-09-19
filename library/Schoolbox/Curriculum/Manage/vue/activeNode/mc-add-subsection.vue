<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { useStore } from '../store/manageCurriculumStore';
import { computed, watch } from 'vue';
import { useActiveNodeStore } from './store/mcActiveNodeStore';
import { useAddSubsectionStore } from './store/mcAddSubsectionStore';
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
const addSubsectionStore = useAddSubsectionStore();

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
const ckeditorInstance = computed(() => `description-${activeNodeStore.node.id}`);

/**
 * Use a customised ckeditor for the description field
 */
 const descriptionField = useCKEditor(addSubsectionStore.subsection.description, ckeditorInstance.value);

/**
 * And watch for changes on this field so we can update the description in the store
 */
watch(descriptionField, (newValue) => {
    addSubsectionStore.subsection.description = newValue;
});

/**
 * To instantly notify the user that the subsection is being added
 * by updating the button text
 *
 * @returns { String }
 */
const addButtonText = computed(() => addSubsectionStore.isProcessing ? 'Adding...' : 'Add');

/**
 * submit save
 */
function onAddSubsectionSelected () {
    addSubsectionStore.add();
}

/**
 * to display the toast on subsection added / failed
 */
 addSubsectionStore.$onAction(({ name, after, onError }) => {
    if (name === 'add') {
        store.toastEl.activate('Adding...');
        after((result) => {
            store.toastEl.timeout(result.message, true, 5000);
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.timeout('Unable to add subsection', false, 5000);
        });
    }
});

/**
 * close the addSubsection view
 */
function onCancelSelected () {
    addSubsectionStore.cancel();
}

/**
 * Only enable the button once the conditions are met
 *
 * @returns { Boolean }
 */
const isAddDisabled = computed(() => {
    return addSubsectionStore.subsection.name.trim().length === 0 || addSubsectionStore.isProcessing;
});

</script>

<template>
    <div class="node-container">
        <section>
            <fieldset class="content">
                <legend>Add subsection to {{ activeNodeStore.title }}</legend>
                <div class="small-12 columns">
                    <label for="name" class="required">Name</label>
                    <input type="text" id="name" v-model="addSubsectionStore.subsection.name" maxlength="2048" required>
                </div>
                <div class="small-12 columns">
                    <label for="code">Code</label>
                    <input type="text" id="code" v-model="addSubsectionStore.subsection.code" maxlength="64">
                </div>
                <div class="small-12 columns">
                    <label for="description">Description</label>
                    <textarea :id="ckeditorInstance" rows="6" maxlength="65000"></textarea>
                </div>
                <div class="small-12 columns">
                    <ul class="flex-list">
                        <li>
                            <button @click.prevent="onCancelSelected" @keyup.enter="onCancelSelected" class="button icon-close" :disabled="addSubsectionStore.isProcessing">Cancel</button>
                        </li>
                        <li>
                            <button @click.prevent="onAddSubsectionSelected" @keyup.enter="onAddSubsectionSelected" class="button icon-add submit" :disabled="isAddDisabled">{{ addButtonText }}</button>
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