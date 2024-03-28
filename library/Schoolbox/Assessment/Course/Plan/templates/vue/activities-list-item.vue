<script setup lang="ts">
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { kebabCase, startCase } from "lodash";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useActivitiesListStore } from "./store/activitiesListStore";
import { useActivitiesListDragStore } from "./store/activitiesListDragStore";

gsap.registerPlugin(Draggable);

interface Props {
    activitiesListStoreId: string; // The id of the activitiesListStoreId this belongs to
    activity: Activity;
}

const props = defineProps<Props>();
const activitiesListStore = useActivitiesListStore(props.activitiesListStoreId);
const activitiesListDragStore = useActivitiesListDragStore(props.activitiesListStoreId);

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

const draggableEl = window.Vue.ref<HTMLElement>();
let draggableInstance: ReturnType<typeof Draggable.create> | null = null;

window.Vue.onMounted(() => {
    activitiesListDragStore().setActivityEl(props.activity.id, activityListEl.value!);

    // Whether to allow the user to resequence the activities
    if (activitiesListStore().routes.reorder) {
        if (draggableEl.value) {
            draggableInstance = Draggable.create(draggableEl.value, {
                type: "y",
                autoScroll: 1, // Scroll speed
                inertia: true,
                allowContextMenu: true,
                onPress: () => {
                    if (!draggableInstance) return;
                    activitiesListDragStore().onDragStart(draggableInstance[0], props.activity);
                },
                onDrag: () => {
                    if (!draggableInstance) return;
                    activitiesListDragStore().onDragMove(draggableInstance[0]);
                },
                onRelease: () => {
                    if (!draggableInstance) return;
                    activitiesListDragStore().onDragEnd(draggableInstance[0]);
                },
            });
        }
    }
});

window.Vue.onUnmounted(() => {
    activitiesListDragStore().unsetActivityEl(props.activity.id);
});
</script>

<template>
    <li
        ref="activityListEl"
        class="actions-small-1"
        :class="{
            'is-dragging': activitiesListDragStore().dragCurrentActivityID === props.activity.id,
        }"
    >
        <div class="list-item">
            <div v-if="activitiesListStore().routes.reorder" ref="draggableEl" class="sortable-holder"></div>
            <div class="grip compact ui-sortable-handle"></div>
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

<style scoped lang="scss">
li.is-dragging {
    background-color: #f8f8f8;
    border: 1px solid #e0e0e0;

    .sortable-holder {
        transform: none !important;
    }
}
.sortable-holder {
    background-color: transparent;
    height: 100%;
    position: absolute;
    width: 40px;
    z-index: 100;
}

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
