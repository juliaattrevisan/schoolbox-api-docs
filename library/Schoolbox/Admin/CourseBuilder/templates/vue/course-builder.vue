<script setup lang="ts">
import { useCourseBuilderStore } from "./store/courseBuilderStore";
import type { PageTemplate, CourseTemplate, Role, Permissions } from "./courseBuilderTypes";
import CourseBuilderRows from "./course-builder-rows.vue";
import CourseBuilderAccessCourse from "./course-builder-access-course.vue";
import CourseBuilderAccessUnit from "./course-builder-access-unit.vue";
import CourseBuilderDeleteModal from "./course-builder-delete-modal.vue";

interface Props {
    courseTemplates: CourseTemplate[];
    availableTemplates: PageTemplate[];
    coursePermissions: Permissions;
    unitPermissions: Permissions;
    courseInherit: boolean;
    unitInherit: boolean;
    roles: Role[];
}

const props = defineProps<Props>();

const store = useCourseBuilderStore();

const newRowAmount = window.Vue.ref(0);

/**
 * Add a new row to the list
 */
function onAddNewRow(): void {
    const firstValidTemplate = props.availableTemplates.filter((template) => !template.deleted)[0];

    newRowAmount.value += 1;
    store.courseTemplates.push({
        id: -newRowAmount.value, // New rows are set with an ever increasing negative increment to mark them as new for the back end
        name: "",
        courseTemplate: firstValidTemplate,
        unitTemplate: firstValidTemplate,
    });
}

/**
 * Save the changes to the course builder
 */
async function saveChanges(): Promise<void> {
    await store.saveChanges();
}

window.Vue.onMounted(() => {
    store.init(
        props.courseTemplates,
        props.coursePermissions,
        props.unitPermissions,
        props.courseInherit,
        props.unitInherit,
    );
});
</script>

<template>
    <form action="/adminv2/course-builder/save" method="post" novalidate @submit.prevent="saveChanges">
        <div class="small-12 island">
            <h2 class="subheader">Course Framework</h2>
            <section>
                <div class="list-container">
                    <header class="list-header actions-small-1">
                        <div class="list-item">
                            <label class="small-12 medium-6 column required">Framework Name</label>
                            <label class="small-12 medium-3 column required">Course Page Template</label>
                            <label class="small-12 medium-3 column required">Unit Page Template</label>
                        </div>
                    </header>
                    <course-builder-rows :available-templates="props.availableTemplates" />
                    <div class="component-action">
                        <section>
                            <a class="button" @click.prevent="onAddNewRow">Add Framework</a>
                        </section>
                    </div>
                </div>
            </section>
        </div>

        <div class="small-12 medium-6 island">
            <h2 class="subheader">Course Page Permissions</h2>
            <section>
                <course-builder-access-course :roles="props.roles" />
            </section>
        </div>
        <div class="small-12 medium-6 island">
            <h2 class="subheader">Unit page Permissions</h2>
            <section>
                <course-builder-access-unit :roles="props.roles" />
            </section>
        </div>
        <div class="small-12 column">
            <button class="submit" type="submit">Save Changes</button>
        </div>
    </form>

    <course-builder-delete-modal />
</template>

<style scoped></style>
