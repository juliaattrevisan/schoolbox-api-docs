/**
 * To generate a report of the curriculum coverage
 */

import { defineStore } from "pinia";
import { usePrintDialog } from "@schoolboxVue/composables/print/usePrintDialog";
import { isAbortError } from "@js/util/errorUtils";
import type {
    CurriculumCoveragePayload,
    ReportError,
    CurriculumCoverageNode,
} from "../../js/types/curriculumCoverageTypes";
import { useQueryStore } from "./ccQueryStore";
import { useInputStore } from "./ccInputStore";
import { useCoursesStore } from "./ccCoursesStore";

export const useReportStore = defineStore("ccReport", () => {
    const hasNotReported = window.Vue.ref(true); // to prompt a search if the user hasn't searched yet
    const isFetching = window.Vue.ref(false);
    const isLoading = window.Vue.ref(false);
    const hasLoadError = window.Vue.ref(false);
    const loadError = window.Vue.ref<ReportError | null>(null);
    const nodes = window.Vue.ref<CurriculumCoverageNode[]>([]); // the nodes to display in the report
    const hasReport = window.Vue.ref<boolean>(false); // whether the report has been generated
    let controller: AbortController; // to allow aborting the previous search if a new one is started
    const { isPrinting } = usePrintDialog();

    /**
     * Whether a search can be performed, can be if there's a change of course or node selection
     * @returns - whether a new report can be requested
     */
    const canReport = window.Vue.computed(() => {
        return useInputStore().canReport;
    });

    /**
     * Loop through and add the new course to the existing node.usage.courses[]
     * @param existingNode - the existing node to update
     * @param newNode - the new node that contains the new course to add
     */
    function addCourseToChildren(existingNode: CurriculumCoverageNode, newNode: CurriculumCoverageNode): void {
        if (existingNode.children) {
            existingNode.children.forEach((child, index) => {
                child.usage.courses = [...child.usage.courses, ...newNode.children![index].usage.courses];
                addCourseToChildren(child, newNode.children![index]);
            });
        }
    }

    /**
     * Load the course/node data for a single course
     * @param url - the url to load, linked to the single course and all curriculum
     * @param currentIndex - the current load index
     */
    async function getCourse(url: string, currentIndex: number): Promise<void> {
        try {
            const response = await fetch(url, {
                method: "GET",
                signal: controller.signal,
                headers: {
                    Accept: "application/json",
                },
            });
            if (!response.ok) {
                hasLoadError.value = true;
                loadError.value = {
                    title: "An error occurred",
                    type: "error",
                    status: response.status,
                };
                return;
            }
            const responseJson = (await response.json()) as CurriculumCoveragePayload | ReportError;
            if (typeof responseJson !== "object" || responseJson === null) {
                hasLoadError.value = true;
                return;
            }
            if ("status" in responseJson) {
                hasLoadError.value = true;
                loadError.value = responseJson;
                return;
            }
            if ("data" in responseJson) {
                const coursesStore = useCoursesStore();
                // if it's the first course load, we can just set the courses and nodes
                if (currentIndex === 0) {
                    coursesStore.setCourses(responseJson.data.courses);
                    nodes.value = responseJson.data.nodes;
                } else {
                    // otherwise we need to add the new courses to the existing courses
                    coursesStore.addCourses(responseJson.data.courses);
                    // and we also need to run through nodes and add the new course within each node.usage.courses[]
                    // then if the node has children, we need to do the same for the children within
                    const newCourseNodes = responseJson.data.nodes;
                    nodes.value.forEach((node, index) => {
                        node.usage.courses = [...node.usage.courses, ...newCourseNodes[index].usage.courses];
                        addCourseToChildren(node, newCourseNodes[index]);
                    });
                }
            }
        } catch (error) {
            // ignore abort errors, as the previous report was cancelled
            if (isAbortError(error)) {
                return;
            }
            console.error(error);
            hasLoadError.value = true;
        }
    }

    /**
     * In an attempt to reduce server load,
     * the report is generated sequentially for each course
     */
    async function report(): Promise<void> {
        useInputStore().setActive();
        useQueryStore().setQueryParams();
        hasNotReported.value = false;
        isLoading.value = true;
        hasLoadError.value = false;
        controller = new AbortController();
        // abort the previous search if it's still running
        if (isFetching.value) {
            controller.abort();
        }
        try {
            isFetching.value = true;
            await useQueryStore()
                .getQueryUrls()
                .reduce(async (previousPromise, url, currentIndex) => {
                    await previousPromise;
                    if (!hasLoadError.value) {
                        await getCourse(url, currentIndex);
                    }
                }, Promise.resolve());
            hasReport.value = true;
        } catch (error) {
            console.error(error);
            hasLoadError.value = true;
        } finally {
            isFetching.value = false;
            isLoading.value = false;
        }
    }

    return {
        hasNotReported,
        isLoading,
        hasLoadError,
        report,
        nodes,
        hasReport,
        canReport,
        isPrinting,
    };
});
