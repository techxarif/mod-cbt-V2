/**
 * MODUS CBT — Exam Countdown Timer
 * Prevents timer reset on refresh by calculating elapsed time from server-persisted started_at
 */
class ExamTimer {
    constructor(remainingSeconds, onTick, onExpire) {
        this.remainingSeconds = remainingSeconds;
        this.onTick = onTick;
        this.onExpire = onExpire;
        this.interval = null;
    }

    start() {
        this.update();
        this.interval = setInterval(() => {
            this.remainingSeconds--;
            this.update();
            if (this.remainingSeconds <= 0) {
                this.stop();
                if (typeof this.onExpire === 'function') {
                    this.onExpire();
                }
            }
        }, 1000);
    }

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }

    update() {
        const hours = Math.floor(Math.max(0, this.remainingSeconds) / 3600);
        const minutes = Math.floor((Math.max(0, this.remainingSeconds) % 3600) / 60);
        const seconds = Math.floor(Math.max(0, this.remainingSeconds) % 60);

        const formatted = [
            String(hours).padStart(2, '0'),
            String(minutes).padStart(2, '0'),
            String(seconds).padStart(2, '0')
        ].join(':');

        if (typeof this.onTick === 'function') {
            this.onTick(formatted, this.remainingSeconds);
        }
    }
}

window.ExamTimer = ExamTimer;
