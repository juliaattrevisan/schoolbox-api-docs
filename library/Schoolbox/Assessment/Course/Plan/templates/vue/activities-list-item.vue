<script setup lang="ts">
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { kebabCase, startCase } from "lodash";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useActivitiesListStore } from "./store/activitiesListStore";
import { useActivitiesListDragStore } from "./store/activitiesListDragStore";
import { useActivitiesListDeleteModalStore } from "./store/activitiesListDeleteModalStore";

gsap.registerPlugin(Draggable);

interface Props {
    activitiesListStoreId: string; // The id of the activitiesListStoreId this belongs to
    activity: Activity;
    isDragPlaceholder: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(["deleted"]);
const activitiesListStore = useActivitiesListStore(props.activitiesListStoreId);
const activitiesListDragStore = useActivitiesListDragStore(props.activitiesListStoreId);
const activitiesListDeleteModalStore = useActivitiesListDeleteModalStore();

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
    // Whether to allow the user to resequence the activities
    if (activitiesListStore().routes.reorder && !props.isDragPlaceholder) {
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
    if (activitiesListStore().routes.reorder && !props.isDragPlaceholder) {
        activitiesListDragStore().unsetActivityEl(props.activity.id);
    }
});
</script>

<template>
    <li
        ref="activityListEl"
        class="actions-small-1"
        :class="{ 'ui-sortable-helper': props.isDragPlaceholder }"
        :style="{
            top: props.isDragPlaceholder ? `${activitiesListDragStore().dragPlaceholderY}px` : undefined,
        }"
        :data-sortable-active="
            (activitiesListDragStore().dragCurrentActivity === props.activity && !props.isDragPlaceholder) || undefined
        "
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
                <a
                    class="icon-delete"
                    @click.prevent="activitiesListDeleteModalStore.prompt(props.activity, () => emit('deleted'))"
                    >Delete</a
                >
            </li>
        </ul>
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
