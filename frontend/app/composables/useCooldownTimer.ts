export interface EmailActionCooldown {
  is_active: boolean;
  remaining_seconds: number;
  available_at: string | null;
}

export function useCooldownTimer() {
  const remainingSeconds = ref(0);
  let timer: ReturnType<typeof setInterval> | null = null;

  function stop() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function start() {
    stop();

    if (remainingSeconds.value <= 0) {
      return;
    }

    timer = setInterval(() => {
      if (remainingSeconds.value <= 1) {
        remainingSeconds.value = 0;
        stop();
        return;
      }

      remainingSeconds.value -= 1;
    }, 1000);
  }

  function sync(cooldown?: EmailActionCooldown | null) {
    remainingSeconds.value = Math.max(0, cooldown?.remaining_seconds ?? 0);
    start();
  }

  const isCoolingDown = computed(() => remainingSeconds.value > 0);

  onUnmounted(() => stop());

  return {
    remainingSeconds,
    isCoolingDown,
    sync,
    stop,
  };
}
