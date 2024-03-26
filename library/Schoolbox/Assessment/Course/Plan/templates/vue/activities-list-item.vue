<script setup lang="ts">
import { kebabCase, startCase } from "lodash";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useDeleteActivityStore } from "./store/unitPlannerDeleteActivityStore";
import { useActivitiesDragStore } from "./store/activitiesDragStore";
import type { Unit } from "./unitPlannerTypes";

interface Props {
    activitiesDragStoreId: string; // The id of the activitiesDragStore this belongs to
    unit: Unit; // The id of the unit to which the activity belongs.
    activity: Activity;
}

const props = defineProps<Props>();
const deleteActivityStore = useDeleteActivityStore();
const activitiesDragStore = useActivitiesDragStore(props.activitiesDragStoreId);

const activityListEl = window.Vue.ref<HTMLElement | null>(null);

const activityLink = window.Vue.computed(() => {
    // If the activity is a project, use the status link
    if (props.activity.assessmentType === "project") {
        return props.activity._links.status;
    }
    // If the modifyContent link exists, use it - This is the case for quizzes
    if (props.activity._links.modifyContent) {
        return props.activity._links.modifyContent;
    }
    // Otherwise, use the summary link
    return props.activity._links.summary;
});

// When activityListEl is set, add the activity to the drag store (effectively after mount)
window.Vue.watch(
    activityListEl,
    () => {
        if (!activityListEl.value) return;
        activitiesDragStore().setActivityEl(props.activity.id, activityListEl.value);
    },
    { immediate: true },
);

window.Vue.onUnmounted(() => {
    activitiesDragStore().unsetActivityEl(props.activity.id);
});
</script>

<template>
    <li ref="activityListEl" class="actions-small-1">
        <div class="list-item">
            <div class="card small-12 compact wrap-down">
                <a :href="getReturnUrl(activityLink)">
                    <span
                        :class="'icon-' + kebabCase(props.activity.assessmentType)"
                        :title="startCase(props.activity.assessmentType)"
                    ></span>
                    <p>{{ props.activity.title }}</p>
                </a>
            </div>
        </div>
        <nav>
            <sbx-pop-out
                :content-id="'dropdown-unitplanner-activity-' + props.activity.id"
                move-to="#unitplanner-dropdown-container"
            ></sbx-pop-out>
        </nav>
        <!-- Move the dropdown content to the unit planner table so that it can get around the cell's overflow:hidden -->
        <ul :id="'dropdown-unitplanner-activity-' + props.activity.id" class="f-dropdown" data-dropdown-content>
            <li v-if="props.activity._links.export">
                <a
                    :href="getReturnUrl(props.activity._links.export)"
                    class="icon-export"
                    title="Copy this task into classes"
                    >Export to Classes</a
                >
            </li>
            <li>
                <a :href="getReturnUrl(props.activity._links.modify)" class="icon-modify">{{
                    props.activity.assessmentType === "quiz" ? "Modify Settings" : "Modify"
                }}</a>
            </li>
            <li v-if="props.activity.assessmentType === 'quiz' && props.activity._links.modifyContent">
                <a :href="getReturnUrl(props.activity._links.modifyContent)" class="icon-modify">Modify Contents</a>
            </li>
            <li>
                <a class="icon-delete" @click.prevent="deleteActivityStore.prompt(props.unit, props.activity)"
                    >Delete</a
                >
            </li>
        </ul>
    </li>
</template>

<style scoped>
.card.compact {
    padding-left: 0;
    padding-right: 0;
}

.card.compact > * {
    height: 2.5rem;
}

.card.compact a p {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
