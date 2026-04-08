<script setup lang="ts">
/**
 * Custom Participation Participant Row Component
 */

import { getReturnUrl } from "@js/util/sbx/urlUtils";
import locale from "@js/terminology/rosettaStone";
import type { PastoralFlag } from "@schoolboxLibrary/Pastoral/types/PastoralFlag";
import PastoralFlags from "@schoolboxLibrary/Pastoral/vue/flags/pastoral-flags.vue";
import type { CustomisedPlan, Participant, Plan, Routes } from "../js/types/customParticipationTypes";
import { hasCustomisation } from "../js/util/participantUtils";

const props = defineProps<{
    participant: Participant;
    showCheckboxes: boolean;
    pastoralFlags: Record<string, PastoralFlag[]>;
    routes: Routes;
    assessmentId: number;
    canSuggestCustomisation: boolean | undefined;
    customisations: (Plan | CustomisedPlan)[];
    differentiationEnabled: boolean;
}>();

const selectedList = defineModel<Participant[]>("selectedList", { default: [] });

const emit = defineEmits<{
    (event: "viewDifferentiationProfile", participant: Participant): void;
    (event: "viewSuggestedCustomisation", participant: Participant): void;
    (event: "actionSelected", participants: Participant[], action: string, isSingle: boolean): void;
    (event: "openIncludeInExistingModal", isSingle: boolean, participant: Participant | null): void;
}>();

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
 * Gets the modification URL for a customised plan
 * @param customisedPlan - The customised plan to get the URL for
 * @returns The modification URL with return URL parameter
 */
function getCustomisedPlanUrl(customisedPlan: CustomisedPlan): string {
    const baseUrl =
        customisedPlan.name !== null ? customisedPlan._links.modify : customisedPlan.assessable._links.modify;
    return `${baseUrl}?${getReturnUrl()}`;
}

/**
 * To open the differentiation profile view
 */
function onViewDP(): void {
    emit("viewDifferentiationProfile", props.participant);
}

/**
 * To generate or view the AI suggested customisation
 */
function onViewSuggestedCustomisation(): void {
    emit("viewSuggestedCustomisation", props.participant);
}

/**
 * To open the action event
 * @param action - The action to perform
 */
function onActionSelected(action: string): void {
    emit("actionSelected", [props.participant], action, true);
}

/**
 * To open the include in existing modal
 */
function onOpenIncludeInExistingModal(): void {
    emit("openIncludeInExistingModal", true, props.participant);
}
</script>

