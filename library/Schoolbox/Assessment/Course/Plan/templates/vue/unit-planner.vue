<script setup lang="ts">
import OverviewComponent from "@schoolboxCurriculum/vue/overviewComponent/overview-component.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { Unit } from "./unitPlannerTypes";
import ContentContainer from "./content-container.vue";
import RubricCapabilities from "./rubric-capabilities.vue";
import CurriculumCodesList from "./curriculum-codes-list.vue";
import TextboxContent from "./textbox-content.vue";
import ActivitiesList from "./activities-list.vue";

interface Props {
    units: Unit[];
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();

const selectedTab = window.Vue.ref("all");
const tabs = window.Vue.ref([
    { id: "all", label: "All" },
    { id: "overview", label: "Overview" },
    { id: "activities", label: "Activities" },
    { id: "rubric-capabilities", label: "Rubric Capabilities" },
    { id: "curriculum-overview", label: "Curriculum Overview" },
]);

type TextboxKey = keyof Unit["textboxes"];

const allTextboxKeys = window.Vue.computed(() => {
    const keysSet = new Set<TextboxKey>();
    props.units.forEach((unit) => {
        Object.keys(unit.textboxes).forEach((key) => {
            if (key === "extras") return;
            keysSet.add(key);
        });
    });
    return [...keysSet];
});

const totalExtras = window.Vue.computed(() => {
    let total = 0;
    props.units.forEach((unit) => {
        total += unit.extraTextboxes;
    });
    return total;
});

window.Vue.onMounted(() => {
    store.init(props.units);
});
</script>

<template>
    <ul class="sub-nav">
        <li v-for="tab in tabs" :key="tab.id" :class="{ active: selectedTab === tab.id }">
            <a @click="selectedTab = tab.id">{{ tab.label }}</a>
        </li>
    </ul>

    <div class="scrollable">
        <table>
            <thead>
                <tr>
                    <th>Unit</th>
                    <template v-if="selectedTab === 'all' || selectedTab === 'overview'">
                        <th v-for="textboxKey in allTextboxKeys" :key="textboxKey">
                            {{ textboxKey }}
                        </th>
                        <th v-if="totalExtras > 0">Extra Textboxes</th>
                    </template>
                    <th v-if="selectedTab === 'all' || selectedTab === 'activities'">Activities</th>
                    <th v-if="selectedTab === 'all' || selectedTab === 'rubric-capabilities'">Rubric Capabilities</th>
                    <th v-if="selectedTab === 'all' || selectedTab === 'curriculum-overview'">Curriculum Overview</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="unit in store.units"
                    :key="unit.id"
                    :class="{ collapsed: !store.expandedUnits.includes(unit.id) }"
                >
                    <td>
                        <a :href="unit.homepage" target="_blank">
                            <strong>{{ unit.title }}</strong>
                        </a>
                    </td>
                    <template v-if="selectedTab === 'all' || selectedTab === 'overview'">
                        <td v-for="(textbox, textboxKey) in unit.textboxes" :key="textboxKey">
                            <content-container :unit-id="unit.id">
                                <textbox-content v-if="textbox !== null" :textbox="textbox"></textbox-content>
                            </content-container>
                        </td>
                        <td v-if="totalExtras > 0">
                            <template v-if="unit.extraTextboxes > 0">
                                <div class="extras-content">
                                    <p>
                                        {{ unit.extraTextboxes }} more textbox{{
                                            unit.extraTextboxes === 1 ? "" : "es"
                                        }}
                                    </p>
                                </div>
                            </template>
                        </td>
                    </template>
                    <td v-if="selectedTab === 'all' || selectedTab === 'activities'">
                        <activities-list :activities="unit.activities" :unit-id="unit.id"></activities-list>
                    </td>
                    <td v-if="selectedTab === 'all' || selectedTab === 'rubric-capabilities'">
                        <content-container :unit-id="unit.id">
                            <rubric-capabilities :rubric-capabilities="unit.rubricCapabilities"></rubric-capabilities>
                        </content-container>
                    </td>
                    <td
                        v-if="selectedTab === 'all' || selectedTab === 'curriculum-overview'"
                        class="curriculum-codes-container"
                    >
                        <content-container :unit-id="unit.id">
                            <curriculum-codes-list :curriculum-codes="unit.curriculumCodes"></curriculum-codes-list>
                        </content-container>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <overview-component></overview-component>
</template>

<style scoped>
table th:first-child {
    min-width: 200px;
    width: 200px;
}

table th:not(:first-child) {
    min-width: 400px;
    width: 400px;
}

table tbody td:not(:first-child) {
    padding: 0px;
}

table tbody tr {
    height: 300px;
}

.extras-content {
    padding: 5px;
}
.curriculum-codes-container {
    max-width: 500px;
}
</style>
