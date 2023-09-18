<script setup lang="ts">
import { useCourseBuilderStore } from "./store/courseBuilderStore";
import { PageTemplate, CourseTemplate } from "./courseBuilderTypes";
import CourseBuilderRows from "./course-builder-rows.vue";

interface Props {
    courseTemplates: CourseTemplate[];
    availableTemplates: PageTemplate[];
}

const props = defineProps<Props>();

const store = useCourseBuilderStore();

window.Vue.onMounted(() => {
    store.init(props.courseTemplates);
});

const newRowAmount = window.Vue.ref(0);

/**
 * Add a new row to the list
 */
function onAddNewRow() {
    newRowAmount.value += 1;
    store.courseTemplates.push({
        id: -newRowAmount.value, // New rows are set with an ever increasing negative increment to mark them as new for the back end
        name: "",
        courseTemplate: props.availableTemplates[0],
        unitTemplate: props.availableTemplates[0],
    });
}
</script>

<template>
    <section>
        <div class="list-container">
            <header class="list-header actions-small-1">
                <div class="list-item">
                    <label class="small-12 medium-1 column">ID</label>
                    <label class="small-12 medium-5 column required">Template Name</label>
                    <label class="small-12 medium-3 column required">Selected Course Page Template</label>
                    <label class="small-12 medium-3 column required">Selected Unit Page Template</label>
                </div>
            </header>
            <course-builder-rows :available-templates="props.availableTemplates" />
            <div class="component-action">
                <section>
                    <a class="button" @click.prevent="onAddNewRow">Add New Template</a>
                </section>
            </div>
        </div>
    </section>
    <table>
        <tr>
            <th>ID</th>
            <th class="required">Template Name</th>
            <th class="required">Selected Course Page Template</th>
            <th class="required">Selected Unit Page Template</th>
            <th>Delete</th>
        </tr>
        <tr v-for="courseTemplate in store.courseTemplates" :key="courseTemplate.id">
            <td>{{ courseTemplate.id }}</td>
            <td>{{ courseTemplate.name }}</td>
            <td>{{ courseTemplate.courseTemplate.name }}</td>
            <td>{{ courseTemplate.unitTemplate.name }}</td>
            <td><a href="#" class="icon-delete" title="Delete Row"></a></td>
        </tr>
        <!-- <tr>
            <td>NEW</td>
            <td><input></td>
            <td>
                <select name="courseTemplate">
                    {% for template in availableTemplates %}
                        <option value="{{ template.id }}">{{ template.name }}</option>
                    {% endfor %}
                </select>
            </td>
            <td>
                <select name="unitTemplate">
                    {% for template in availableTemplates %}
                        <option value="{{ template.id }}">{{ template.name }}</option>
                    {% endfor %}
                </select>
            </td>
            <td>Save?</td>
        </tr> -->
    </table>
</template>

<style scoped>
.list-item-container {
    transition: opacity 0.3s ease-out;
}

.delete-row-button {
    width: 40px;
    height: 40px;
    margin: 0px 8px 0px 0px;
}
</style>
