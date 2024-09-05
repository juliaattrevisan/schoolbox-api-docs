import type { Ref } from "vue";
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { ScrollToPlugin } from "gsap/all";
import { defineStore } from "pinia";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useActivitiesListStore } from "./activitiesListStore";

gsap.registerPlugin(Draggable);
gsap.registerPlugin(ScrollToPlugin);

// eslint-disable-next-line @typescript-eslint/explicit-function-return-type -- we want the inferred type from defineStore
export const useActivitiesListDragStore = (activitiesListStoreId: string) =>
    defineStore(`activitiesListDragStore/${activitiesListStoreId}`, () => {
        const activitiesListStore = useActivitiesListStore(activitiesListStoreId);

        const activityEls = window.Vue.ref<Record<Activity["id"], HTMLElement>>({});

        // If an activity is deleted from the store, remove it from the activityEls
        window.Vue.watch(activitiesListStore().activities, (newActivities, oldActivities) => {
            const newActivityIDs = newActivities.map((activity) => activity.id);
            const oldActivityIDs = oldActivities.map((activity) => activity.id);
            const removedActivityIDs = oldActivityIDs.filter((id) => !newActivityIDs.includes(id));
            removedActivityIDs.forEach((id) => {
                delete activityEls.value[id];
            });
        });

        // Allow the refs to be passed in instead of creating and monitoring two
        let activitiesContainerEl: Ref<HTMLElement>;

        const isDragging = window.Vue.ref(false);
        const dragCurrentActivity = window.Vue.ref<Activity | null>(null);
        const dragCurrentPointerY = window.Vue.ref<number | null>(null);
        const dragInitial = window.Vue.ref<{
            y: number;
            offsetY: number;
        } | null>(null);

        /**
         * store any passed through properties
         * @param $activitiesContainerEl - ref to the element containing the activities
         */
        function init($activitiesContainerEl: Ref<HTMLElement>): void {
            activitiesContainerEl = $activitiesContainerEl;
        }

        let callbackOnDragStart: () => void;
        let callbackOnDragEnd: () => void;

        /**
         * Set the callback function to be called on drag start
         * @param callback - the callback function
         */
        function setCallbackOnDragStart(callback: () => void): void {
            callbackOnDragStart = callback;
        }

        /**
         * Set the callback function to be called on drag end
         * @param callback - the callback function
         */
        function setCallbackOnDragEnd(callback: () => void): void {
            callbackOnDragEnd = callback;
        }

        /**
         * Assign an element to a draggable activity
         * @param activityId - the activity id
         * @param el - the element to add
         */
        function setActivityEl(activityId: number, el: HTMLElement): void {
            activityEls.value[activityId] = el;
        }

        /**
         * De-assign an element from a draggable activity
         * @param activityId - the activity id
         */
        function unsetActivityEl(activityId: number): void {
            delete activityEls.value[activityId];
        }

        const dragPlaceholderY = window.Vue.computed(() => {
            if (
                dragCurrentPointerY.value === null ||
                dragInitial.value === null ||
                activitiesContainerEl.value === undefined
            ) {
                // Not ready to calculate the placeholder position yet, either not dragging or not initialised yet
                return -1;
            }

            // The optimal placeholder position is:
            // - the current mouse pointer Y position
            // - minus the top of the activities container
            // - minus the initial offset of the drag (which is the difference between where the user grabbed the drag handle, and the top of the row)
            const targetHeight =
                dragCurrentPointerY.value -
                activitiesContainerEl.value.getBoundingClientRect().top -
                dragInitial.value.offsetY;

            // Clamp the target height to between:
            // - Min of 0 (Top of the container)
            // - Max of the height of every row combined excluding the dragged row
            const minDragHeight = 0;
            const maxDragHeight =
                Math.max(
                    activitiesContainerEl.value.clientHeight,
                    Object.values(activityEls.value).reduce((acc, draggableEl) => {
                        return acc + draggableEl.clientHeight;
                    }, 0),
                ) - activityEls.value[dragCurrentActivity.value!.id].clientHeight;
            return Math.max(minDragHeight, Math.min(targetHeight, maxDragHeight));
        });

        const orderedActivities = window.Vue.computed<Activity[]>(() => {
            // Show the current order if:
            // - This was created from a unit sortable placeholder (which doesn't allow reordering)
            // - or, the user is not dragging
            // - or, no current draggable is set
            // - or, the initial drag info is not set
            if (
                // isUnitSortablePlaceholder.value ||
                !isDragging.value ||
                dragCurrentActivity.value === null ||
                dragInitial.value === null
            ) {
                return activitiesListStore().activities;
            }

            // Use reduce to find the index where the unit should be inserted
            const initialReduceVals = { cumulativeHeight: 0, foundDragIndex: 0, found: false };
            const reduceResult = activitiesListStore().activities.reduce((acc, activity, index) => {
                if (acc.found) return acc; // Skip further processing if index is found

                const activityEl = activityEls.value[activity.id];
                if (activityEl === undefined) {
                    throw new Error(`Activity ${activity.id} has no draggable element`);
                }

                const newCumulativeHeight = acc.cumulativeHeight + activityEl.clientHeight;

                // If the new cumulative height is greater than the top of the drag placeholder (just above the mouse position),
                // then we've found the index
                if (newCumulativeHeight >= dragPlaceholderY.value && !acc.found) {
                    return { ...acc, foundDragIndex: index, found: true };
                }

                // Otherwise, continue to the next unit
                return { ...acc, cumulativeHeight: newCumulativeHeight, foundDragIndex: index + 1 };
            }, initialReduceVals);

            const adjustedDragIndex = Math.min(
                reduceResult.foundDragIndex,
                activitiesListStore().activities.length - 1,
            );

            // Reorder the units based on the new index
            const newOrderedActivities = [...activitiesListStore().activities];
            const dragActivityIndex = activitiesListStore().activities.findIndex(
                (u) => u.id === dragCurrentActivity.value!.id,
            );
            const [draggedActivity] = newOrderedActivities.splice(dragActivityIndex, 1);
            newOrderedActivities.splice(adjustedDragIndex, 0, draggedActivity);

            return newOrderedActivities;
        });

        /**
         * While the user is dragging a unit
         * @param draggable - the draggable instance
         */
        function onDragMove(draggable: Draggable): void {
            dragCurrentPointerY.value = draggable.pointerY;
        }

        /**
         * When the user starts dragging a unit
         * @param draggable - the draggable instance
         * @param activity - the activity to start dragging
         */
        function onDragStart(draggable: Draggable, activity: Activity): void {
            const pointerEvent = draggable.pointerEvent as PointerEvent;
            const pointerTarget = pointerEvent.target as HTMLElement;

            dragCurrentPointerY.value = draggable.pointerY;
            dragCurrentActivity.value = activity;
            dragInitial.value = {
                // The initial position of the drag handle
                y: draggable.y,
                // The difference between the pointer position and the top of the row
                offsetY:
                    pointerEvent.offsetY +
                    pointerTarget.getBoundingClientRect().top -
                    pointerTarget.parentElement!.getBoundingClientRect().top,
            };

            isDragging.value = true;
            callbackOnDragStart();
        }

        /**
         * When the user stops dragging a unit
         * @param draggable - the draggable instance
         */
        function onDragEnd(draggable: Draggable): void {
            if (dragInitial.value !== null) {
                // Move the drag handle back to its original position
                gsap.to(draggable.target, {
                    y: dragInitial.value.y,
                    duration: 0,
                });
            }

            activitiesListStore().resequenceActivities(orderedActivities.value.map((activity) => activity.id));

            isDragging.value = false;
            dragCurrentActivity.value = null;
            dragCurrentPointerY.value = null;
            dragInitial.value = null;
            callbackOnDragEnd();
        }

        return {
            init,
            setCallbackOnDragStart,
            setCallbackOnDragEnd,
            activityEls,
            setActivityEl,
            unsetActivityEl,
            orderedActivities,
            isDragging,
            dragCurrentActivity,
            dragPlaceholderY,
            onDragStart,
            onDragMove,
            onDragEnd,
        };
    });
