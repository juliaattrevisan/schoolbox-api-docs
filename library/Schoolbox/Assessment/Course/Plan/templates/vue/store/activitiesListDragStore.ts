import type { Ref } from "vue";
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { ScrollToPlugin } from "gsap/all";
import { defineStore } from "pinia";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useActivitiesListStore } from "./activitiesListStore";

gsap.registerPlugin(Draggable);
gsap.registerPlugin(ScrollToPlugin);

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
        const dragCurrentActivityID = window.Vue.ref<Activity["id"] | null>(null);
        const dragCurrentPointerY = window.Vue.ref<number | null>(null);
        const dragInitial = window.Vue.ref<{
            y: number;
            offsetY: number;
        } | null>(null);

        let callbackOnDragStart: () => void;
        let callbackOnDragEnd: () => void;

        /**
         * store any passed through properties
         * @param { Ref<HTMLElement> } $activitiesContainerEl - ref to the element containing the activities
         * @param { () => void } $callbackOnDragStart - callback function to be called on drag start
         * @param { () => void } $callbackOnDragEnd - callback function to be called on drag end
         */
        function init(
            $activitiesContainerEl: Ref<HTMLElement>,
            $callbackOnDragStart: () => void,
            $callbackOnDragEnd: () => void,
        ) {
            activitiesContainerEl = $activitiesContainerEl;
            callbackOnDragStart = $callbackOnDragStart;
            callbackOnDragEnd = $callbackOnDragEnd;
        }

        /**
         * Assign an element to a draggable activity
         * @param { number } activityId - the activity id
         * @param { HTMLElement } el - the element to add
         */
        function setActivityEl(activityId: number, el: HTMLElement) {
            activityEls.value[activityId] = el;
        }

        /**
         * De-assign an element from a draggable activity
         * @param { number } activityId - the activity id
         */
        function unsetActivityEl(activityId: number) {
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
            // - Max of the height of every row combined
            const minDragHeight = 0;
            const maxDragHeight = Math.max(
                activitiesContainerEl.value.clientHeight,
                Object.values(activityEls.value).reduce((acc, draggableEl) => {
                    return acc + draggableEl.clientHeight;
                }, 0),
            );
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
                dragCurrentActivityID.value === null ||
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
                (u) => u.id === dragCurrentActivityID.value,
            );
            const [draggedActivity] = newOrderedActivities.splice(dragActivityIndex, 1);
            newOrderedActivities.splice(adjustedDragIndex, 0, draggedActivity);

            return newOrderedActivities;
        });

        /**
         * While the user is dragging a unit
         * @param { Draggable } draggable - the draggable instance
         * @returns { void }
         */
        function onDragMove(draggable: Draggable) {
            dragCurrentPointerY.value = draggable.pointerY;
        }

        /**
         * When the user starts dragging a unit
         * @param { Draggable } draggable - the draggable instance
         * @param { Activity } activity - the activity to start dragging
         */
        function onDragStart(draggable: Draggable, activity: Activity) {
            const pointerEvent = draggable.pointerEvent as PointerEvent;
            const pointerTarget = pointerEvent.target as HTMLElement;

            dragCurrentPointerY.value = draggable.pointerY;
            dragCurrentActivityID.value = activity.id;
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
         * @param { Draggable } draggable - the draggable instance
         */
        function onDragEnd(draggable: Draggable) {
            if (dragInitial.value !== null) {
                // Move the drag handle back to its original position
                gsap.to(draggable.target, {
                    y: dragInitial.value.y,
                    duration: 0,
                });
            }

            activitiesListStore().resequenceActivities(orderedActivities.value.map((activity) => activity.id));

            isDragging.value = false;
            dragCurrentActivityID.value = null;
            dragCurrentPointerY.value = null;
            dragInitial.value = null;
            callbackOnDragEnd();
        }

        return {
            init,
            activityEls,
            setActivityEl,
            unsetActivityEl,
            orderedActivities,
            isDragging,
            dragCurrentActivityID,
            dragPlaceholderY,
            onDragStart,
            onDragMove,
            onDragEnd,
        };
    });
