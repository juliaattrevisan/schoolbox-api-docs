import http from "k6/http";
import { check, sleep } from "k6";
import { credentials } from "./config.js";

// Test configuration
export let options = {
    stages: [
        { duration: "1m", target: 2 }, // Ramp up to 2 VUs over 1 minute
        { duration: "3m", target: 2 }, // Stay at 2 VUs for 3 minutes
        { duration: "1m", target: 5 }, // Ramp up to 5 VUs over 1 minute
        { duration: "3m", target: 5 }, // Stay at 5 VUs for 3 minutes
        { duration: "1m", target: 0 }, // Ramp down to 0
    ],
    thresholds: {
        http_req_duration: ["p(95)<3000"], // 95% of requests under 3 seconds
        http_req_failed: ["rate<0.1"], // Less than 10% failure rate
    },
};

const BASE_URL = "https://canary.dev.schoolbox.cloud";

export default function () {
    // Pick a random user for this VU's iteration
    const user = credentials[Math.floor(Math.random() * credentials.length)];

    // Calculate Unix timestamps
    const now = Math.floor(Date.now() / 1000);
    const startOfToday = now - (now % 86400); // Round down to midnight
    const startOfTomorrow = startOfToday + 86400; // Add 24 hours
    const dayOfWeek = new Date().getDay(); // 0 = Sunday
    const calendarStart = startOfToday - dayOfWeek * 86400; // Back to Sunday, i.e., start of current week
    const calendarEnd = calendarStart + 42 * 86400; // Forward 6 weeks
    const currentYear = new Date().getFullYear();
    const endOfYear = Math.floor(new Date(currentYear + 1, 0, 1).getTime() / 1000);

    // Step 1: Login
    let loginRes = http.post(`${BASE_URL}/login?`, {
        page: "",
        username: user.username,
        password: user.password,
        Submit: "Login",
    });

    // Check if login was successful
    check(loginRes, {
        "login successful": (r) => r.status === 200 || r.status === 302,
    });
    sleep(1); // Wait 1 second after login

    // Step 2: Visit Dashboard
    let dashboardRes = http.get(`${BASE_URL}/`);
    check(dashboardRes, {
        "dashboard loaded": (r) => r.status === 200,
    });

    // Dashboard AJAX requests
    http.get(`${BASE_URL}/news/saved`);
    http.get(`${BASE_URL}/news/lists/feed`);
    http.get(`${BASE_URL}/paperly/viewPaperly`);
    http.get(`${BASE_URL}/calendar/ajax/full?start=${startOfToday}&end=${startOfTomorrow}&userId=${user.userId}`);
    sleep(2);

    // Step 3: Visit Calendar
    let calendarRes = http.get(`${BASE_URL}/calendar/`);
    check(calendarRes, {
        "calendar loaded": (r) => r.status === 200,
    });

    // Calendar AJAX request
    http.get(`${BASE_URL}/calendar/ajax/full?start=${calendarStart}&end=${calendarEnd}&userId=${user.userId}`);
    sleep(2);

    // Step 4: Visit Timetable
    let timetableRes = http.get(`${BASE_URL}/timetable`);
    check(timetableRes, {
        "timetable loaded": (r) => r.status === 200,
    });
    sleep(2);

    // Step 5: Visit Due Work
    let dueWorkRes = http.get(`${BASE_URL}/learning/due/`);
    check(dueWorkRes, {
        "due work loaded": (r) => r.status === 200,
    });

    // Due Work AJAX request
    http.get(`${BASE_URL}/learning/due/events/${user.userId}?start=${calendarStart}&end=${calendarEnd}`);
    sleep(2);

    // Step 6: Visit Homepage
    if (user.roleType !== "Parent") {
        let homepageRes = http.get(`${BASE_URL}/homepage/${user.homepageId}`);
        check(homepageRes, {
            "homepage loaded": (r) => r.status === 200,
        });

        // Homepage AJAX request
        http.get(`${BASE_URL}/calendar/ajax/full?start=${startOfToday}&end=${endOfYear}&userId=${user.userId}`);
        sleep(2);
    }

    // Step 7: Visit Grades
    let gradesRes = http.get(`${BASE_URL}/learning/grades`);
    check(gradesRes, {
        "grades loaded": (r) => r.status === 200,
    });
    sleep(2);

    // Step 8: Visit Course List
    if (user.roleType === "Staff") {
        let courseListRes = http.get(`${BASE_URL}/course/list`);
        check(courseListRes, {
            "course list loaded:": (r) => r.status === 200,
        });
        sleep(3);
     }
}
