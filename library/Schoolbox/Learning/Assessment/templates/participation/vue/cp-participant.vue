<script setup lang="ts">
/**
 * Custom Participation Participant Row Component
 */

import { getReturnUrl } from "@js/util/sbx/urlUtils";
import locale from "@js/terminology/rosettaStone";
import type { StudentPastoralCountBadge } from "@schoolboxLibrary/Pastoral/types/StudentPastoralCountBadge";
import type { CustomisedPlan, Participant, Plan, Routes } from "../js/types/customParticipationTypes";

const props = defineProps<{
    participant: Participant;
    showCheckboxes: boolean;
    studentPastoralCounts: Record<string, StudentPastoralCountBadge[]>;
    routes: Routes;
    assessmentId: number;
    customisations: (Plan | CustomisedPlan)[];
}>();

const selectedList = defineModel<Participant[]>("selectedList", { default: [] });

const emit = defineEmits<{
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
 * Handle action selection events
 * @param action - The action to perform
 */
function handleActionSelected(action: string): void {
    emit("actionSelected", [props.participant], action, true);
}

/**
 * Handle include in existing modal
 */
function handleOpenIncludeInExistingModal(): void {
    emit("openIncludeInExistingModal", true, props.participant);
}
</script>

<template>
    <tr :data-test="`custom-participation-row-${participant.user.id}`">
        <td v-if="showCheckboxes">
            <input
                :id="`id-${participant.user.id}`"
                v-model="selectedList"
                type="checkbox"
                :value="participant"
                :data-test="`custom-participation-checkbox-${participant.user.id}`"
            />
            <label :for="`id-${participant.user.id}`" class="checkbox-label"></label>
        </td>
        <td>
            <img
                :src="`/portrait.php?id=${participant.user.id}&size=square64`"
                :alt="`${participant.user.lastname}, ${participant.user.firstname}`"
            />
        </td>
        <td>
            <div class="l-flex-col l-gap-s">
                <div v-if="participant.user.id">
                    <a :href="`/search/user/${participant.user.id}`">
                        {{ participant.user.lastname }},
                        {{ participant.user.preferredName || participant.user.firstname }}
                        {{ participant.user.pronoun || "" }}
                    </a>
                </div>
                <template v-else>
                    {{ participant.user.lastname }},
                    {{ participant.user.preferredName || participant.user.firstname }}
                    {{ participant.user.pronoun || "" }}
                </template>
                <div class="l-flex-row l-gap-xs">
                    <sbx-label
                        v-for="studentBadge in studentPastoralCounts[participant.user.id]"
                        :key="`badge-${studentBadge.typeId}-${studentBadge.subtypeId}`"
                        :title="studentBadge.title"
                        :text="studentBadge.count.toString()"
                        :icon="studentBadge.icon"
                        :background-color="studentBadge.bgColor"
                        :text-color="studentBadge.fgColor"
                        :href="studentBadge.destinationUrl"
                    />
                </div>
            </div>
        </td>
        <td>
            <p>Blah</p>
        </td>
        <td>
            <template v-if="participant.customisedPlan !== null">
                <a :href="getCustomisedPlanUrl(participant.customisedPlan)">
                    {{ participant.customisedPlan.name ?? participant.customisedPlan.assessable.title }}
                </a>
                <span class="meta">{{ getCustomisationMeta(participant.customisedPlan) }}</span>
            </template>
            <template v-else-if="participant.plan.name !== null">
                <a :href="`${participant.plan._links.modify}?${getReturnUrl()}`">
                    {{ participant.plan.name }}
                </a>
                <span class="meta">{{ getCustomisationMeta(participant.plan) }}</span>
            </template>
            <template v-else-if="participant.excludedAt !== null">Excluded</template>
        </td>
        <td class="no-pad">
            <div v-if="participant.user.id" class="actions-small-1">
                <nav>
                    <sbx-pop-out
                        :content-id="`participant-${participant.user.id}`"
                        move-to="#pop-out-container"
                        is-close-on-content-click
                    ></sbx-pop-out>
                </nav>
                <ul :id="`participant-${participant.user.id}`" data-dropdown-content="" class="f-dropdown">
                    <template
                        v-if="
                            participant.excludedAt !== null ||
                            participant.plan.name !== null ||
                            participant.customisedPlan !== null
                        "
                    >
                        <li class="separator">Manage</li>
                        <li>
                            <button class="icon-close" @click.prevent="handleActionSelected('removeCustomisation')">
                                Remove Customisation
                            </button>
                        </li>
                    </template>
                    <template v-else>
                        <li class="separator">Manage</li>
                        <li>
                            <button
                                class="icon-modify"
                                @click.prevent="
                                    handleActionSelected(
                                        `${props.routes.getLearningPlanNewUrl}?student=${participant.user.id}&${getReturnUrl()}`,
                                    )
                                "
                            >
                                Modify
                            </button>
                        </li>
                        <li>
                            <button class="icon-staff-students" @click.prevent="handleActionSelected('exclude')">
                                Exclude
                            </button>
                        </li>
                        <li>
                            <button
                                v-if="customisations.length > 0"
                                class="icon-list"
                                @click.prevent="handleOpenIncludeInExistingModal"
                            >
                                Include in Existing Customisation
                            </button>
                        </li>
                        <li class="separator">Create</li>
                        <li v-if="props.routes.getAssessmentParticipationCopyActivity">
                            <button
                                class="icon-copy"
                                @click.prevent="
                                    handleActionSelected(
                                        `${props.routes.getAssessmentParticipationCopyActivity}?student=${participant.user.id}&original=${props.assessmentId}&${getReturnUrl()}`,
                                    )
                                "
                            >
                                Copy Activity
                            </button>
                        </li>
                        <li>
                            <button
                                class="icon-submissions"
                                @click.prevent="
                                    handleActionSelected(
                                        `${props.routes.getAssessmentParticipationNewActivity}?student=${participant.user.id}&original=${props.assessmentId}&${getReturnUrl()}`,
                                    )
                                "
                            >
                                New Activity
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
        </td>
    </tr>
</template>

<style scoped></style>
