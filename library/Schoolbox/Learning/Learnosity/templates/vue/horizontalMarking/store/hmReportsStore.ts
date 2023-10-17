/**
 *
 */

import { defineStore } from "pinia";
import { useStudentsStore } from "./hmStudentsStore";

export const useReportsStore = defineStore("hmReports", () => {
    /**
     * store any passed through properties
     * @param {string} reportPayloads - the report payload
     */
    function init(reportPayloads: string[]) {
        /* Lookups/indexes - a list of ids, and individualised report callbacks
         */
        const reportIds = [];
        const reportCallbacks = [];

        for (let i = 0; i < reportPayloads.length; i += 1) {
            const initOptions = JSON.parse(reportPayloads[i]);

            /* Remember all the report ids that we'll need to traverse
             */
            let reportId = initOptions.request.reports[0].id;
            reportIds[i] = reportId;

            /* Every report gets its own callback; this is because the readyListeners()
             * don't have any idea which report they are in, which means we can't tell
             * when a report has been fully loaded to the point where we can hide all
             * their divs, because we only want to show one div at a time per report.
             */
            reportCallbacks[i] = {
                readyListener: function () {
                    let attemptReport = reportsApp.getReport(reportId);

                    /* itemsApi only spawns if you used session-detail-by-item
                     */
                    attemptReport.on("ready:itemsApi", function (itemsApp) {
                        useStudentsStore().reportLoaded(reportId);
                        // debugger;
                        // window.itemsApp = itemsApp;

                        /* Loosely presume how many have loaded successfully
                         * by maintaining a counter; could use reportId as a
                         * lookup index if you wanted to double-down on this
                         */
                        // reportsLoaded++;

                        /* Start hiding things
                         */
                        // hideAllResponses(reportId);

                        /* Slap on some our feedback stuff
                         */
                        itemsApp.getQuestions(function (questions) {
                            // let report = document.querySelector(`#${reportId}`);

                            for (let key in questions) {
                                let element = questions[key];

                                /* Access different parts of the dom directly, to place
                                 * our input fields etc.
                                 */
                                let learnosityReference = element.metadata.sheet_reference;
                                let learnosityReferenceTuple = element.response_id;

                                /* Student scoring and metadata
                                 */
                                let score = responseScores[learnosityReferenceTuple].score;
                                let maxScore = responseScores[learnosityReferenceTuple].max;

                                /* Some questions have a sample answer
                                 * If it does, inject that too
                                 */
                                // let sampleAnswerHtml = "";

                                // if (element.metadata.sample_answer) {
                                //     sampleAnswerHtml =
                                //         "<label>Sample Answer</label><div>" + element.metadata.sample_answer + "</div></label>";
                                // }

                                /* Containers into which we inject things
                                 */
                                // let container = report.querySelector(`[data-reference="${learnosityReference}"]`);
                                // let guts = document.createElement("div");

                                /* For readability in the giant html slab below
                                 */
                                // let inputId = "scores-" + learnosityReferenceTuple;
                                // let inputName = "scores[" + learnosityReferenceTuple + "]";

                                // let textareaId = "responseComments-" + learnosityReferenceTuple;
                                // let textareaName = "responseComments[" + learnosityReferenceTuple + "]";
                                // let textareaVal = responseComments[learnosityReferenceTuple] ?? "";

                                // guts.innerHTML =
                                //     "" +
                                //     '<div class="sb-custom-score" style="display:none" data-reference="' +
                                //     learnosityReference +
                                //     '">' +
                                //     sampleAnswerHtml +
                                //     "<label>Customise score</label>" +
                                //     '<input id="' +
                                //     inputId +
                                //     '" name="' +
                                //     inputName +
                                //     '" type="number" value="' +
                                //     score +
                                //     '">' +
                                //     "<label>Comment</label>" +
                                //     '<textarea id="' +
                                //     textareaId +
                                //     '" name="' +
                                //     textareaName +
                                //     '" data-response-reference="' +
                                //     learnosityReferenceTuple +
                                //     '" data-report-id="' +
                                //     reportId +
                                //     '">' +
                                //     textareaVal +
                                //     "</textarea>" +
                                //     "</div>";

                                // container.append(guts);

                                /* We need this for cke replacements
                                 */
                                // ckeditorFields.push("#responseComments-" + learnosityReferenceTuple);

                                /* Assign async blur listener to each Score and Comment field
                                 * so it sends back to Learnosity upon blur
                                 *
                                 * TODO make more sophisticated by checking dirtyness first
                                 *   just like textboxes I guess
                                 */
                                // document.querySelector("#" + inputId).addEventListener("blur", function () {
                                //     /* Add toaster
                                //      */
                                //     let toast = $("#toast");
                                //     toast.toastActivate();

                                //     /* Send it
                                //      */
                                //     let payload = new FormData();
                                //     payload.append("learnosityReference", learnosityReferenceTuple);
                                //     payload.append("score", this.value);

                                //     /* Figure out the user id from the reportId value
                                //      * (we know it here, unlike when ckeditors load outside of this loop)
                                //      */
                                //     let userId = users[reportId].id;

                                //     fetch(urls[userId].score, {
                                //         method: "POST",
                                //         body: payload,
                                //     }).then((response) => {
                                //         /* Add toaster
                                //          */
                                //         toast.toastTimeout({
                                //             text: "Feedback updated.",
                                //         });
                                //     });
                                // });
                            }

                            /* Rewrite the item title to be the guy's name
                             */
                            // let titles = report.getElementsByClassName("lrn-report-item-title");

                            // for (let element of titles) {
                            //     element.firstChild.textContent = users[reportId].name;
                            // }
                        });
                    });
                },
            };

            /* Initialise reports for each attempt
             */
            let reportsApp = LearnosityReports.init(initOptions, reportCallbacks[i]);
        }
    }

    return { init };
});
