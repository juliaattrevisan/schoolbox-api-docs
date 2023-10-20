/**
 * To load the reports, currently we dont store anything here, its more to
 * load the reports data and pass it to the other stores
 */

import { defineStore } from "pinia";
import { useStudentsStore } from "./hmStudentsStore";
import { useItemsStore } from "./hmItemsStore";
import type { Question } from "../types/Question";

export const useReportsStore = defineStore("hmReports", () => {
    /**
     * store any passed through properties
     * @param {string} reportPayloads - the report payload
     */
    function init(reportPayloads: string[]) {
        // Lookups/indexes - a list of ids, and individualised report callbacks
        const reportIds = [];
        const reportCallbacks = [];

        for (let i = 0; i < reportPayloads.length; i += 1) {
            const initOptions = JSON.parse(reportPayloads[i]);

            // Remember all the report ids that we'll need to traverse
            const reportId = initOptions.request.reports[0].id;
            reportIds[i] = reportId;

            /**
             * Every report gets its own callback; this is because the readyListeners()
             * don't have any idea which report they are in, which means we can't tell
             * when a report has been fully loaded to the point where we can hide all
             * their divs, because we only want to show one div at a time per report.
             */
            reportCallbacks[i] = {
                readyListener: () => {
                    const attemptReport = reportsApp.getReport(reportId);

                    // itemsApi only spawns if you used session-detail-by-item
                    attemptReport.on("ready:itemsApi", (itemsApp) => {
                        useStudentsStore().reportLoaded(reportId);

                        // Load the questions into the items store
                        itemsApp.getQuestions((questions: object) => {
                            Object.values(questions).forEach((question: Question) => {
                                useItemsStore().addQuestionToItem(question);
                            });
                        });
                    });
                },
            };

            // Initialise reports for each attempt
            let reportsApp = LearnosityReports.init(initOptions, reportCallbacks[i]);
        }
    }

    return { init };
});
