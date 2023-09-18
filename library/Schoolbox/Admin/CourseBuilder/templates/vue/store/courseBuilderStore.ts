import { defineStore } from "pinia";
import { CourseTemplate } from "../courseBuilderTypes";

export const useCourseBuilderStore = defineStore("courseBuilderStore", () => {
    const courseTemplates = window.Vue.ref<CourseTemplate[]>([]); // Current state of the course templates

    /**
     * store any passed through properties
     * @param { CourseTemplate[] } $courseTemplates - the course templates
     */
    function init($courseTemplates: CourseTemplate[]) {
        courseTemplates.value = $courseTemplates;
    }

    /**
     * Updates a specific course template in the store
     * @param { CourseTemplate } updatedTemplate - the updated course template
     */
    function updateCourseTemplate(updatedTemplate: CourseTemplate) {
        const index = courseTemplates.value.findIndex((ct) => ct.id === updatedTemplate.id);
        if (index !== -1) {
            courseTemplates.value[index] = { ...updatedTemplate };
        }
    }

    /**
     * Deletes a specific course template in the store
     * @param { CourseTemplate } courseTemplate - the course template to delete
     */
    function deleteCourseTemplate(courseTemplate: CourseTemplate) {
        const index = courseTemplates.value.findIndex((ct) => ct.id === courseTemplate.id);
        if (index !== -1) {
            courseTemplates.value.splice(index, 1);
        }
    }

    return {
        init,
        courseTemplates,
        updateCourseTemplate,
        deleteCourseTemplate,
    };
});
