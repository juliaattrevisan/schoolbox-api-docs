import TimetableCalendar from "./timetable-calendar";

if (document.readyState !== "loading") {
    attachEventListeners();
} else {
    document.addEventListener("DOMContentLoaded", attachEventListeners);
}

function attachEventListeners() {
    const anchors = document.querySelectorAll("[data-timetable-selector]");

    for (const anchor of anchors) {
        anchor.addEventListener("click", onClickTimetableSelector);
    }

    initCalendar();
}

function onClickTimetableSelector(ev) {
    const anchor = ev.target;
    const key = anchor.getAttribute("data-timetable-selector");
    const tokens = key.split("-");
    const userId = tokens[0];
    const campusId = tokens[1];

    window.fullCalendarConfig.userId = userId;

    // TODO: Update TimetableCalendar somehow to use updated window.fullCalendarConfig.
    //       Maybe by:
    //        * Updating the Vue app's internal data, so it then will update itself?
    //        * Destroying then recreating the Vue app?

    // Update state of buttons.
    for (const anchorInner of document.querySelectorAll("[data-timetable-selector]")) {
        anchorInner.parentElement.classList.remove("active");
    }
    anchor.parentElement.classList.add("active");
}

export function initCalendar() {
    window.fullCalendarVueApp = window.Vue.createApp({
        components: {
            TimetableCalendar,
        },
    });
    const mountResult = window.fullCalendarVueApp.mount("#timetable-calendar");
}
