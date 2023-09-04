<script setup lang="ts">
/**
 *
 */
import { kebabCase } from "lodash";
import { useLinkedActivitiesStore, Activity } from "./store/linkedActivitiesStore";
import linkedActivitiesModalItem from "./linked-activities-modal-item.vue";

type Unit = {
    id: number;
    title: string;
    openDate: string;
    closeDate: string;
    relativeOpenDateFormatted: string;
    relativeCloseDateFormatted: string;
    assessments: Activity[];
};

type CourseAssessments = {
    units: Unit[];
};

interface Props {
    courseAssessments: CourseAssessments;
    linkedActivities: Activity[];
    isInstructor: boolean;
    sourceId: number;
}

const props = defineProps<Props>();

useLinkedActivitiesStore().init(props.linkedActivities);
const store = useLinkedActivitiesStore();

/**
 * Get the title of the course that the activity belongs to
 * @param { Activity } activity The activity to get the course title for
 * @returns { string } Course title
 */
function getActivityCourseTitle(activity: Activity): string {
    const selectedUnit = props.courseAssessments.units.find((unit) => {
        return unit.assessments.find((a) => a.id === activity.id);
    });
    return selectedUnit ? selectedUnit.title : "";
}

/**
 * Check if the unit is open (Between open and close date)
 * @param {Unit} unit The unit to check
 * @returns {boolean} True if the unit is open
 */
function isUnitOpen(unit: Unit): boolean {
    return window.dayjs().isBetween(window.dayjs(unit.openDate), window.dayjs(unit.closeDate));
}

const unitUrgency = window.Vue.computed(() => {
    return "gradient";
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
        return `Link ${addedActivitiesCount} Activities`;
    }
    if (addedActivitiesCount === 0 && removedActivitiesCount > 0) {
        return `Unlink ${removedActivitiesCount} Activities`;
    }
    return `Unlink ${removedActivitiesCount} Activities and Link ${addedActivitiesCount} Activities`;
});

const sortedUnitsByDate = window.Vue.computed(() => {
    const unsortedUnits = props.courseAssessments.units;
    return unsortedUnits.sort((a, b) => {
        return window.dayjs(a.openDate).diff(window.dayjs(b.openDate));
    });
});

window.Vue.onMounted(() => {
    $("[data-collapser]").collapsable();
});
</script>

<template>
    <section>
        <ul class="information-list">
            <li v-for="unit in sortedUnitsByDate" :key="unit.id" :class="isUnitOpen(unit) ? 'expanded' : 'collapsed'">
                <div class="actions-small-1">
                    <div class="list-item">
                        <div class="card small-12">
                            <h3>
                                <a
                                    class="expand-link"
                                    :class="isUnitOpen(unit) ? 'icon-up-arrow' : 'icon-down-arrow'"
                                    :data-collapser="'collap-unit-' + unit.id"
                                    :data-interaction-state="isUnitOpen(unit) ? 'expanded' : 'collapsed'"
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
                    :class="{ hide: !isUnitOpen(unit) }"
                    :data-collapse-state="isUnitOpen(unit) ? 'uncollapsed' : 'collapsed'"
                    :data-collapsable="'collap-unit-' + unit.id"
                >
                    <ul class="information-list card-selectable threaded" data-task-list>
                        <li
                            v-for="activity in unit.assessments"
                            :key="activity.id"
                            class="interact child"
                            :data-unit-assessment="activity.id"
                        >
                            <linked-activities-modal-item
                                :activity="activity"
                                :source-id="props.sourceId"
                            ></linked-activities-modal-item>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
        <a
            href="#"
            class="button expand icon-link"
            :class="{ disabled: store.getAddedActivities().length === 0 && store.getRemovedActivities().length === 0 }"
            @click.prevent="store.saveSelectedActivities()"
        >
            {{ linkedActivitiesButtonText }}
        </a>
    </section>

    <section>
        <ul class="action-list grid">
            <li
                v-for="activity in store.linkedActivities"
                :key="activity.id"
                :class="{ 'actions-small-1': isInstructor }"
            >
                <div class="card small-12">
                    <h3>
                        <span :class="'icon-' + kebabCase(activity.assessmentType)"></span>
                        <a class="title">{{ activity.title }}</a>
                    </h3>
                    <p class="meta">
                        {{ getActivityCourseTitle(activity) }} |
                        {{ activity.title }}
                    </p>
                    <p class="meta">Due {{ activity.closeAtFormatted }}</p>
                </div>
                <nav v-if="isInstructor">
                    <a
                        class="icon-unlink no-text"
                        title="Unlink Assessment"
                        @click.prevent="store.removeActivity(activity, true)"
                    ></a>
                </nav>
            </li>
        </ul>
    </section>
</template>

<style scoped></style>
