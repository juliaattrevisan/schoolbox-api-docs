import TimetableCalendar from "./timetable-calendar";

if (document.readyState !== "loading") {
    attachEventListeners();
} else {
    document.addEventListener("DOMContentLoaded", attachEventListeners);
}

function attachEventListeners() {
    const anchors = document.querySelectorAll("[data-child-button]");

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

    // The ref is set as <timetable-calendar ref='timetable-calendar'></timetable-calendar> in timetableCalendar.twig.
    window.fullCalendarVueAppRoot.$refs["timetable-calendar"].changeUserAndReloadEvents(
        anchor.getAttribute("data-userId")
    );

    // Update state of buttons.
    for (const anchorInner of document.querySelectorAll("[data-child-button]")) {
        anchorInner.parentElement.classList.remove("active");
    }
    anchor.parentElement.classList.add("active");

    // Update page heading.
    const childName = anchor.getAttribute("data-childFormattedName");
    const yearLevel = anchor.getAttribute("data-childYearLevel");
    document.querySelector("[data-heading-user-fullname]").innerHTML = childName;
    document.querySelector("[data-heading-user-year]").innerHTML = yearLevel == "" ? "" : ", " + yearLevel;
}
