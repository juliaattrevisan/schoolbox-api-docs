import { defineStore } from "pinia";
import { CourseTemplate, Permissions } from "../courseBuilderTypes";

export const useCourseBuilderStore = defineStore("courseBuilderStore", () => {
    const courseTemplates = window.Vue.ref<CourseTemplate[]>([]); // Current state of the course templates
    const coursePermissions = window.Vue.ref<Permissions>({});
    const unitPermissions = window.Vue.ref<Permissions>({});
    const courseInherit = window.Vue.ref<boolean>(false);
    const unitInherit = window.Vue.ref<boolean>(false);

    /**
     * store any passed through properties
     * @param { CourseTemplate[] } $courseTemplates - the course templates
     * @param { Permissions } $coursePermissions - the course permissions
     * @param { Permissions } $unitPermissions - the unit permissions
     * @param { boolean } $courseInherit - the course inherit
     * @param { boolean } $unitInherit - the unit inherit
     */
    function init(
        $courseTemplates: CourseTemplate[],
        $coursePermissions: Permissions,
        $unitPermissions: Permissions,
        $courseInherit: boolean,
        $unitInherit: boolean
    ) {
        courseTemplates.value = $courseTemplates;
        coursePermissions.value = $coursePermissions;
        unitPermissions.value = $unitPermissions;
        courseInherit.value = $courseInherit;
        unitInherit.value = $unitInherit;
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
        coursePermissions,
        unitPermissions,
        courseInherit,
        unitInherit,
        updateCourseTemplate,
        deleteCourseTemplate,
    };
});
