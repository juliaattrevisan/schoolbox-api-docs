type TimeRemainingDetail = {
    totalSeconds: number; // Total number of seconds remaining in the countdown
    seconds: number; // Number of seconds remaining in the countdown
    minutes: number; // Number of minutes remaining in the countdown
    hours: number; // Number of hours remaining in the countdown
    days: number; // Number of days remaining in the countdown
    weeks: number; // Number of weeks remaining in the countdown
};

type OnCountdownChangeEvent = (timeRemaining: TimeRemainingDetail) => void;
type OnCountdownFinishedEvent = () => void;

/**
 * A class to implement a countdown timer
 */
export default class CountdownTimer {
    private targetDate: Date;

    private timerId: number | null = null; // The ID of the timer interval, so it can be stopped

    private interval: number;

    /**
     * Event to be emitted when the countdown changes
     */
    private onCountdownChangeHandler: OnCountdownChangeEvent | null = null;

    /**
     * Event to be emitted when the countdown finishes
     */
    private onCountdownFinishedHandler: OnCountdownFinishedEvent | null = null;

    constructor(targetDate: Date, interval: number = 1000) {
        this.targetDate = targetDate;
        this.interval = interval;
    }

    /**
     * Updates the countdown and emits the onCountdownChange event
     */
    private updateCountdown(): void {
        if (!this.timerId) return; // Timer has been stopped

        const timeRemaining = this.targetDate.getTime() - Date.now();
        if (timeRemaining <= 0) {
            window.clearInterval(this.timerId);
            if (this.onCountdownFinishedHandler) {
                this.onCountdownFinishedHandler();
            }
        } else {
            const totalSeconds = timeRemaining / 1000;
            const seconds = Math.floor(totalSeconds % 60);
            const minutes = Math.floor((totalSeconds / 60) % 60);
            const hours = Math.floor((totalSeconds / (60 * 60)) % 24);
            const days = Math.floor((totalSeconds / (60 * 60 * 24)) % 7);
            const weeks = Math.floor(totalSeconds / (60 * 60 * 24 * 7));
            if (this.onCountdownChangeHandler) {
                this.onCountdownChangeHandler({
                    totalSeconds,
                    seconds,
                    minutes,
                    hours,
                    days,
                    weeks,
                });
            }
        }
    }

    /**
     * Starts the countdown
     */
    start(): void {
        this.updateCountdown(); // Workaround to avoid a 1 second delay before the first update
        this.timerId = window.setInterval(() => {
            this.updateCountdown();
        }, this.interval);
    }

    /**
     * Stops the countdown
     */
    stop(): void {
        if (!this.timerId) return; // Timer has already been stopped
        window.clearInterval(this.timerId);
    }

    /**
     * Event to be emitted when the countdown finishes
     * @param handler - the event handler
     */
    public onCountdownChange(handler: (timeRemaining: TimeRemainingDetail) => void): void {
        this.onCountdownChangeHandler = handler;
    }

    /**
     * Event to be emitted when the countdown finishes
     * @param handler - the event handler
     */
    public onCountdownFinished(handler: () => void): void {
        this.onCountdownFinishedHandler = handler;
    }
}
