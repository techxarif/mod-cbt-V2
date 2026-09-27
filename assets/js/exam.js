/**
 * MODUS CBT — Exam Interface Controller
 */
window.ExamController = {
    init(config) {
        this.config = config;
        this.timer = new ExamTimer(
            config.remainingSeconds,
            (formatted, secs) => {
                const el = document.getElementById('timer');
                if (el) el.textContent = formatted;
                if (secs <= 300) el?.classList.add('danger');
            },
            () => {
                this.submitExam(true);
            }
        );
        this.timer.start();
    },

    submitExam(isAuto = false) {
        if (!isAuto && !confirm('Are you sure you want to submit your examination?')) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'submit.php';

        const testIdInput = document.createElement('input');
        testIdInput.type = 'hidden';
        testIdInput.name = 'test_id';
        testIdInput.value = this.config.testId;
        form.appendChild(testIdInput);

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf';
        csrfInput.value = this.config.csrfToken;
        form.appendChild(csrfInput);

        document.body.appendChild(form);
        form.submit();
    }
};
