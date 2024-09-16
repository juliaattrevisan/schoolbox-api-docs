// Countdown timer used on homepages

// import confetti from "canvas-confetti";
import CountdownTimer from "./CountdownTimer";
import { play } from "./countdownAnimation";

const countdownElements = document.querySelectorAll('[data-countdown="elements"]');
countdownElements.forEach((element) => {
    const targetDateString = element.getAttribute("data-target-date");
    const targetDate = targetDateString ? new Date(targetDateString) : new Date();
    const targetElement = element;
    const countdownTimer = new CountdownTimer(targetDate);
    // Avoid duplicate timers on the same element
    if (targetElement.getAttribute("started") === "true") {
        return;
    }
    targetElement.setAttribute("started", "true");

    countdownTimer.onCountdownChange((timeRemaining) => {
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
    });

    countdownTimer.onCountdownFinished(() => {
        const targetElementParent = targetElement.parentElement;

        if (targetElementParent === null) {
            return; // Countdown is invalid or has been removed...
        }

        if (targetElementParent.classList.contains("disabled")) {
            return; // Prevent multiple confetti explosions
        }

        targetElement.innerHTML =
            '<li class="count count--finished l-flex l-flex--center">Countdown has been reached!</li>';
        targetElementParent.classList.add("disabled");

        if (targetElement.getAttribute("data-finish-animation") === "1") {
            let hasTriggered = false;
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting && !hasTriggered) {
                            hasTriggered = true;
                            const boundRect = targetElement.getBoundingClientRect();
                            play(boundRect);
                        }
                    });
                },
                { rootMargin: "0px", threshold: 1.0 },
            );

            observer.observe(targetElement);
        }
    });

    countdownTimer.start();
});
