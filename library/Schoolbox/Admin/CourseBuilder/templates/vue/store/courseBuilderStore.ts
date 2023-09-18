import { defineStore } from "pinia";
import Toast from "@schoolboxVue/components/toast.vue";
import { CourseTemplate, Permissions } from "../courseBuilderTypes";

export const useCourseBuilderStore = defineStore("courseBuilderStore", () => {
    const courseTemplates = window.Vue.ref<CourseTemplate[]>([]); // Current state of the course templates
    const coursePermissions = window.Vue.ref<Permissions>({});
    const unitPermissions = window.Vue.ref<Permissions>({});
    const courseInherit = window.Vue.ref<boolean>(false);
    const unitInherit = window.Vue.ref<boolean>(false);

    const saveIsProcessing = window.Vue.ref<boolean>(false);
    const saveStatus = window.Vue.ref<string>("Save Changes");
    const toastEl = window.Vue.ref<typeof Toast>(); // so we don't need to pass it all around as a prop

    /**
     * store any passed through properties
     * @param { CourseTemplate[] } $courseTemplates - the course templates
     * @param { Permissions } $coursePermissions - the course permissions
     * @param { Permissions } $unitPermissions - the unit permissions
     * @param { boolean } $courseInherit - the course inherit
     * @param { boolean } $unitInherit - the unit inherit
     * @param {typeof Toast} toastElement - the toast element
     */
    function init(
        $courseTemplates: CourseTemplate[],
        $coursePermissions: Permissions,
        $unitPermissions: Permissions,
        $courseInherit: boolean,
        $unitInherit: boolean,
        toastElement: typeof Toast
    ) {
        courseTemplates.value = $courseTemplates;
        coursePermissions.value = $coursePermissions;
        unitPermissions.value = $unitPermissions;
        courseInherit.value = $courseInherit;
        unitInherit.value = $unitInherit;
        toastEl.value = toastElement.value;
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

    /**
     * Send through the data to save the whole list and permissions
     * @returns { FormData } - the form data to send through
     */
    function getSaveAllFormData() {
        const formData = new FormData();
        formData.append("unitInherit", unitInherit.value ? "1" : "0");
        formData.append("courseInherit", courseInherit.value ? "1" : "0");
        formData.append("coursePermissions", JSON.stringify(coursePermissions.value));
        formData.append("unitPermissions", JSON.stringify(unitPermissions.value));
        formData.append("templates", JSON.stringify(courseTemplates.value));
        return formData;
    }

    /**
     * Saves all changes
     */
    async function saveChanges() {
        try {
            saveIsProcessing.value = true;
            saveStatus.value = "Saving...";

            toastEl.value.activate("Saving...");
            const response = await fetch("/adminv2/course-builder/save", {
                method: "POST",
                body: getSaveAllFormData(),
                headers: {
                    Accept: "application/json",
                },
            });
            if (response.ok) {
                toastEl.value.timeout("Save successful", true, 2000);

                // reload the page to get the new data
                window.location.reload();
            } else {
                const responseText = await response.text();
                toastEl.value.timeout(responseText, false, 2000);
            }
        } catch (error) {
            toastEl.value(error.message, false, 2000);
        } finally {
            await window.Vue.nextTick(() => {
                saveIsProcessing.value = false;
                saveStatus.value = "Save Changes";
            });
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
        saveChanges,
    };
});
