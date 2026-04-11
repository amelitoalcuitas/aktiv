<script setup lang="ts">
import type { Court, Hub } from '~/types/hub';
import type { OpenPlaySession } from '~/types/openPlay';
import { getOpenPlaySessionPresentation } from '~/utils/openPlayPresentation';
import OpenPlayOwnerModal from '~/components/openPlay/OpenplayOwnerModal.vue';
import BookingWalkInModal from '~/components/booking/BookingWalkInModal.vue';

definePageMeta({ middleware: 'owner-hub', layout: 'dashboard-hub' });

const route = useRoute();
const { fetchCourts, fetchHub } = useHubs();
const { fetchSessions } = useOwnerOpenPlay();
const toast = useToast();

const hubId = computed(() => String(route.params.id));

const manageTabs = computed(() => [
  {
    label: 'Hub',
    icon: 'i-heroicons-building-storefront',
    to: `/dashboard/hubs/${hubId.value}/edit`
  },
  {
    label: 'Courts',
    icon: 'i-heroicons-squares-2x2',
    to: `/dashboard/hubs/${hubId.value}/courts`
  },
  {
    label: 'Bookings',
    icon: 'i-heroicons-calendar-days',
    to: `/dashboard/hubs/${hubId.value}/bookings`
  },
  {
    label: 'Open Play',
    icon: 'i-heroicons-user-group',
    to: `/dashboard/hubs/${hubId.value}/open-play`
  },
  {
    label: 'Events',
    icon: 'i-heroicons-megaphone',
    to: `/dashboard/hubs/${hubId.value}/events`
  },
  {
    label: 'Reviews',
    icon: 'i-heroicons-star',
    to: `/dashboard/hubs/${hubId.value}/reviews`
  },
  {
    label: 'Settings',
    icon: 'i-heroicons-cog-6-tooth',
    to: `/dashboard/hubs/${hubId.value}/settings`
  }
]);

const sessions = ref<OpenPlaySession[]>([]);
const sessionsLoading = ref(false);
const hubCourts = ref<Court[]>([]);
const hubData = ref<Hub | null>(null);

const isCreateOpen = ref(false);
const isManageOpen = ref(false);
const selectedSessionId = ref<string | null>(null);
const highlightedSessionId = ref<string | null>(null);

async function loadSessions() {
  sessionsLoading.value = true;
  try {
    const fetchedSessions = await fetchSessions(hubId.value);
    sessions.value = [...fetchedSessions].sort(
      (a, b) =>
        new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    );
  } catch {
    toast.add({ title: 'Failed to load open play sessions', color: 'error' });
  } finally {
    sessionsLoading.value = false;
  }
}

async function loadHubContext() {
  try {
    const [courts, hub] = await Promise.all([
      fetchCourts(hubId.value),
      fetchHub(hubId.value)
    ]);

    hubCourts.value = courts;
    hubData.value = hub;
  } catch {
    toast.add({ title: 'Failed to load hub details', color: 'error' });
  }
}

function scrollToSession(id: string) {
  nextTick(() => {
    setTimeout(() => {
      const el = document.getElementById(`session-${id}`);
      if (!el) return;
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      highlightedSessionId.value = id;
      setTimeout(() => {
        highlightedSessionId.value = null;
      }, 5000);
    }, 80);
  });
}

onMounted(async () => {
  await Promise.all([loadSessions(), loadHubContext()]);
  const sessionId = Array.isArray(route.query.sessionId)
    ? route.query.sessionId[0]
    : route.query.sessionId;
  if (sessionId) scrollToSession(sessionId);
});

watch(
  () => route.query.sessionId,
  (sessionId) => {
    const id = Array.isArray(sessionId) ? sessionId[0] : sessionId;
    if (id) scrollToSession(id);
  }
);;

function openManage(sessionId: string) {
  selectedSessionId.value = sessionId;
  isManageOpen.value = true;
}

async function onSessionCreated(session: OpenPlaySession) {
  await loadSessions();
  openManage(session.id);
}

async function onSessionUpdated() {
  await loadSessions();
}

function sessionPresentation(session: OpenPlaySession) {
  return getOpenPlaySessionPresentation(session);
}

function isCancelledSession(session: OpenPlaySession): boolean {
  return sessionPresentation(session).status === 'cancelled';
}

function formatSchedule(session: OpenPlaySession): string {
  if (!session.booking) return 'Schedule unavailable';
  const timezone = session.booking.hub_timezone ?? session.booking.court?.hub_timezone ?? hubData.value?.timezone;
  return `${session.booking.court?.name ?? 'Court'} · ${formatInHubTimezone(session.booking.start_time, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  }, 'en-PH', timezone)} · ${formatInHubTimezone(session.booking.start_time, {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  }, 'en-PH', timezone)} - ${formatInHubTimezone(session.booking.end_time, {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  }, 'en-PH', timezone)}`;
}

