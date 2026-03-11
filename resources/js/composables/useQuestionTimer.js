import { ref, onUnmounted } from 'vue';

export function useQuestionTimer(initialSeconds) {
    const remainingSeconds = ref(Math.max(initialSeconds, 1));
    let timerId = null;

    const formatTimer = (seconds) => {
        const safe = Math.max(Number(seconds) || 0, 0);
        const m = Math.floor(safe / 60).toString().padStart(2, '0');
        const s = (safe % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    };

    const stop = () => {
        if (timerId !== null) {
            window.clearInterval(timerId);
            timerId = null;
        }
    };

    const start = (onTick, onZero) => {
        stop();
        remainingSeconds.value = Math.max(initialSeconds, 1);

        timerId = window.setInterval(() => {
            remainingSeconds.value -= 1;
            onTick?.(remainingSeconds.value);

            if (remainingSeconds.value <= 0) {
                stop();
                onZero?.();
            }
        }, 1000);
    };

    onUnmounted(stop);

    return { remainingSeconds, formatTimer, start, stop };
}
