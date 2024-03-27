import type { Ref } from "vue";
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { ScrollToPlugin } from "gsap/all";
import { defineStore } from "pinia";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import Toast from "@schoolboxVue/components/toast.vue";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";

gsap.registerPlugin(Draggable);
gsap.registerPlugin(ScrollToPlugin);

interface DraggableActivity {
    activity: Activity;
    el: HTMLElement | null;
    sequence: number;
}

export const useActivitiesDragStore = (storeId: string) =>
    defineStore(`activitiesDragStore/${storeId}`, () => {
        const routeReorder = window.Vue.ref<string | null>(null);
        const draggables = window.Vue.ref<Record<number, DraggableActivity>>({});
        const toastEl = window.Vue.ref<typeof Toast>();
        const isUnitSortablePlaceholder = window.Vue.ref<boolean>(false);

        // Allow the refs to be passed in instead of creating and monitoring two
        let activitiesContainerEl: Ref<HTMLElement>;

        const isDragging = window.Vue.ref(false);
        const dragCurrentDraggable = window.Vue.ref<DraggableActivity | null>(null);
        const dragCurrentPointerY = window.Vue.ref<number | null>(null);
        const dragInitial = window.Vue.ref<{
            y: number;
            offsetY: number;
        } | null>(null);

        /**
         * store any passed through properties
         * @param { string|null } $routeReorder - the route to use when resequencing, if the user has access to do so
         * @param { Ref<HTMLElement> } $activitiesContainerEl - the ref to the activities container element
         * @param { Activity[] } $activities - the initial set of activities, in order
         * @param { typeof Toast } $toastEl - the toast element
         * @param { boolean } $isUnitSortablePlaceholder - whether this has been created from a unit sortable placeholder
         */
        function init(
            $routeReorder: string | null,
            $activitiesContainerEl: Ref<HTMLElement>,
            $activities: Activity[],
            $toastEl: typeof Toast,
            $isUnitSortablePlaceholder: boolean,
        ) {
            routeReorder.value = $routeReorder;
            activitiesContainerEl = $activitiesContainerEl;

            $activities.forEach((activity, index) => {
                draggables.value[activity.id] = {
                    activity,
                    el: null,
                    sequence: index,
                };
            });

            toastEl.value = $toastEl;
            isUnitSortablePlaceholder.value = $isUnitSortablePlaceholder;
        }

        /**
         * Assign an element to a draggable activity
         * @param { number } activityId - the activity id
         * @param { HTMLElement } el - the element to add
         */
        function setActivityEl(activityId: number, el: HTMLElement) {
            if (draggables.value[activityId] === undefined) {
                throw new Error(`Activity ${activityId} not found`);
            }
            draggables.value[activityId].el = el;
        }

        /**
         * De-assign an element from a draggable activity
         * @param { number } activityId - the activity id
         */
        function unsetActivityEl(activityId: number) {
            if (draggables.value[activityId] === undefined) {
                throw new Error(`Activity ${activityId} not found`);
            }
            draggables.value[activityId].el = null;
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
                Object.values(draggables.value)
                    .filter((d) => d.el !== null)
                    .map((d) => d.el)
                    .reduce((acc, draggableEl) => {
                        return acc + draggableEl!.clientHeight;
                    }, 0),
            );
            return Math.max(minDragHeight, Math.min(targetHeight, maxDragHeight));
        });

        const currentOrderedActivities = window.Vue.computed<Activity[]>(() => {
            return Object.values(draggables.value)
                .sort((a, b) => a.sequence - b.sequence)
                .map((d) => d.activity);
        });

        const orderedActivities = window.Vue.computed<Activity[]>(() => {
            // Show the current order if:
            // - This was created from a unit sortable placeholder (which doesn't allow reordering)
            // - or, the user is not dragging
            // - or, no current draggable is set
            // - or, the initial drag info is not set
            if (
                isUnitSortablePlaceholder.value ||
                !isDragging.value ||
                dragCurrentDraggable.value === null ||
                dragInitial.value === null
            ) {
                return currentOrderedActivities.value;
            }

            // Use reduce to find the index where the unit should be inserted
            const initialReduceVals = { cumulativeHeight: 0, foundDragIndex: 0, found: false };
            const reduceResult = currentOrderedActivities.value.reduce((acc, activity, index) => {
                if (acc.found) return acc; // Skip further processing if index is found

                const activityDraggable = draggables.value[activity.id];
                if (activityDraggable === undefined) {
                    throw new Error(`Activity ${activity.id} not found`);
                }
                if (activityDraggable.el === null) {
                    throw new Error(`Activity ${activity.id} has no draggable element`);
                }

                const newCumulativeHeight = acc.cumulativeHeight + activityDraggable.el.clientHeight;

                // If the new cumulative height is greater than the top of the drag placeholder (just above the mouse position),
                // then we've found the index
                if (newCumulativeHeight >= dragPlaceholderY.value && !acc.found) {
                    return { ...acc, foundDragIndex: index, found: true };
                }

                // Otherwise, continue to the next unit
                return { ...acc, cumulativeHeight: newCumulativeHeight, foundDragIndex: index + 1 };
            }, initialReduceVals);

            const adjustedDragIndex = Math.min(reduceResult.foundDragIndex, currentOrderedActivities.value.length - 1);

            // Reorder the units based on the new index
            const newOrderedActivities = [...currentOrderedActivities.value];
            const dragActivityIndex = currentOrderedActivities.value.findIndex(
                (u) => u.id === dragCurrentDraggable.value?.activity.id,
            );
            const [draggedActivity] = newOrderedActivities.splice(dragActivityIndex, 1);
            newOrderedActivities.splice(adjustedDragIndex, 0, draggedActivity);

            return newOrderedActivities;
        });

        /**
         * Save the sequence of the activities to the database
         */
        async function saveActivitySequence() {
            if (!routeReorder.value) {
                return; // User does not have access to resequence
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
            toastEl.value!.activate("Saving...");
            try {
                const formData = new FormData();
                orderedActivities.value.forEach((activity) => {
                    formData.append("id[]", activity.id.toString());
                });

                const response = await fetch(routeReorder.value, {
                    method: "POST",
                    body: formData,
                    headers: {
                        Accept: "application/json",
                    },
                });

                if (!response.ok) {
                    throw new Error(await extractErrorMessage(response));
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
                toastEl.value!.timeout("Save successful", true, 2000);
            } catch (error) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
                toastEl.value!.timeout(error, false, 2000);
            }
        }

        /**
         * Resequences and saves the new activity order
         * @param { number[] } activityIDs - the ids of the units to resequence
         */
        function resequenceActivities(activityIDs: number[]) {
            let hasChanged = false;

            activityIDs.forEach((activityID, index) => {
                const draggable = draggables.value[activityID];
                if (draggable === undefined) {
                    throw new Error(`Activity ${activityID} not found`);
                }

                if (draggable.sequence !== index) {
                    draggable.sequence = index;
                    hasChanged = true;
                }
            });

            if (hasChanged) {
                saveActivitySequence().catch(() => {
                    // noop
                });
            }
        }

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
            if (draggables.value[activity.id] === undefined) {
                throw new Error(`Activity ${activity.id} not found`);
            }

            const pointerEvent = draggable.pointerEvent as PointerEvent;
            const pointerTarget = pointerEvent.target as HTMLElement;

            dragCurrentPointerY.value = draggable.pointerY;
            dragCurrentDraggable.value = draggables.value[activity.id];
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

            resequenceActivities(orderedActivities.value.map((u) => u.id));

            isDragging.value = false;
            dragCurrentDraggable.value = null;
            dragCurrentPointerY.value = null;
            dragInitial.value = null;
        }

        return {
            init,
            routeReorder,
            draggables,
            orderedActivities,
            setActivityEl,
            unsetActivityEl,
            isDragging,
            dragCurrentDraggable,
            dragPlaceholderY,
            onDragStart,
            onDragMove,
            onDragEnd,
        };
    });
