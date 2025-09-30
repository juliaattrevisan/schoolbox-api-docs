import http from 'k6/http';
import { check, sleep } from 'k6';
import { credentials } from './config.js';

// Test configuration
export let options = {
    stages: [
        { duration: '1m', target: 2 },  // Ramp up to 2 VUs over 1 minute
        { duration: '3m', target: 2 },  // Stay at 2 VUs for 3 minutes
        { duration: '1m', target: 5 },  // Ramp up to 5 VUs over 1 minute
        { duration: '3m', target: 5 },  // Stay at 5 VUs for 3 minutes
        { duration: '1m', target: 0 },  // Ramp down to 0
    ],
    thresholds: {
        http_req_duration: ['p(95)<3000'], // 95% of requests under 3 seconds
        http_req_failed: ['rate<0.1'],     // Less than 10% failure rate
    },
};

const BASE_URL = 'https://vagrant.dev.schoolbox.cloud';

export default function () {

    // Step 1: Login
    let loginRes = http.post(`${BASE_URL}/login?`, {
        page: '',
        username: credentials.username,
        password: credentials.password,
        Submit: 'Login'
    });

    // Check if login was successful
    check(loginRes, {
        'login successful': (r) => r.status === 200 || r.status === 302,
    });

    sleep(1); // Wait 1 second after login

    // Step 2: Visit Dashboard
    let dashboardRes = http.get(`${BASE_URL}/`);
    check(dashboardRes, {
        'dashboard loaded': (r) => r.status === 200,
    });
    sleep(2);

    // Step 3: Visit Calendar
    let calendarRes = http.get(`${BASE_URL}/calendar/`);
    check(calendarRes, {
        'calendar loaded': (r) => r.status === 200,
    });
    sleep(2);

    // Step 4: Visit Timetable
    let timetableRes = http.get(`${BASE_URL}/timetable`);
    check(timetableRes, {
        'timetable loaded': (r) => r.status === 200,
    });
    sleep(2);

    // Step 5: Visit Due Work
    let dueWorkRes = http.get(`${BASE_URL}/learning/due/`);
    check(dueWorkRes, {
        'due work loaded': (r) => r.status === 200,
    });
    sleep(2);

    // Step 6: Visit Homepage
    let homepageRes = http.get(`${BASE_URL}/homepage/80`);
    check(homepageRes, {
        'homepage loaded': (r) => r.status === 200,
    });
    sleep(2);

    // Step 7: Visit Grades
    let gradesRes = http.get(`${BASE_URL}/learning/grades`);
    check(gradesRes, {
        'grades loaded': (r) => r.status === 200,
    });
    sleep(3);

}
