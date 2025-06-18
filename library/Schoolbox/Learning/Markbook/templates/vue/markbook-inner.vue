<script setup lang="ts">
import { useMarkbookStore } from "./store/markbookStore";

interface Props {
    type: "course" | "class";
    endpoint: string;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const showDisplayMarks = window.Vue.ref(false);
const showDrafts = window.Vue.ref(false);

const showAverages = window.Vue.ref(false);

/**
 * If the markbook has more than this number of participants,
 * reduce functionality to improve browser performance (SA-5714)
 */
const functionDegradeLimit = 80;
const isDegraded = window.Vue.computed(() => {
    if (mbStore.data === null) {
        return 0;
    }

    const students = mbStore.data.classImpls.reduce((acc, impl) => acc + Object.keys(impl.students).length, 0);
    return students > functionDegradeLimit;
});

window.Vue.onMounted(async () => {
    await mbStore.init(props.endpoint);
});
</script>

<template>
    <div v-if="mbStore.data === null">... loading (todo: add a better spinner or skeleton here)</div>
    <div
        v-else
        :class="{
            'actions-small-1': mbStore.data._routes.exportReport !== null,
        }"
    >
        <div v-if="mbStore.data.classImpls.length === 0" class="empty-state">
            <i class="icon-curriculum"></i>
            <p>
                This course has not yet been imported to any classes or groups.
                <a
                    v-if="mbStore.data._routes['courseImport.getImport']"
                    :href="mbStore.data._routes['courseImport.getImport']"
                >
                    Import this course </a
                >.
            </p>
        </div>
        <div class="legend">
            <div class="indicators">
                <p class="meta"><span class="legend-indicator indicator-none"></span>Unmarked</p>
                <p class="meta"><span class="legend-indicator indicator-pending"></span>Unpublished draft</p>
                <p class="meta"><span class="legend-indicator indicator-resubmit"></span>Resubmit</p>
                <p class="meta"><span class="legend-indicator indicator-published"></span>Mark Published</p>
            </div>
        </div>

        <div class="checklist">
            <input v-model="showDisplayMarks" type="checkbox" />
            <label for="show-display-marks" data-test="markbook-show-display-marks">
                Show Display Marks
                <span class="hint has-tip tip-top" title="Shows marks in the same format that students see">?</span>
            </label>
        </div>
        <div class="checklist">
            <input v-model="showDrafts" type="checkbox" />
            <label for="show-drafts">Show Drafts</label>
        </div>
        <template v-if="mbStore.data._routes.exportReport">
            <nav>
                <sbx-pop-out content-id="header-options"></sbx-pop-out>
            </nav>
            <ul id="header-options" class="f-dropdown" data-dropdown-content>
                <li class="separator">Report</li>
                <li>
                    <a :href="mbStore.data._routes.exportReport" class="icon-download"> Download Spreadsheet </a>
                </li>
            </ul>
        </template>
    </div>

    <div v-if="isDegraded">
        <div class="small-12 column list-item">
            <ul class="meta">
                <li class="icon-alert">
                    This markbook is showing a large number of students; for performance reasons, student portraits and
                    marking of tasks have been disabled.
                </li>
            </ul>
        </div>
    </div>

    <div v-if="mbStore.data" class="diagonal-headings-table-container small-12 island">
        <div v-if="Object.keys(mbStore.data.assessments).length === 0" class="empty-state">
            <i class="icon-curriculum"></i>
            <p>
                There are no activities that match the selected filters. Try altering the filters to broaden your
                search.
            </p>
        </div>
        <table v-else class="diagonal-headings-table marksheet">
            <thead>
                <tr class="rotate-headers">
                    <th class="fixed-left"><div></div></th>
                    <th class="fixed-left">
                        <div><span>Student Weighted Average</span></div>
                    </th>
                    <th v-if="mbStore.data.rank !== null">
                        <div>
                            <span>{{ props.type === "class" ? "Class" : "Course" }} Ranking</span>
                        </div>
                    </th>
                    <th
                        v-for="activity in mbStore.data.assessments"
                        :key="activity.id"
                        :data-test="`markbook-assessment-${activity.id}`"
                    >
                        <div>
                            <!-- A teacher clicking an assessment from the markbook is most likely interested in seeing more granular results -->
                            <span>
                                <a href="{{ activity._routes.summary }}" title="{{ activity.title }}">
                                    {{ activity.title }}
                                </a>
                                <br />
                                <span class="meta">{{ activity.typeName }}</span>
                            </span>
                        </div>
                    </th>
                </tr>
                <template v-for="(classImpl, classImplId) in mbStore.data.classImpls" :key="classImplId">
                    <tr v-if="props.type === 'class' && classImpl.stats !== null" class="average-headers">
                        <th class="fixed-left white profile-cell average-expander">
                            <a
                                href="#"
                                class="icon-down-arrow"
                                title="Show additional metrics"
                                @click="showAverages = !showAverages"
                            ></a>
                            Class Average
                        </th>
                        <!-- todo: draftsOffClass/draftsOnClass -->
                        <th class="fixed-left student-grade-cell">
                            {{ showDrafts ? classImpl.stats.draftWeightedMean : classImpl.stats.weightedMean }}
                        </th>
                        <th v-if="mbStore.data.rank !== null">-</th>
                        <th
                            v-for="assessment in classImpl.assessments"
                            :key="assessment.id"
                            class="student-grade-cell white"
                        >
                            <span
                                v-if="
                                    showDrafts &&
                                    assessment.stats.draftMean &&
                                    Object.keys(assessment.hasDraft).length > 0
                                "
                                class="meta"
                            >
                                {{ assessment.stats.draftMean ?? "-" }}
                            </span>
                            <span v-else>
                                {{ assessment.stats.mean ?? "-" }}
                            </span>
                        </th>
                    </tr>
                </template>
                <!-- todo: course-headers -->
            </thead>
            <tbody>
                <!-- todo: course average (course markbook) -->
                <!-- todo: statistics (class markbook) -->
                <template v-for="(classImpl, classImplId) in mbStore.data.classImpls" :key="classImplId">
                    <!-- todo: visibility (class markbook) -->

                    <tr>
                        <th class="class-row class-row-heading fixed-left">
                            <a href="/homepage/{{ classImpl.folder.id }}">{{ classImpl.folder.title }}</a>
                        </th>
                        <th class="class-row fixed-left"></th>
                        <td v-if="mbStore.data.rank !== null"></td>
                        <template v-for="assessment in mbStore.data.assessments" :key="assessment.id">
                            <td v-if="classImpl.assessments[assessment.id]?.stats" class="class-row">
                                <a :href="classImpl.assessments[assessment.id]._routes['learningAssessment.getStatus']">
                                    <span
                                        v-if="
                                            showDrafts && classImpl.assessments[assessment.id].stats.draftMean !== null
                                        "
                                        class="meta"
                                    >
                                        {{ classImpl.assessments[assessment.id].stats.draftMean ?? "-" }}
                                    </span>
                                    <span v-else>
                                        {{ classImpl.assessments[assessment.id].stats.mean ?? "-" }}
                                    </span>
                                </a>
                            </td>
                            <td v-else class="class-row na"></td>
                        </template>
                    </tr>

                    <tr v-for="student in classImpl.students" :key="student.id" :data-student="student.id">
                        <!-- todo: students -->
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</template>

<style scoped></style>
