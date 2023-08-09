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

    const vueApp = window.Vue.createApp({
        components: {
            TimetableCalendar,
        },
    });
    window.fullCalendarVueAppRoot = vueApp.mount("#timetable-calendar");
}

function onClickTimetableSelector(ev) {
    const anchor = ev.target;

    if (anchor.parentElement.classList.contains("active")) {
        // This child is already selected, so do nothing.
        return;
    }

    const key = anchor.getAttribute("data-timetable-selector");
    const tokens = key.split("-");
    const userId = tokens[0];
    const campusId = tokens[1];

    // The ref is set as <timetable-calendar ref='timetable-calendar'></timetable-calendar> in timetableCalendar.twig.
    window.fullCalendarVueAppRoot.$refs["timetable-calendar"].changeUserAndReloadEvents(userId);

    // Update state of buttons.
    for (const anchorInner of document.querySelectorAll("[data-timetable-selector]")) {
        anchorInner.parentElement.classList.remove("active");
    }
    anchor.parentElement.classList.add("active");

    // Update page heading.
    const childName = anchor.getAttribute("data-childFormattedName");
    const yearLevel = anchor.getAttribute("data-childYearLevel");
    document.querySelector("[data-heading-user-fullname]").innerHTML = childName;
    document.querySelector("[data-heading-user-year]").innerHTML = yearLevel == "" ? "" : ", " + yearLevel;
}
