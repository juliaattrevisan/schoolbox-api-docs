import IbStudentRegistrationConfirmation from "./ib-student-registration-confirmation.vue";

(["candidate", "subject"] as const).forEach((section) => {
    const mountEl = document.getElementById(`ib-${section}-confirmation`);

    if (mountEl) {
        window.Vue.createApp(IbStudentRegistrationConfirmation, {
            section,
            updateConfirmationEndpoint: mountEl.dataset.updateConfirmationEndpoint ?? "",
            initialConfirmed: mountEl.dataset.confirmed === "true",
            initialConfirmedBy: mountEl.dataset.confirmedBy ?? "",
            initialConfirmedAt: mountEl.dataset.confirmedAt ?? "",
        }).mount(mountEl);
    }
});
