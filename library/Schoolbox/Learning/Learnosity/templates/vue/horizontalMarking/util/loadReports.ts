/**
 * To load the reports data and pass it to the other stores
 */

import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import { addTargetsToMarks, disableTextAreas } from "@schoolboxLearnosity/js/util/domUtils";
import type { LearnosityReportInitOptions } from "@schoolboxLearnosity/vue/learnosityReportTypes";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";

/**
 * store any passed through properties
 * @param {string} reportPayloads - the report payload
 */
export function loadReports(reportPayloads: string[]) {
    let reportsLoaded = 0;
    for (let i = 0; i < reportPayloads.length; i += 1) {
        const initOptions = JSON.parse(reportPayloads[i]) as LearnosityReportInitOptions;
        // To link the report to the student
        const reportId = initOptions.request.reports[0].id;
        /**
         * Every report gets its own callback; this is because the readyListeners()
         * don't have any idea which report they are in, which means we can't tell
         * when a report has been fully loaded to the point where we can hide all
         * their divs, because we only want to show one div at a time per report.
         */
        const reportCallback = {
            // eslint-disable-next-line @typescript-eslint/no-loop-func
            readyListener: () => {
                // @ts-expect-error -- reportsApp is global but untyped, this should be investigated at some point to determine if we can get this from learnosity
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-use-before-define
                const attemptReport = (reportsApp as unknown).getReport(reportId);
                // itemsApi only spawns if you used session-detail-by-item
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
                attemptReport.on("ready:itemsApi", (itemsApp: unknown) => {
                    // Load the items for the student
                    // @ts-expect-error -- itemsApp is global but untyped, this should be investigated at some point to determine if we can get this from learnosity
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                    itemsApp.getItems(($items: object) => {
                        reportsLoaded += 1;
                        const items: StudentItem[] = [];
                        // grab each item object and push it into the items array
                        Object.values($items).forEach((item: StudentItem) => {
                            items.push(item);
                        });
                        useStudentsStore().addItems(reportId, items);
                        addTargetsToMarks(reportId);
                        disableTextAreas(reportId);
                        // add the questions to the items, as they aren't included in the initial payload
                        useItemsStore().addQuestions(items, reportsLoaded === reportPayloads.length);
                    });
                    // to notify the report has loaded
                    useStudentsStore().reportLoaded(reportId);
                });
            },
        };

        // Initialise reports for each attempt
        // @ts-expect-error -- LearnosityReports is global but untyped, this should be investigated at some point to determine if we can get this from learnosity
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call
        let reportsApp = (LearnosityReports as unknown).init(initOptions, reportCallback);
    }
}
