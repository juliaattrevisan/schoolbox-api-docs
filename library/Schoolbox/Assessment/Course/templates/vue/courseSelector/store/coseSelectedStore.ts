/**
 * To manage the list of currently selected courses
 */

import { defineStore } from "pinia";
import type { Course } from "@schoolboxLibrary/types/Course";

export const useSelectedStore = defineStore("courseSelector", () => {
    const selected = window.Vue.ref<Course[]>([]); // the courses to display in the report

    /**
     * store any passed through properties
     * @param toAdd - the course or courses to add to the selected list
     */
    function add(toAdd: Course | Course[]): void {
        if (Array.isArray(toAdd)) {
            selected.value = [...selected.value, ...toAdd];
        } else {
            selected.value = [...selected.value, toAdd];
        }
    }

    /**
     * remove the course or courses from the selected list
     * @param toRemove - the course or courses to remove from the selected list
     */
    function remove(toRemove: Course | Course[]): void {
        if (Array.isArray(toRemove)) {
            toRemove.forEach((courseToRemove) => {
                selected.value = selected.value.filter((course) => course.id !== courseToRemove.id);
            });
        } else {
            selected.value = selected.value.filter((course) => course.id !== toRemove.id);
        }
    }

    /**
     * to switch a courses selection status
     * @param course - the course to switch the selection status of
     * @param isChecked - whether the course is checked or not
     */
    function switchCourse(course: Course, isChecked: boolean): void {
        if (isChecked) {
            add(course);
        } else {
            remove(course);
        }
    }

    /**
     * completely update the courses with a new array
     * @param updated - the new array of courses
     */
    function update(updated: Course[]): void {
        selected.value = [...updated];
    }

    /**
     * clear all the currently selected courses
     */
    function empty(): void {
        selected.value = [];
    }

    return {
        add,
        remove,
        switchCourse,
        update,
        empty,
    };
});
