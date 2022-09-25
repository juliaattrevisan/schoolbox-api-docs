<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { computed, watch } from 'vue';
import { useStore } from './store/manageCurriculumStore';
import { useAddCurriculumStore } from './store/mcAddCurriculumStore';
import { useCKEditor } from './composable/useCKEditor';


const store = useStore();
const addCurriculumStore = useAddCurriculumStore();


/**
 * Store a reference to the ckeditor used for the description
 *
 * @returns { String }
 */
const ckeditorInstance = computed(() => `new-curriculum`);

/**
 * Use a customised ckeditor for the description field
 */
const descriptionField = useCKEditor(addCurriculumStore.curriculum.description, ckeditorInstance.value);

/**
 * And watch for changes on this field so we can update the description in the store
 */
watch(descriptionField, (newValue) => {
    addCurriculumStore.curriculum.description = newValue;
});

/**
 * To instantly notify the user that the subsection is being added
 * by updating the button text
 *
 * @returns { String }
 */
const addButtonText = computed(() => addCurriculumStore.isProcessing ? 'Adding...' : 'Add');

/**
 * submit save
 */
function onAddSubsectionSelected () {
    addCurriculumStore.add();
}

/**
 * to display the toast on curriculum added / failed
 */
addCurriculumStore.$onAction(({ name, after, onError }) => {
    if (name === 'add') {
        store.toastEl.activate('Adding...');
        after((result) => {
            store.toastEl.timeout(result.message, true, 5000);
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.timeout('Unable to add Curriculum', false, 5000);
        });
    }
});

/**
 * close the addSubsection view
 */
function onCancelSelected () {
    addCurriculumStore.cancel();
}

/**
 * Only enable the button once the conditions are met
 *
 * @returns { Boolean }
 */
const isAddDisabled = computed(() => {
    return addCurriculumStore.curriculum.name.trim().length === 0 || addCurriculumStore.isProcessing;
});

</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <section>
                <fieldset class="content">
                    <legend>Add new Curriculum</legend>
                    <div class="small-12 columns">
                        <label for="name" class="required">Name</label>
                        <input type="text" id="name" v-model="addCurriculumStore.curriculum.name" maxlength="2048" required>
                    </div>
                    <div class="small-12 columns">
                        <label for="code">Code</label>
                        <input type="text" id="code" v-model="addCurriculumStore.curriculum.code" maxlength="64">
                    </div>
                    <div class="small-12 columns">
                        <label for="description">Description</label>
                        <textarea :id="ckeditorInstance" rows="6"></textarea>
                    </div>
                    <div class="small-12 columns">
                        <ul class="flex-list">
                            <li>
                                <button @click.prevent="onCancelSelected" @keyup.enter="onCancelSelected" class="button icon-close" :disabled="addCurriculumStore.isProcessing">Cancel</button>
                            </li>
                            <li>
                                <button @click.prevent="onAddSubsectionSelected" @keyup.enter="onAddSubsectionSelected" class="button icon-add submit" :disabled="isAddDisabled">{{ addButtonText }}</button>
                            </li>
                        </ul>
                    </div>
                </fieldset>
            </section>
        </div>
    </div>
</template>

<style lang="scss" scoped>

legend {
    font-size: 1rem;
    margin-bottom: 1rem;
}

</style>