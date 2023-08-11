import TimetableCalendar from "./timetable-calendar";

window.Vue.createApp({
    components: {
        TimetableCalendar,
    },
}).mount("#timetable-calendar");
/*
if (document.readyState !== "loading") {
    onPageLoad();
} else {
    document.addEventListener("DOMContentLoaded", onPageLoad);
}

/**
 * Attach event listeners to buttons, and create the Vue app.
 *
function onPageLoad() {
    const anchors = document.querySelectorAll("[data-child-student-button]");

    for (const anchor of anchors) {
        anchor.addEventListener("click", onClickChildStudentButton);
    }

    const vueApp = window.Vue.createApp({
        components: {
            TimetableCalendar,
        },
    });
    window.fullCalendarVueAppRoot = vueApp.mount("#timetable-calendar");
}

/**
 * When a child student button is clicked:
 *  - Ignore the click if the button is already in the active state
 *  - Load calendar events for the newly selected child student
 *  - Update the state of the buttons and the page heading.
 *
function onClickChildStudentButton(ev) {
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
    for (const anchorInner of document.querySelectorAll("[data-child-student-button]")) {
        anchorInner.parentElement.classList.remove("active");
    }
    anchor.parentElement.classList.add("active");

    // Update page heading.
    const childName = anchor.getAttribute("data-childFormattedName");
    const yearLevel = anchor.getAttribute("data-childYearLevel");
    document.querySelector("[data-heading-user-fullname]").innerHTML = childName;
    document.querySelector("[data-heading-user-year]").innerHTML = yearLevel == "" ? "" : yearLevel;
}
*/
