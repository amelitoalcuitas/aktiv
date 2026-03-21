<script setup lang="ts">
import type { Hub, Court } from '~/types/hub';
import type { CalendarBooking, SelectedSlot } from '~/types/booking';

definePageMeta({ layout: 'hub' });

const _route = useRoute();
await navigateTo(`/hubs/${_route.params.id}/about`, { replace: true });

const route = useRoute();
const { fetchCourts } = useHubs();
const { fetchHubBookings } = useBooking();

const hubId = computed(() => String(route.params.id ?? ''));

// Hub is already fetched by HubProfileHeader with this key — just read the cache.
const { data: hub } = useNuxtData<Hub>(`hub-${hubId.value}`);

const { data: courts } = await useAsyncData<Court[]>(
  `hub-courts-${hubId.value}`,
  () => fetchCourts(hubId.value)
);

// ── Selected date (drives both mini calendar and resource grid) ─
const selectedDate = ref(new Date());

// ── Bookings map keyed by court.id ─────────────────────────────
const bookingsMap = ref<Record<number, CalendarBooking[]>>({});

function formatDateString(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

async function loadAllBookings() {
  if (!courts.value || courts.value.length === 0) return;
  const dateStr = formatDateString(selectedDate.value);
  try {
    bookingsMap.value = await fetchHubBookings(hubId.value, {
      date_from: dateStr,
      date_to: dateStr
    });
  } catch {
    bookingsMap.value = {};
  }
}

watch(
  () => courts.value,
  () => loadAllBookings(),
  { immediate: true }
);

watch(selectedDate, () => loadAllBookings());

// ── Multi-slot selection ────────────────────────────────────────
const selectedSlots = ref<SelectedSlot[]>([]);

function onSlotClick({ court, date }: { court: Court; date: Date }) {
  const t = date.getTime();
  const existing = selectedSlots.value;
  const HOUR = 3_600_000;

  if (existing.length === 0 || existing[0]!.courtId !== court.id) {
    selectedSlots.value = [{ courtId: court.id, slotStart: date }];
    return;
  }

  const sorted = [...existing].sort((a, b) => a.slotStart.getTime() - b.slotStart.getTime());
  const minT = sorted[0]!.slotStart.getTime();
  const maxT = sorted[sorted.length - 1]!.slotStart.getTime();

  if (t >= minT && t <= maxT) {
    if (t === minT) {
      selectedSlots.value = sorted.slice(1);
    } else if (t === maxT) {
      selectedSlots.value = sorted.slice(0, -1);
    } else {
      selectedSlots.value = [{ courtId: court.id, slotStart: date }];
    }
    return;
  }

  if (t === minT - HOUR) {
    selectedSlots.value = [{ courtId: court.id, slotStart: date }, ...sorted];
    return;
  }

  if (t === maxT + HOUR) {
    selectedSlots.value = [...sorted, { courtId: court.id, slotStart: date }];
    return;
  }

  selectedSlots.value = [{ courtId: court.id, slotStart: date }];
}

function onClearSlots() {
  selectedSlots.value = [];
}

function onRemoveSlots(slots: SelectedSlot[]) {
  const keys = new Set(
    slots.map((s) => `${s.courtId}-${s.slotStart.getTime()}`)
  );
  selectedSlots.value = selectedSlots.value.filter(
    (s) => !keys.has(`${s.courtId}-${s.slotStart.getTime()}`)
  );
}

function onBookingCreated() {
  // The websocket event (booking.slot.updated) triggers loadAllBookings already.
  // No manual reload needed here to avoid a duplicate fetch.
}

// ── Real-time slot updates via websocket ───────────────────────
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let hubChannel: any = null;

onMounted(() => {
  const { $echo } = useNuxtApp();
  if (!$echo) return;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const echo = $echo as any;
  // Ensure connection is up (the Echo plugin disconnects until auth; public
  // channels work without auth but still need the transport connected).
  echo.connector.pusher.connection.connect();
  hubChannel = echo.channel(`hub.${hubId.value}`);
  hubChannel.listen('.booking.slot.updated', () => {
    loadAllBookings();
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

// ── Receipt upload (own pending_payment slots) ─────────────────
const receiptModalOpen = ref(false);
const pendingReceiptBooking = ref<CalendarBooking | null>(null);
const pendingReceiptCourtId = ref<number | null>(null);
const pendingReceiptCourtName = ref('');

function onOwnBookingClick({
  booking,
  court
}: {
  booking: CalendarBooking;
  court: Court;
}) {
  pendingReceiptBooking.value = booking;
  pendingReceiptCourtId.value = court.id;
  pendingReceiptCourtName.value = court.name;
  receiptModalOpen.value = true;
}

// ── Operating hours → grid bounds (widest range across all open days) ──
const gridMinTime = computed(() => {
  const oh = hub.value?.operating_hours;
  if (!oh?.length) return '06:00';
  const open = oh.filter((e) => !e.is_closed);
  if (!open.length) return '06:00';
  return open.reduce((min, e) => (e.opens_at < min ? e.opens_at : min), open[0]!.opens_at);
});

const gridMaxTime = computed(() => {
  const oh = hub.value?.operating_hours;
  if (!oh?.length) return '23:00';
  const open = oh.filter((e) => !e.is_closed);
  if (!open.length) return '23:00';
  return open.reduce((max, e) => (e.closes_at > max ? e.closes_at : max), open[0]!.closes_at);
});
</script>

<template>
  <div class="space-y-4">
    <!-- No courts empty state -->
    <div
      v-if="!courts || courts.length === 0"
      class="rounded-2xl border border-dashed border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-12 text-center"
    >
      <UIcon
        name="i-heroicons-squares-2x2"
        class="mx-auto h-10 w-10 text-[var(--aktiv-border)]"
      />
      <h3 class="mt-3 text-sm font-semibold text-[var(--aktiv-ink)]">
        No courts available
      </h3>
      <p class="mt-1 text-sm text-[var(--aktiv-muted)]">
        This hub hasn't added any courts yet.
      </p>
    </div>

    <template v-else>
      <!-- ① Contact notice -->
      <SchedulerContactNotice
        :contact-numbers="hub?.contact_numbers ?? []"
        :websites="hub?.websites ?? []"
      />

      <!-- ② Resource grid + floating booking summary sidebar -->
      <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[1fr_320px]">
        <SchedulerResourceGrid
          :courts="courts ?? []"
          :bookings-map="bookingsMap"
          :selected-date="selectedDate"
          :selected-slots="selectedSlots"
          :min-time="gridMinTime"
          :max-time="gridMaxTime"
          :operating-hours="hub?.operating_hours ?? []"
          @slot-click="onSlotClick"
          @update:selected-date="selectedDate = $event"
          @own-booking-click="onOwnBookingClick"
        />
        <!-- ③ Booking summary: sticky floating sidebar on desktop -->
        <div class="lg:sticky lg:top-4">
          <SchedulerBookingSummary
            :selected-slots="selectedSlots"
            :courts="courts ?? []"
            :hub-id="hubId"
            :hub="hub ?? null"
            @booking-created="onBookingCreated"
            @clear="onClearSlots"
            @remove-slots="onRemoveSlots"
          />
        </div>
      </div>
    </template>

    <SchedulerReceiptUploadModal
      v-model:open="receiptModalOpen"
      :booking="pendingReceiptBooking"
      :hub-id="hubId"
      :court-id="String(pendingReceiptCourtId ?? '')"
      :court-name="pendingReceiptCourtName"
      @receipt-uploaded="onBookingCreated"
    />
  </div>
</template>
