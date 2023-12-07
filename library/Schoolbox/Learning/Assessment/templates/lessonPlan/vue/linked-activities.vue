<script setup lang="ts">
import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal.vue";
import Toast from "@schoolboxVue/components/toast.vue";
import { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useLinkedActivitiesStore } from "./store/linkedActivitiesStore";
import linkedActivitiesItem from "./linked-activities-item.vue";
import LinkedActivitiesUnlink from "./linked-activities-unlink.vue";
import { Unit, CourseAssessments } from "./linkedActivitiesTypes";
import linkedActivitiesModalItem from "./linked-activities-modal-item.vue";

interface Props {
    courseAssessments: CourseAssessments;
    linkedActivities: Activity[];
    isInstructor: boolean;
    sourceId: number;
    gradeGradientEnable: boolean; // whether to show the student completion colour gradient on linked activities
}

const props = defineProps<Props>();

const toastEl = window.Vue.ref<Toast | null>(null);

const store = useLinkedActivitiesStore();

/**
 * Check if the unit is open (Between open and close date)
 * @param {Unit} unit The unit to check
 * @returns {boolean} True if the unit is open
 */
function isUnitOpen(unit: Unit): boolean {
    return window.dayjs().isBetween(window.dayjs(unit.openDate), window.dayjs(unit.closeDate));
}

/**
 * Check if the unit is open or if there are any selected activities inside the unit
 * @param {Unit} unit The unit to check
 * @returns {boolean} True if the unit is open or if there are any selected activities inside the unit
 */
function isUnitOpenOrHasLinked(unit: Unit): boolean {
    if (isUnitOpen(unit)) {
        return true;
    }

    // If any assessment inside the unit is inside store.selectedActivities, then show as open
    return unit.assessments.some((assessment) => {
        return store.selectedActivities.some((selectedActivity) => {
            return selectedActivity.id === assessment.id;
        });
    });
}

const returnUrl = window.Vue.computed(() => {
    return window.btoa(window.location.pathname);
});

/**
 * Get the urgency class for the unit
 * @param {Unit} unit The unit to get the urgency class for
 * @returns {string} The urgency class
 */
function getUnitUrgency(unit: Unit): string {
    if (window.dayjs().isBefore(window.dayjs(unit.openDate))) {
        return "gradient";
    }
    if (isUnitOpen(unit)) {
        return "gradient-10";
    }
    return "gradient-0";
}

/**
 * Get the completion message for the unit
 * @param {Unit} unit The unit to get the completion message for
 * @returns {string} The completion message
 */
function getUnitCompletion(unit: Unit): string {
    if (window.dayjs().isBefore(window.dayjs(unit.openDate))) {
        return `This unit will open ${unit.relativeOpenDateFormatted}`;
    }
    if (isUnitOpen(unit)) {
        return `This unit will close ${unit.relativeCloseDateFormatted}`;
    }
    return `This unit closed ${unit.relativeCloseDateFormatted}`;
}

const linkedActivitiesButtonText = window.Vue.computed(() => {
    const addedActivitiesCount = store.getAddedActivities().length;
    const removedActivitiesCount = store.getRemovedActivities().length;

    if (addedActivitiesCount === 0 && removedActivitiesCount === 0) {
        return "No Activities to Link";
    }
    if (addedActivitiesCount > 0 && removedActivitiesCount === 0) {
        return `Link ${addedActivitiesCount} ${addedActivitiesCount > 1 ? "Activities" : "Activity"}`;
    }
    if (addedActivitiesCount === 0 && removedActivitiesCount > 0) {
        return `Unlink ${removedActivitiesCount} ${removedActivitiesCount > 1 ? "Activities" : "Activity"}`;
    }
    return `Unlink ${removedActivitiesCount} ${removedActivitiesCount > 1 ? "Activities" : "Activity"}
            and Link ${addedActivitiesCount} ${addedActivitiesCount > 1 ? "Activities" : "Activity"}`;
});

const sortedUnitsByDate = window.Vue.computed(() => {
    const unsortedUnits = props.courseAssessments.units;
    return unsortedUnits.sort((a, b) => {
        return window.dayjs(a.openDate).diff(window.dayjs(b.openDate));
    });
});

window.Vue.onMounted(() => {
    useLinkedActivitiesStore().init(props.sourceId, props.linkedActivities, props.gradeGradientEnable, toastEl);
    $("[data-collapser]").collapsable();
});
</script>

