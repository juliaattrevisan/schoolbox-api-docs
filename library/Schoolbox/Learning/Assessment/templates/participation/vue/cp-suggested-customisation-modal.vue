<script setup lang="ts">
/**
 * To generate and display a suggested customisation,
 * choose what type of customisation to apply and
 * notify if existing customisation or feedback already exists.
 */

import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CpAiModal from "./cp-ai-modal.vue";

const props = defineProps<{
    route: string;
    assessmentId: number;
}>();

const studentId = defineModel<number | null>("student-id", { default: null });

const emit = defineEmits<{
    (event: "customiseActivity"): void;
}>();

const isViewCustomisationOpen = window.Vue.ref(false);
const isSelectTypeOpen = window.Vue.ref(false);
const picked = window.Vue.ref("modify");

/**
 * To display the view/generate customisation modal when a student is selected
 */
window.Vue.watch(studentId, (newStudentId) => {
    if (newStudentId) {
        isViewCustomisationOpen.value = true;
        isSelectTypeOpen.value = false;
    }
});

/**
 * To move to the next step of customising the activity
 */
function onCustomiseActivity(): void {
    isViewCustomisationOpen.value = false;
    isSelectTypeOpen.value = true;
}

/**
 *
 */
function onBack(): void {
    isViewCustomisationOpen.value = true;
    isSelectTypeOpen.value = false;
}

/**
 * To use the suggested AI content
 */
function onConfirm(): void {
    // isViewCustomisationOpen.value = false;
    // isSelectTypeOpen.value = true;
}
</script>

<template>
    <cp-ai-modal
        v-model:is-open="isViewCustomisationOpen"
        v-model:student-id="studentId"
        :route="props.route"
        :assessment-id="props.assessmentId"
        @customise-activity="onCustomiseActivity"
    />
    <basic-modal v-model:is-open="isSelectTypeOpen">
        <form class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onConfirm">
            <div class="l-flex-row l-flex--justify-space l-gap-s f-no-margins">
                <h2>Select Customisation</h2>
            </div>
            <div>
                <ul class="option-list">
                    <li>
                        <input id="cp-modify" v-model="picked" type="radio" value="modify" />
                        <label for="cp-modify" class="icon-modify f-no-icon-color">Modify</label>
                    </li>
                    <li>
                        <input id="cp-copy" v-model="picked" type="radio" value="copy" />
                        <label for="cp-copy" class="icon-copy f-no-icon-color"
                            >Copy {{ locale.activity.translate(false, true) }}</label
                        >
                    </li>
                    <li>
                        <input id="cp-new" v-model="picked" type="radio" value="new" />
                        <label for="cp-new" class="icon-submissions f-no-icon-color"
                            >New {{ locale.activity.translate(false, true) }}</label
                        >
                    </li>
                </ul>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button l-flex__item--1" data-test="" @click.prevent="onBack">Back</button>
                <button class="button submit l-flex__item--1" type="button" data-test="" @click.prevent="onConfirm">
                    Proceed
                </button>
            </div>
        </form>
    </basic-modal>
</template>

<style scoped></style>
