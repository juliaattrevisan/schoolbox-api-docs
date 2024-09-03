// Quiz evaluation countdown timer

import CountdownTimer from "./CountdownTimer";

const countdownText = document.querySelectorAll('[data-countdown="evaluation"]');
countdownText.forEach((element) => {
    const timeLeft = parseInt(element.getAttribute("data-timeleft") ?? "-1", 10); // in seconds

    if (!(element instanceof HTMLElement)) {
        return;
    }

    const targetElement = element;
    if (timeLeft < 0) {
        targetElement.innerText = "Unlimited";
        return;
    }
    const targetDate = new Date(Date.now() + timeLeft * 1000);
    const countdownTimer = new CountdownTimer(targetDate);

    // Avoid duplicate timers on the same element
    if (targetElement.getAttribute("started") === "true") {
        return;
    }
    targetElement.setAttribute("started", "true");

    countdownTimer.onCountdownChange = (timeRemaining): void => {
        targetElement.dispatchEvent(new CustomEvent("countdownChange", { detail: timeRemaining }));
    };

    countdownTimer.onCountdownFinished = (): void => {
        targetElement.dispatchEvent(new CustomEvent("countdownFinished"));
    };

    countdownTimer.start();
});