<template>
    <h3 v-if="isInstructor || store.linkedActivities.length > 0" class="subheader">Activities</h3>
    <section v-if="store.linkedActivities.length > 0">
        <ul class="information-list">
            <li
                v-for="activity in store.linkedActivities"
                :key="activity.id"
                :class="{ 'actions-small-1': isInstructor }"
            >
                <linked-activities-item
                    :activity="activity"
                    :is-instructor="isInstructor"
                    :return-url="returnUrl"
                ></linked-activities-item>
            </li>
        </ul>
        <div v-if="isInstructor" class="component-action">
            <section>
                <a class="button" @click.prevent="store.isOpen = true">Link Activities</a>
            </section>
        </div>
    </section>
    <div v-else-if="isInstructor" class="empty-state">
        <i class="icon-link"></i>
        <p>
            There are currently no activities linked to this lesson. You may
            <a @click.prevent="store.isOpen = true">link existing activities</a> to this lesson for students to complete
            as part of this lesson.
        </p>
    </div>

    <sequential-reveal-modal
        v-if="isInstructor"
        size="medium"
        :is-open="store.isOpen"
        :is-next-selected="store.isNextSelected"
        :is-previous-selected="store.isPreviousSelected"
        @next-opened="store.isNextSelected = false"
        @previous-opened="store.isPreviousSelected = false"
        @closed="
            store.isPreviousSelected = false;
            store.isOpen = false;
        "
    >
        <template #sequence-1>
            <section>
                <h1>Link Activities</h1>
                <ul class="information-list expandable-card">
                    <li
                        v-for="unit in sortedUnitsByDate"
                        :key="unit.id"
                        :class="isUnitOpenOrHasLinked(unit) ? 'expanded' : 'collapsed'"
                    >
                        <div class="actions-small-1">
                            <div class="list-item">
                                <div class="card small-12">
                                    <h3>
                                        <a
                                            class="expand-link"
                                            :class="isUnitOpenOrHasLinked(unit) ? 'icon-up-arrow' : 'icon-down-arrow'"
                                            :data-collapser="'collap-unit-' + unit.id"
                                            :data-interaction-state="
                                                isUnitOpenOrHasLinked(unit) ? 'expanded' : 'collapsed'
                                            "
                                        ></a>
                                        {{ unit.title }}
                                    </h3>
                                    <p v-if="unit.closeDate !== null" :class="getUnitUrgency(unit)">
                                        {{ getUnitCompletion(unit) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div
                            :class="{ hide: !isUnitOpenOrHasLinked(unit) }"
                            :data-collapse-state="isUnitOpenOrHasLinked(unit) ? 'uncollapsed' : 'collapsed'"
                            :data-collapsable="'collap-unit-' + unit.id"
                        >
                            <ul class="information-list card-selectable threaded" data-task-list>
                                <template v-for="activity in unit.assessments" :key="activity.id">
                                    <li
                                        v-if="activity.assessmentType !== 'project'"
                                        :data-unit-assessment="activity.id"
                                        class="interact child"
                                    >
                                        <linked-activities-modal-item
                                            :activity="activity"
                                            :source-id="props.sourceId"
                                        ></linked-activities-modal-item>
                                    </li>
                                    <li
                                        v-else
                                        v-for="subActivity in activity.assessments"
                                        :key="subActivity.id"
                                        :data-unit-assessment="subActivity.id"
                                        class="interact child"
                                    >
                                        <linked-activities-modal-item
                                            :activity="subActivity"
                                            :source-id="props.sourceId"
                                            :project-activity="activity"
                                        ></linked-activities-modal-item>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </li>
                </ul>
                <a
                    href="#"
                    class="button expand icon-link"
                    :class="{
                        disabled: store.getAddedActivities().length === 0 && store.getRemovedActivities().length === 0,
                    }"
                    @click.prevent="store.saveSelectedActivities()"
                >
                    {{ linkedActivitiesButtonText }}
                </a>
            </section>
        </template>
        <template #sequence-2>
            <linked-activities-unlink></linked-activities-unlink>
        </template>
    </sequential-reveal-modal>

    <sequential-reveal-modal
        v-if="isInstructor"
        size="medium"
        :is-open="store.isSingleUnlinkOpen"
        @closed="store.isSingleUnlinkOpen = false"
    >
        <template #sequence-1>
            <linked-activities-unlink></linked-activities-unlink>
        </template>
    </sequential-reveal-modal>

    <toast ref="toastEl"></toast>
</template>

<style scoped></style>