function formatPrice(session: OpenPlaySession): string {
  const price = Number(session.price_per_player);

  if (price === 0) return 'Free session';

  return `P${price.toLocaleString('en-PH')} / player`;
}

function participantSummary(session: OpenPlaySession): string {
  return `${session.participants_count} / ${session.max_players} players reserved`;
}

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
</script>

<template>
  <div class="flex min-w-0 w-full max-w-full flex-col">
    <HubTabNav :tabs="manageTabs" />

    <div class="mx-auto w-full max-w-[1200px] px-4 py-8 md:px-6">
      <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-[#0f1728]">Open Play</h1>
          <p class="mt-1 text-sm text-[#64748b]">
            Create and manage public open play sessions for your hub.
          </p>
        </div>

        <UButton
          icon="i-heroicons-plus"
          class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
          @click="isCreateOpen = true"
        >
          Create Open Play
        </UButton>
      </div>

      <div
        v-if="sessionsLoading"
        class="rounded-2xl border border-[#dbe4ef] bg-white p-10 text-center text-sm text-[#64748b]"
      >
        Loading open play sessions...
      </div>

      <div
        v-else-if="sessions.length === 0"
        class="rounded-2xl border border-dashed border-[#dbe4ef] bg-white p-10 text-center"
      >
        <UIcon
          name="i-heroicons-user-group"
          class="mx-auto h-10 w-10 text-[#c8d5e0]"
        />
        <h2 class="mt-3 text-base font-semibold text-[#0f1728]">
          No open play sessions yet
        </h2>
        <p class="mt-1 text-sm text-[#64748b]">
          Create your first session to start accepting individual players.
        </p>
      </div>

      <div v-else class="space-y-4">
        <UCard
          v-for="session in sessions"
          :id="`session-${session.id}`"
          :key="session.id"
          :class="[
            'rounded-2xl border bg-white transition-all duration-500',
            highlightedSessionId === session.id
              ? 'border-[#004e89] ring-2 ring-[#004e89]/30'
              : 'border-[#dbe4ef]',
            isCancelledSession(session) ? 'opacity-80' : ''
          ]"
          :ui="{ root: 'ring-0', body: 'p-5' }"
        >
          <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-[#0f1728]">
                  {{ session.title }}
                </h2>
                <UBadge
                  :color="sessionPresentation(session).color"
                  variant="soft"
                >
                  {{ sessionPresentation(session).label }}
                </UBadge>
              </div>

              <p class="mt-2 text-sm text-[#64748b]">
                {{ formatSchedule(session) }}
              </p>

              <div class="mt-4 flex flex-wrap gap-2 text-sm">
                <span class="rounded-md bg-[#f8fafc] px-2.5 py-1 text-[#0f1728]">
                  {{ participantSummary(session) }}
                </span>
                <span class="rounded-md bg-[#f8fafc] px-2.5 py-1 text-[#0f1728]">
                  {{ formatPrice(session) }}
                </span>
                <span
                  v-if="session.guests_can_join"
                  class="rounded-md bg-[#f8fafc] px-2.5 py-1 text-[#0f1728]"
                >
                  Guests allowed
                </span>
              </div>

              <p
                v-if="session.description ?? session.notes"
                class="mt-3 text-sm text-[#64748b]"
              >
                {{ session.description ?? session.notes }}
              </p>
            </div>

            <div class="flex items-center gap-2">
              <UButton
                :color="isCancelledSession(session) ? 'neutral' : 'primary'"
                variant="outline"
                :icon="
                  isCancelledSession(session)
                    ? 'i-heroicons-eye'
                    : 'i-heroicons-pencil-square'
                "
                @click="openManage(session.id)"
              >
                {{ isCancelledSession(session) ? 'View Details' : 'Manage' }}
              </UButton>
            </div>
          </div>
        </UCard>
      </div>
    </div>

    <BookingWalkInModal
      v-model:open="isCreateOpen"
      :hub-id="hubId"
      :hub-timezone="hubData?.timezone"
      :courts="hubCourts"
      :operating-hours="hubData?.operating_hours ?? []"
      mode="openplay"
      @openplay:created="onSessionCreated"
    />

    <OpenPlayOwnerModal
      v-if="selectedSessionId"
      v-model:open="isManageOpen"
      :hub-id="hubId"
      :hub-timezone="hubData?.timezone"
      :session-id="selectedSessionId"
      :courts="hubCourts"
      :operating-hours="hubData?.operating_hours ?? []"
      @updated="onSessionUpdated"
    />
  </div>
</template>