<template>
    <tr :data-test="`custom-participation-row-${props.participant.user.id}`">
        <td v-if="showCheckboxes">
            <input
                :id="`id-${props.participant.user.id}`"
                v-model="selectedList"
                type="checkbox"
                :value="participant"
                :data-test="`custom-participation-checkbox-${props.participant.user.id}`"
            />
            <label :for="`id-${props.participant.user.id}`" class="checkbox-label"></label>
        </td>
        <td>
            <img
                :src="`/portrait.php?id=${props.participant.user.id}&size=square64`"
                :alt="`${props.participant.user.lastname}, ${props.participant.user.firstname}`"
            />
        </td>
        <td>
            <div class="l-flex-col l-gap-s">
                <div v-if="props.participant.user.id">
                    <a :href="`/search/user/${props.participant.user.id}`">
                        {{ props.participant.user.lastname }},
                        {{ props.participant.user.preferredName || props.participant.user.firstname }}
                        {{ props.participant.user.pronoun || "" }}
                    </a>
                </div>
                <template v-else>
                    {{ props.participant.user.lastname }},
                    {{ props.participant.user.preferredName || props.participant.user.firstname }}
                    {{ props.participant.user.pronoun || "" }}
                </template>
                <pastoral-flags
                    v-if="
                        pastoralFlags[props.participant.user.id] && pastoralFlags[props.participant.user.id].length > 0
                    "
                    :user-id="props.participant.user.id"
                    :flags="props.pastoralFlags[props.participant.user.id]"
                />
            </div>
        </td>
        <td v-if="props.differentiationEnabled">
            <button
                v-if="props.participant._links.differentiationProfile"
                class="button icon-learning-support f-no-margins"
                type="button"
                :data-test="`cp-participant-${props.participant.user.id}-view-differentiation-profile`"
                @click.prevent="onViewDP"
            >
                View {{ locale.differentiationProfile.translate(false, true) }}
            </button>
        </td>
        <td v-if="canSuggestCustomisation">
            <button
                v-if="props.participant._links.differentiationProfile"
                class="button f-no-margins"
                type="button"
                @click.prevent="onViewSuggestedCustomisation"
            >
                <sbx-icon name="ai"></sbx-icon>
                {{ props.participant.cachedSuggestedCustomisation ? "View" : "Generate" }} Suggested Customisation
            </button>
        </td>
        <td>
            <template v-if="props.participant.customisedPlan !== null">
                <a :href="getCustomisedPlanUrl(props.participant.customisedPlan)">
                    {{ props.participant.customisedPlan.name ?? props.participant.customisedPlan.assessable.title }}
                </a>
                <span class="meta">{{ getCustomisationMeta(props.participant.customisedPlan) }}</span>
            </template>
            <template v-else-if="props.participant.plan.name !== null">
                <a :href="`${props.participant.plan._links.modify}?${getReturnUrl()}`">
                    {{ props.participant.plan.name }}
                </a>
                <span class="meta">{{ getCustomisationMeta(props.participant.plan) }}</span>
            </template>
            <template v-else-if="props.participant.excludedAt !== null">Excluded</template>
        </td>
        <td class="no-pad">
            <div v-if="props.participant.user.id" class="actions-small-1">
                <nav>
                    <sbx-pop-out
                        :content-id="`participant-${props.participant.user.id}`"
                        move-to="#pop-out-container"
                        is-close-on-content-click
                    ></sbx-pop-out>
                </nav>
                <ul :id="`participant-${props.participant.user.id}`" data-dropdown-content="" class="f-dropdown">
                    <template v-if="hasCustomisation(props.participant)">
                        <li class="separator">Manage</li>
                        <li>
                            <button
                                class="sbx-button--has-icon"
                                @click.prevent="onActionSelected('removeCustomisation')"
                            >
                                <sbx-icon name="close"></sbx-icon>
                                Remove Customisation
                            </button>
                        </li>
                    </template>
                    <template v-else>
                        <li class="separator">Manage</li>
                        <li>
                            <button
                                @click.prevent="
                                    onActionSelected(
                                        `${props.routes.getLearningPlanNewUrl}?student=${props.participant.user.id}&${getReturnUrl()}`,
                                    )
                                "
                            >
                                <sbx-icon name="modify"></sbx-icon>
                                Modify Existing {{ locale.activity.translate(false, true) }}
                            </button>
                        </li>
                        <li>
                            <button class="icon-staff-students" @click.prevent="onActionSelected('exclude')">
                                Exclude Student
                            </button>
                        </li>
                        <li>
                            <button v-if="customisations.length > 0" @click.prevent="onOpenIncludeInExistingModal">
                                <sbx-icon name="list"></sbx-icon>
                                Include in Existing Customisation
                            </button>
                        </li>
                        <li class="separator">Create</li>
                        <li v-if="props.routes.getAssessmentParticipationCopyActivity">
                            <button
                                @click.prevent="
                                    onActionSelected(
                                        `${props.routes.getAssessmentParticipationCopyActivity}?student=${props.participant.user.id}&original=${props.assessmentId}&${getReturnUrl()}`,
                                    )
                                "
                            >
                                <sbx-icon name="copy"></sbx-icon>
                                Create Copy of {{ locale.activity.translate(false, true) }}
                            </button>
                        </li>
                        <li>
                            <button
                                @click.prevent="
                                    onActionSelected(
                                        `${props.routes.getAssessmentParticipationNewActivity}?student=${props.participant.user.id}&original=${props.assessmentId}&${getReturnUrl()}`,
                                    )
                                "
                            >
                                <sbx-icon name="submissions"></sbx-icon>
                                Create New {{ locale.activity.translate(false, true) }}
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
        </td>
    </tr>
</template>

<style scoped></style>
