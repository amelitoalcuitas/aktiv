<script setup lang="ts">
import type { Hub } from '~/types/hub';
import type { OpenPlaySession } from '~/types/openPlay';
import OpenPlayJoinModal from '~/components/openPlay/OpenplayJoinModal.vue';
import OpenPlaySessionCard from '~/components/openPlay/OpenplaySessionCard.vue';

definePageMeta({ layout: 'hub' });

useHead({ title: 'Open Play · Aktiv' });

const route = useRoute();
const hubId = computed(() => String(route.params.id ?? ''));
const { fetchSessions } = useOpenPlay();

const { data: hub } = useNuxtData<Hub>(`hub-${hubId.value}`);

const loading = ref(true);
const sessions = ref<OpenPlaySession[]>([]);
const joinModalOpen = ref(false);
const selectedSessionId = ref<string | null>(null);

async function loadSessions() {
  loading.value = true;
  try {
    sessions.value = await fetchSessions(hubId.value);
  } finally {
    loading.value = false;
  }
}

await loadSessions();

// ── Real-time slot count updates ──────────────────────────────────
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let hubChannel: any = null;

onMounted(() => {
  const { $echo } = useNuxtApp();
  if (!$echo) return;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const echo = $echo as any;
  echo.connector.pusher.connection.connect();
  hubChannel = echo.channel(`hub.${hubId.value}`);
  hubChannel.listen('.booking.slot.updated', () => {
    loadSessions();
  });
});

onUnmounted(() => {
  const { $echo } = useNuxtApp();
  if ($echo && hubChannel) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    ($echo as any).leaveChannel(`hub.${hubId.value}`);
    hubChannel = null;
  }
});

const selectedSession = computed(
  () =>
    sessions.value.find((session) => session.id === selectedSessionId.value) ??
    null
);

function openSession(sessionId: string) {
  selectedSessionId.value = sessionId;
  joinModalOpen.value = true;
}
</script>

<template>
  <div class="space-y-6">
    <UCard
      class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
      :ui="{ root: 'ring-0 divide-y-0', body: 'p-6' }"
    >
      <div
        class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
      >
        <div class="flex items-start gap-4">
          <div class="rounded-xl bg-primary/10 p-3 text-primary">
            <UIcon name="i-heroicons-user-group" class="h-6 w-6" />
          </div>
          <div>
            <h2 class="m-0 text-2xl font-black text-[var(--aktiv-ink)]">
              Open Play
            </h2>
            <p class="mt-2 text-[var(--aktiv-muted)]">
              Discover upcoming public sessions at
              {{ hub?.name ?? 'this hub' }} and join in a few taps.
            </p>
          </div>
        </div>
      </div>
    </UCard>

    <div
      v-if="loading"
      class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-10 text-center text-sm text-[var(--aktiv-muted)]"
    >
      Loading open play sessions...
    </div>

    <div
      v-else-if="sessions.length === 0"
      class="rounded-2xl border border-dashed border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-10 text-center"
    >
      <UIcon
        name="i-heroicons-calendar-days"
        class="mx-auto h-10 w-10 text-[var(--aktiv-border)]"
      />
      <h3 class="mt-3 text-sm font-semibold text-[var(--aktiv-ink)]">
        No upcoming open play sessions
      </h3>
      <p class="mt-1 text-sm text-[var(--aktiv-muted)]">
        Check back soon for new public sessions.
      </p>
    </div>

    <div v-else class="space-y-4">
      <OpenPlaySessionCard
        v-for="session in sessions"
        :key="session.id"
        :session="session"
        @open="openSession"
      />
    </div>

    <OpenPlayJoinModal
      v-model:open="joinModalOpen"
      :hub-id="hubId"
      :hub="hub ?? null"
      :session="selectedSession"
      @updated="loadSessions"
    />
  </div>
</template>
