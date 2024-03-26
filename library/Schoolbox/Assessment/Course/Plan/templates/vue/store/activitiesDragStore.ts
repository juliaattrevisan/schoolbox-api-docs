import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { ScrollToPlugin } from "gsap/all";
import { defineStore } from "pinia";
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

        const isDragging = window.Vue.ref(false);

        const dragOrderedActivities = window.Vue.computed<Activity[]>(() => {
            const activitiesWithEl = Object.values(draggables.value).filter((d) => d.el !== null);

            // TODO LOGIC
            const newSequence = activitiesWithEl.map((d) => d.sequence);

            return newSequence.map((sequence) => {
                return activitiesWithEl.find((d) => d.sequence === sequence)!.activity;
            });
        });

        const orderedActivities = window.Vue.computed<Activity[]>(() => {
            if (isDragging.value) {
                return dragOrderedActivities.value;
            }

            return Object.values(draggables.value)
                .sort((a, b) => a.sequence - b.sequence)
                .map((d) => d.activity);
        });

        /**
         * store any passed through properties
         * @param { string|null } $routeReorder - the route to use when resequencing, if the user has access to do so
         * @param { Activity[] } $activities - the initial set of activities, in order
         */
        function init($routeReorder: string | null, $activities: Activity[]) {
            routeReorder.value = $routeReorder;

            $activities.forEach((activity, index) => {
                draggables.value[activity.id] = {
                    activity,
                    el: null,
                    sequence: index,
                };
            });
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

        return {
            init,
            routeReorder,
            draggables,
            dragOrderedActivities,
            orderedActivities,
            setActivityEl,
            unsetActivityEl,
            isDragging,
        };
    });
