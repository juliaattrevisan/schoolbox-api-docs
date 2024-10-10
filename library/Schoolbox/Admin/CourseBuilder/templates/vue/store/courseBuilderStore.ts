import { defineStore } from "pinia";
import * as toast from "@schoolboxVue/components/toast/sbxToastEvent";
import type { CourseTemplate, Permissions } from "../courseBuilderTypes";

export const useCourseBuilderStore = defineStore("courseBuilderStore", () => {
    const courseTemplates = window.Vue.ref<CourseTemplate[]>([]); // Current state of the course templates
    const coursePermissions = window.Vue.ref<Permissions>({});
    const unitPermissions = window.Vue.ref<Permissions>({});
    const courseInherit = window.Vue.ref<boolean>(false);
    const unitInherit = window.Vue.ref<boolean>(false);

    const courseTemplateToDelete = window.Vue.ref<CourseTemplate | null>(null); // The course template to delete, when set - the delete modal will open

    const saveIsProcessing = window.Vue.ref<boolean>(false);
    const saveStatus = window.Vue.ref<string>("Save Changes");

    /**
     * store any passed through properties
     * @param $courseTemplates - the course templates
     * @param $coursePermissions - the course permissions
     * @param $unitPermissions - the unit permissions
     * @param $courseInherit - the course inherit
     * @param $unitInherit - the unit inherit
     */
    function init(
        $courseTemplates: CourseTemplate[],
        $coursePermissions: Permissions,
        $unitPermissions: Permissions,
        $courseInherit: boolean,
        $unitInherit: boolean,
    ): void {
        courseTemplates.value = $courseTemplates;
        coursePermissions.value = $coursePermissions;
        unitPermissions.value = $unitPermissions;
        courseInherit.value = $courseInherit;
        unitInherit.value = $unitInherit;
    }

    /**
     * Updates a specific course template in the store
     * @param updatedTemplate - the updated course template
     */
    function updateCourseTemplate(updatedTemplate: CourseTemplate): void {
        const index = courseTemplates.value.findIndex((ct) => ct.id === updatedTemplate.id);
        if (index !== -1) {
            courseTemplates.value[index] = { ...updatedTemplate };
        }
    }

    /**
     * Deletes the currently selected course template
     * @param courseTemplate - the course template to delete
     */
    async function deleteCourseTemplate(courseTemplate: CourseTemplate): Promise<void> {
        if (courseTemplate.id >= 0) {
            toast.activate("Deleting...");
            const response = await fetch(`/adminv2/course-builder/delete/${courseTemplate.id}`, { method: "DELETE" });

            if (!response.ok) {
                let responseText = await response.text();

                // If the response is JSON, then parse it correctly for the response text
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    const responseJSON = JSON.parse(responseText) as { title: string; type: string; status: number };
                    responseText = responseJSON.title;
                }

                toast.timeout({ text: responseText, isSuccess: false });
                return;
            }

            toast.timeout({ text: "Deleted", isSuccess: true });
        }

        const index = courseTemplates.value.findIndex((ct) => ct.id === courseTemplate.id);
        if (index !== -1) {
            courseTemplates.value.splice(index, 1);
        }

        courseTemplateToDelete.value = null;
    }

    /**
     * Send through the data to save the whole list and permissions
     * @returns - the form data to send through
     */
    function getSaveAllFormData(): FormData {
        const formData = new FormData();
        formData.append("templates", JSON.stringify(courseTemplates.value));
        formData.append("unitInherit", unitInherit.value ? "1" : "0");
        formData.append("courseInherit", courseInherit.value ? "1" : "0");

        // Remove any permissions that are -1 (i.e. not set)
        formData.append(
            "coursePermissions",
            JSON.stringify(
                Object.fromEntries(Object.entries(coursePermissions.value).filter((entry) => entry[1] >= 0)),
            ),
        );

        formData.append(
            "unitPermissions",
            JSON.stringify(Object.fromEntries(Object.entries(unitPermissions.value).filter((entry) => entry[1] >= 0))),
        );

        return formData;
    }

    /**
     * Saves all changes
     */
    async function saveChanges(): Promise<void> {
        try {
            // Ensure all course template names are not empty, otherwise set .hasNameValidationError on the courseTemplate
            let allTemplatesHaveNames = true;
            courseTemplates.value.forEach((ct) => {
                if (!ct.name) {
                    ct.nameHasValidationError = true;
                    allTemplatesHaveNames = false;
                }
            });

            if (!allTemplatesHaveNames) {
                return;
            }

            saveIsProcessing.value = true;
            saveStatus.value = "Saving...";

            toast.activate("Saving...");
            const response = await fetch("/adminv2/course-builder/save", {
                method: "POST",
                body: getSaveAllFormData(),
                headers: {
                    Accept: "application/json",
                },
            });
            if (response.ok) {
                toast.timeout({ text: "Save successful", isSuccess: true });

                // reload the page to get the new data
                window.location.reload();
            } else {
                const responseText = (await response.json()) as { title: string; type: string; status: number };
                toast.timeout({ text: responseText.title, isSuccess: false });
            }
        } catch (error) {
            toast.timeout({ text: (error as Error).message, isSuccess: false });
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
        courseTemplateToDelete,
        updateCourseTemplate,
        deleteCourseTemplate,
        saveChanges,
    };
});
