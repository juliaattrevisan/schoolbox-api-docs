/**
 * Hold the list of selected courses
 */

import { defineStore } from "pinia";
import type { Course } from "@schoolboxLibrary/types/Course";

export const useCoursesStore = defineStore("ccCourses", () => {
    const courses = window.Vue.ref<Course[]>([]); // the courses to display in the report
    const hasMultipleCourses = window.Vue.computed(() => courses.value.length > 1); // don't need to show a total for at the end of the unit if there's only one course

    /**
     * The total number of columns to display in the report
     * @returns - the total number of columns
     */
    const totalColumnCount = window.Vue.computed(() => {
        let total = 2; // start with 2 for the node name and total
        if (hasMultipleCourses.value) {
            // when there are multiple courses, we need to add the total column
            total += courses.value.reduce((acc, course) => {
                // add 1 for the course name and 1 for the total, then the number of units
                return acc + 1 + 1 + (course.units === undefined ? 0 : course.units.length);
            }, 0);
        } else {
            // add 1 for the course name, then the number of units
            total += 1 + (courses.value[0].units === undefined ? 0 : courses.value[0].units.length);
        }
        return total;
    });

    /**
     * Set the courses
     * @param newCourses - the new courses to set
     */
    function setCourses(newCourses: Course[]): void {
        courses.value = newCourses;
    }

    /**
     * Add courses to the existing courses
     * @param newCourses - the new courses to add
     */
    function addCourses(newCourses: Course[]): void {
        courses.value = [...courses.value, ...newCourses];
    }

    return {
        courses,
        hasMultipleCourses,
        totalColumnCount,
        setCourses,
        addCourses,
    };
});
