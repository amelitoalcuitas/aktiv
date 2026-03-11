<script setup lang="ts">
import type { EventInput } from '@fullcalendar/core';
import type { Hub, Court } from '~/types/hub';

definePageMeta({ layout: 'hub' });

const route = useRoute();
const { fetchHub, fetchCourts } = useHubs();
const { fetchBookings } = useBooking();

const hubId = computed(() => String(route.params.id ?? ''));

const [{ data: hub }, { data: courts }] = await Promise.all([
  useAsyncData<Hub>(`hub-${hubId.value}`, () => fetchHub(hubId.value)),
  useAsyncData<Court[]>(`hub-courts-${hubId.value}`, () =>
    fetchCourts(hubId.value)
  )
]);

// ── Active court ───────────────────────────────────────────────
const activeCourt = ref<Court | null>(null);

const queryCourtId = computed(() => {
  const raw = route.query.courtId;
  return raw ? Number(raw) : null;
});

watch(
  () => courts.value,
  (list) => {
    if (!list || list.length === 0) return;
    // Honour ?courtId= query param; fall back to first court
    const preferred = queryCourtId.value
      ? (list.find((c) => c.id === queryCourtId.value) ?? list[0])
      : list[0];
    activeCourt.value = preferred ?? null;
  },
  { immediate: true }
);

// Calendar events (bookings) shown on the FullCalendar grid
const calendarEvents = ref<EventInput[]>([]);

function statusColor(status: string): string {
  switch (status) {
    case 'pending_payment':
    case 'payment_sent':
      return '#f59e0b'; // amber - awaiting confirmation
    case 'confirmed':
      return '#ef4444'; // red - fully booked
    default:
      return '#94a3b8'; // slate - completed/other
  }
}

async function loadBookings(courtId: number) {
  try {
    const bookings = await fetchBookings(hubId.value, courtId);
    calendarEvents.value = bookings.map((b) => ({
      id: String(b.id),
      start: b.start_time,
      end: b.end_time,
      title:
        b.session_type === 'open_play'
          ? 'Open Play'
          : b.is_own
            ? 'Your Booking'
            : 'Reserved',
      backgroundColor: statusColor(b.status),
      borderColor: statusColor(b.status),
      textColor: '#ffffff',
      extendedProps: { status: b.status, is_own: b.is_own }
    }));
  } catch {
    calendarEvents.value = [];
  }
}

watch(
  activeCourt,
  (court) => {
    if (court) loadBookings(court.id);
    else calendarEvents.value = [];
  },
  { immediate: true }
);

function onBookingCreated() {
  if (activeCourt.value) loadBookings(activeCourt.value.id);
}
const isMounted = ref(false);
onMounted(() => {
  isMounted.value = true;
});

const bookingModalOpen = ref(false);
const bookingClickedDate = ref<Date | null>(null);

function onSlotClick({ date }: { date: Date; dateStr: string }) {
  bookingClickedDate.value = date;
  bookingModalOpen.value = true;
}

// ── Price formatting ───────────────────────────────────────────
function formatPrice(price: string | null) {
  if (!price) return null;
  const n = parseFloat(price);
  return isNaN(n)
    ? null
    : n.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
}
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
      <p class="mt-1 text-xs text-[var(--aktiv-muted)]">
        This hub hasn't added any courts yet.
      </p>
    </div>

    <template v-else>
      <!-- ① Contact notice -->
      <SchedulerContactNotice
        :contact-numbers="hub?.contact_numbers ?? []"
        :websites="hub?.websites ?? []"
      />

      <!-- ② Court tabs -->
      <div class="flex gap-2 overflow-x-auto pb-0.5">
        <button
          v-for="court in courts"
          :key="court.id"
          type="button"
          :class="[
            'whitespace-nowrap rounded-lg border px-4 py-2 text-sm font-medium transition-colors',
            activeCourt?.id === court.id
              ? 'border-[var(--aktiv-primary)] bg-[var(--aktiv-primary)] text-white'
              : 'border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] text-[var(--aktiv-ink)] hover:bg-[var(--aktiv-border)]'
          ]"
          @click="activeCourt = court"
        >
          {{ court.name }}
        </button>
      </div>

      <!-- ③ Calendar panel for the active court -->
      <div
        v-if="activeCourt"
        class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] shadow-sm"
      >
        <!-- Court meta strip -->
        <div
          class="flex flex-wrap items-center gap-x-3 gap-y-1.5 border-b border-[var(--aktiv-border)] bg-[var(--aktiv-background)] px-5 py-3"
        >
          <span class="font-semibold text-[var(--aktiv-ink)]">
            {{ activeCourt.name }}
          </span>
          <UBadge color="neutral" variant="soft">
            {{ activeCourt.indoor ? 'Indoor' : 'Outdoor' }}
          </UBadge>
          <span
            v-if="formatPrice(activeCourt.price_per_hour)"
            class="text-sm text-[var(--aktiv-muted)]"
          >
            ₱{{ formatPrice(activeCourt.price_per_hour) }}/hr (Private)
          </span>
          <span
            v-if="
              activeCourt.open_play_price_per_head &&
              formatPrice(activeCourt.open_play_price_per_head)
            "
            class="text-sm text-[var(--aktiv-muted)]"
          >
            ₱{{ formatPrice(activeCourt.open_play_price_per_head) }}/head (Open
            Play)
          </span>
          <div class="flex flex-wrap gap-1">
            <UBadge
              v-for="sport in activeCourt.sports"
              :key="sport"
              color="primary"
              variant="soft"
              class="capitalize"
            >
              {{ sport }}
            </UBadge>
          </div>
        </div>

        <!-- FullCalendar -->
        <div class="p-4">
          <SchedulerCalendar
            :key="activeCourt.id"
            :events="calendarEvents"
            @slot-click="onSlotClick"
          />
        </div>
      </div>
    </template>

    <!-- Booking modal — v-if="isMounted" ensures server + initial client both render nothing,
         avoiding the SSR hydration mismatch that UModal's Teleport would otherwise cause -->
    <SchedulerBookingModal
      v-if="isMounted"
      v-model:open="bookingModalOpen"
      :court="activeCourt"
      :clicked-date="bookingClickedDate"
      :hub-id="hubId"
      @booking-created="onBookingCreated"
    />
  </div>
</template>
