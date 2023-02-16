// Countdown timer used on homepages

import CountdownTimer from "./CountdownTimer";

const countdownElements = document.querySelectorAll('[data-countdown="elements"]');
countdownElements.forEach((element) => {
    const targetDate = new Date(element.getAttribute("data-target-date"));
    const targetElement = element;
    const countdownTimer = new CountdownTimer(targetDate);

    // Avoid duplicate timers on the same element
    if (targetElement.getAttribute("started") === "true") {
        return;
    }
    targetElement.setAttribute("started", "true");

    countdownTimer.onCountdownChange = (timeRemaining) => {
        let format =
            `<li><span class="count" data-hours>${timeRemaining.hours}</span> <span class="labels">Hrs</span></li>` +
            `<li><span class="count" data-minutes>${timeRemaining.minutes}</span> <span class="labels">Mins</span></li>` +
            `<li><span class="count" data-seconds>${timeRemaining.seconds}</span> <span class="labels">Secs</span></li>`;

        if (timeRemaining.days > 0) {
            format = ` <li><span class="count" data-days>${timeRemaining.days}</span> <span class="labels">Day${
                timeRemaining.days === 1 ? "" : "s"
            }</span></li> ${format}`;
        }

        if (timeRemaining.weeks > 0) {
            format = ` <li><span class="count" data-weeks>${timeRemaining.weeks}</span> <span class="labels">Week${
                timeRemaining.weeks === 1 ? "" : "s"
            }</span></li> ${format}`;
        }

        targetElement.innerHTML = format;
    };

    countdownTimer.onCountdownFinished = () => {
        targetElement.innerHTML = "Countdown has been reached!";
        targetElement.parentElement.classList.add("disabled");
    };

    countdownTimer.start();
});
