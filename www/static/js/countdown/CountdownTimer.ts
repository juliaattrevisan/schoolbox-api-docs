/**
 * A class to implement a countdown timer
 */
export default class CountdownTimer {
    private targetDate: Date;
    private timerId: number;
    private interval: number;

    /**
     * Event to be emitted when the countdown changes
     *
     * @event
     * @param {number} totalSeconds - Total number of seconds remaining in the countdown
     * @param {number} seconds - Number of seconds remaining in the countdown
     * @param {number} minutes - Number of minutes remaining in the countdown
     * @param {number} hours - Number of hours remaining in the countdown
     * @param {number} days - Number of days remaining in the countdown
     * @param {number} weeks - Number of weeks remaining in the countdown
     */
    public onCountdownChange: (timeRemaining: {
        totalSeconds: number;
        seconds: number;
        minutes: number;
        hours: number;
        days: number;
        weeks: number;
    }) => void;

    // Event to be emitted when the countdown finishes
    public onCountdownFinished: () => void;

    constructor(targetDate: Date, interval = 1000) {
        this.targetDate = targetDate;
        this.interval = interval;
    }

    /**
     * Updates the countdown and emits the onCountdownChange event
     */
    private updateCountdown() {
        const timeRemaining = this.targetDate.getTime() - Date.now();
        if (timeRemaining <= 0) {
            window.clearInterval(this.timerId);
            this.onCountdownFinished();
        } else {
            const totalSeconds = timeRemaining / 1000;
            const seconds = Math.floor(totalSeconds % 60);
            const minutes = Math.floor((totalSeconds / 60) % 60);
            const hours = Math.floor((totalSeconds / (60 * 60)) % 24);
            const days = Math.floor((totalSeconds / (60 * 60 * 24)) % 7);
            const weeks = Math.floor(totalSeconds / (60 * 60 * 24 * 7));
            this.onCountdownChange({
                totalSeconds,
                seconds,
                minutes,
                hours,
                days,
                weeks,
            });
        }
    }

    /**
     * Starts the countdown
     */
    start() {
        this.updateCountdown(); // Workaround to avoid a 1 second delay before the first update
        this.timerId = window.setInterval(() => {
            this.updateCountdown();
        }, this.interval);
    }

    /**
     * Stops the countdown
     */
    stop() {
        window.clearInterval(this.timerId);
    }
}
