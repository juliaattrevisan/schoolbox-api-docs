<script setup lang="ts">
/**
 * Custom Participation Include in Existing Modal Component
 */
import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { CustomisedPlan, Participant, Plan } from "../js/types/customParticipationTypes";

const props = defineProps<{
    customisations: (Plan | CustomisedPlan)[];
    participantList: Participant[];
    isSingle: boolean;
}>();

const isOpen = defineModel<boolean>("isOpen", { default: false });
const selectedActivityId = defineModel<number | null>("selectedActivityId", { default: null });

const emit = defineEmits<{
    (event: "confirm", participants: Participant[], action: string, isSingle: boolean): void;
}>();

/**
 * The current selected activity for inclusion.
 */
const selectedActivity = window.Vue.computed<Plan | CustomisedPlan | null>(() => {
    return props.customisations.find((plan) => plan.id === selectedActivityId.value) ?? null;
});

/**
 * Generates a label for a given customisation plan.
 * @param plan - The customisation plan to generate the label for.
 * @returns The label for the customisation plan.
 */
function getCustomisationLabel(plan: Plan | CustomisedPlan): string {
    if ("assessable" in plan && plan.assessable !== null) {
        return plan.assessable.title || "";
    }
    return plan.name || "";
}

/**
 * Generates the meta for a given customisation plan.
 * @param plan - The customisation plan to generate the meta for.
 * @returns The meta for the customisation plan.
 */
function getCustomisationMeta(plan: Plan | CustomisedPlan): string {
    if ("assessable" in plan && plan.assessable !== null) {
        return `(New ${locale.activity?.singular()})`;
    }
    return `(Modified ${locale.activity?.singular()})`;
}

/**
 * Handle cancel action
 */
function handleCancel(): void {
    isOpen.value = false;
}

/**
 * Handle confirm action
 */
function handleConfirm(): void {
    emit("confirm", props.participantList, "includeInExisting", props.isSingle);
}
</script>

<template>
    <basic-modal v-model:is-open="isOpen">
        <div class="l-flex-col l-gap-m f-no-margins">
            <div class="l-flex-col l-gap-s f-no-margins">
                <h2>Include in existing customisation</h2>
                <p class="meta">
                    Modifying participation for:
                    <template v-for="(participant, index) in participantList" :key="participant.user.id">
                        {{
                            participant.user.preferredName ? participant.user.preferredName : participant.user.firstname
                        }}
                        {{ participant.user.lastname }}<template v-if="index < participantList.length - 2">, </template
                        ><template v-if="index === participantList.length - 2"> and </template>
                    </template>
                </p>
            </div>
            <div class="l-flex-col l-gap-s f-no-margins">
                <label for="activitySelect">Customisation</label>
                <select id="activitySelect" v-model="selectedActivityId" class="f-no-margins">
                    <option value="null" disabled>Select customisation</option>
                    <option v-for="customisation in customisations" :key="customisation.id" :value="customisation.id">
                        {{ getCustomisationLabel(customisation) }} {{ getCustomisationMeta(customisation) }}
                    </option>
                </select>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button l-flex__item--1" @click.prevent="handleCancel">Cancel</button>
                <button
                    v-if="selectedActivity"
                    class="button submit l-flex__item--1"
                    data-test="cp-include-in-existing-modal-confirm-button"
                    @click.prevent="handleConfirm"
                >
                    Confirm
                </button>
            </div>
        </div>
    </basic-modal>
</template>

<style scoped></style>
