<script setup lang="ts">
/**
 * To display a single activity linked to a particular folder.
 * The activity can be dragged and dropped to reorder the activities list,
 * and further actions can be taken on the activity via the three dot menu
 * As the activity can be dragged and dropped, the activity can also become a placeholder
 * for when the actual activity is being dragged to help the user visualize where the activity will be placed
 */
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { kebabCase, startCase } from "lodash";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useActivitiesListStore } from "./store/activitiesListStore";
import { useActivitiesListDragStore } from "./store/activitiesListDragStore";
import { useActivitiesListDeleteModalStore } from "./store/activitiesListDeleteModalStore";

gsap.registerPlugin(Draggable);

interface Props {
    activitiesListStoreId: string; // The id of the activitiesListStoreId this belongs to
    activity: Activity;
    isDragPlaceholder: boolean;
    preventDraggableInit: boolean; // Whether to skip draggable initialization, e.g. if this is a placeholder
}

const props = defineProps<Props>();
const emit = defineEmits(["deleted"]);
const activitiesListStore = useActivitiesListStore(props.activitiesListStoreId);
const activitiesListDragStore = useActivitiesListDragStore(props.activitiesListStoreId);
const activitiesListDeleteModalStore = useActivitiesListDeleteModalStore();

const activityListEl = window.Vue.ref<HTMLElement | null>(null);

const activityLink = window.Vue.computed(() => {
    if (props.activity._links === null) {
        return null;
    }

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

const hasNavLinks = window.Vue.computed(() => {
    if (props.activity._links === null) return false;

    const validNavLinks = [
        props.activity._links.export,
        props.activity._links.modify,
        props.activity._links.modifyContent,
        props.activity._links.delete,
    ];

    return !validNavLinks.every((link) => link === null);
});

const draggableEl = window.Vue.ref<HTMLElement>();
let draggableInstance: ReturnType<typeof Draggable.create> | null = null;

window.Vue.onMounted(() => {
    // Whether to allow the user to resequence the activities
    if (activitiesListStore().routes.reorder && !props.preventDraggableInit) {
        activitiesListDragStore().setActivityEl(props.activity.id, activityListEl.value!);

        if (draggableEl.value) {
            draggableInstance = Draggable.create(draggableEl.value, {
                type: "y",
                cursor: "move",
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
    if (activitiesListStore().routes.reorder && !props.preventDraggableInit) {
        activitiesListDragStore().unsetActivityEl(props.activity.id);
    }
});
</script>

<template>
    <li
        ref="activityListEl"
        :class="[
            props.isDragPlaceholder ? 'ui-sortable-helper' : undefined,
            `actions-small-${(hasNavLinks ? 1 : 0) + (activitiesListStore().routes.reorder ? 1 : 0)}`,
        ]"
        :style="{
            top: props.isDragPlaceholder ? `${activitiesListDragStore().dragPlaceholderY}px` : undefined,
        }"
        :data-sortable-active="
            (activitiesListDragStore().dragCurrentActivity === props.activity && !props.preventDraggableInit) ||
            undefined
        "
    >
        <div class="list-item">
            <template v-if="activitiesListStore().routes.reorder">
                <div ref="draggableEl" class="sortable-holder"></div>
                <div class="grip compact ui-sortable-handle"></div>
            </template>
            <div
                class="card small-12 compact wrap-down"
                :class="{ 'activity-sortable': activitiesListStore().routes.reorder }"
            >
                <component
                    :is="activityLink !== null ? 'a' : 'span'"
                    :href="activityLink !== null ? getReturnUrl(activityLink) : undefined"
                >
                    <span
                        :class="'icon-' + kebabCase(props.activity.assessmentType)"
                        :title="startCase(props.activity.assessmentType)"
                    ></span>
                    <p>{{ props.activity.title }}</p>
                </component>
            </div>
        </div>
        <template v-if="hasNavLinks && props.activity._links !== null">
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
                <li v-if="props.activity._links.modify">
                    <a :href="getReturnUrl(props.activity._links.modify)" class="icon-modify">{{
                        props.activity.assessmentType === "quiz" ? "Modify Settings" : "Modify"
                    }}</a>
                </li>
                <li v-if="props.activity._links.modifyContent">
                    <a :href="getReturnUrl(props.activity._links.modifyContent)" class="icon-modify">Modify Contents</a>
                </li>
                <li v-if="props.activity._links.delete">
                    <a
                        class="icon-delete"
                        @click.prevent="activitiesListDeleteModalStore.prompt(props.activity, () => emit('deleted'))"
                        >Delete</a
                    >
                </li>
            </ul>
        </template>
    </li>
</template>

<style scoped lang="scss">
li[data-sortable-active="true"] .sortable-holder {
    transform: none !important;
}
.ui-sortable-helper {
    position: absolute;
    width: 100%;
}
.sortable-holder {
    background-color: transparent;
    height: 100%;
    position: absolute;
    width: 40px;
    z-index: var(--sbx-depth-default);
}

.card.compact {
    &.activity-sortable {
        padding-left: 0;
    }
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

/* Styles for printing */
@media print {
    .information-list.sortable > li .grip:before,
    .information-list.sortable-card > li .grip:before {
        display: none !important; // Hide the grip element in sortable lists
    }
    a {
        color: #000 !important;
        text-decoration: none !important;
    }
    .information-list > li {
        border: none !important; // Remove borders from list items in the information list
    }
}
</style>
