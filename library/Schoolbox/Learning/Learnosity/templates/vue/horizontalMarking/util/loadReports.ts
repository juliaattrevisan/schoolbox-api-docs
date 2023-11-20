/**
 * To load the reports data and pass it to the other stores
 */

import type { Item } from "@schoolboxLearnosity/js/types/Item";
import { addTargetsToMarks } from "@schoolboxLearnosity/js/util/domUtils";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";

/**
 * store any passed through properties
 * @param {string} reportPayloads - the report payload
 */
export function loadReports(reportPayloads: string[]) {
    for (let i = 0; i < reportPayloads.length; i += 1) {
        const initOptions = JSON.parse(reportPayloads[i]);
        // To link the report to the student
        const reportId = initOptions.request.reports[0].id as string;
        /**
         * Every report gets its own callback; this is because the readyListeners()
         * don't have any idea which report they are in, which means we can't tell
         * when a report has been fully loaded to the point where we can hide all
         * their divs, because we only want to show one div at a time per report.
         */
        const reportCallback = {
            readyListener: () => {
                const attemptReport = reportsApp.getReport(reportId);
                // itemsApi only spawns if you used session-detail-by-item
                attemptReport.on("ready:itemsApi", (itemsApp) => {
                    // Load the items for the student
                    itemsApp.getItems(($items: object) => {
                        const items: Item[] = [];
                        // grab each item object and push it into the items array
                        Object.values($items).forEach((item: Item) => {
                            items.push(item);
                        });
                        useStudentsStore().addItems(reportId, items);
                        addTargetsToMarks(reportId);
                        // add the questions to the items, as they aren't included in the initial payload
                        useItemsStore().addQuestions(items);
                    });
                    // to notify the report has loaded
                    useStudentsStore().reportLoaded(reportId);
                });
            },
        };
        // Initialise reports for each attempt
        let reportsApp = LearnosityReports.init(initOptions, reportCallback);
    }
}
