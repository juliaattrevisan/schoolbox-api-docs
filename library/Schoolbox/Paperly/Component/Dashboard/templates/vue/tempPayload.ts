/**
 * Dummy payload while we await the actualy Paperly API
 */

import { PaperlyPayload } from "../js/types/PaperlyPayload";

export const payload: PaperlyPayload = {
    features: [
        { Forms: "https://url/to/iframe/create_forms" },
        { Excursions: "https://url/to/iframe/create_excursions" },
        { PTI: "https://url/to/iframe/view_pti" },
    ],
    upcoming: [
        {
            title: "Approve Field Trip: Visit to Science Museum",
            status: "Pending Approval",
            due: "2024-04-15T08:30:00+00:00",
            url: "https://url/to/iframe/complete_action",
        },
        {
            title: "Permission Slip: School Play Performance",
            status: "Pending Approval",
            due: "2024-04-17T10:15:00+00:00",
            url: "https://url/to/iframe/complete_action",
        },
        {
            title: "Parent-Teacher Conference Sign-Up",
            due: "2024-04-20T12:45:00+00:00",
            url: "https://url/to/iframe/complete_action",
        },
        {
            title: "Approve Budget: Annual School Dance",
            status: "Pending Approval",
            due: "2024-04-22T09:00:00+00:00",
            url: "https://url/to/iframe/complete_action",
        },
        {
            title: "Review Curriculum Proposal: New Language Program",
            status: "Pending Approval",
            due: "2024-04-25T13:30:00+00:00",
            url: "https://url/to/iframe/complete_action",
        },
        {
            title: "Teacher Workshop Attendance Form",
            status: "Pending Approval",
            due: "2024-04-27T11:45:00+00:00",
            url: "https://url/to/iframe/complete_action",
        },
        {
            title: "Approve Student Council Budget Proposal",
            status: "Pending Approval",
            due: "2024-04-30T14:20:00+00:00",
            url: "https://url/to/iframe/complete_action",
        },
    ],
};
