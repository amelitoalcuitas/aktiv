<script setup lang="ts">
import type { Hub, Court } from '~/types/hub';
import type { CalendarBooking, SelectedSlot } from '~/types/booking';

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

// ── Selected date (drives both mini calendar and resource grid) ─
const selectedDate = ref(new Date());

// ── Bookings map keyed by court.id ─────────────────────────────
const bookingsMap = ref<Record<number, CalendarBooking[]>>({});

async function loadAllBookings() {
  if (!courts.value || courts.value.length === 0) return;
  const entries = await Promise.all(
    courts.value.map(async (court) => {
      try {
        const bookings = await fetchBookings(hubId.value, court.id);
        return [court.id, bookings] as [number, CalendarBooking[]];
      } catch {
        return [court.id, []] as [number, CalendarBooking[]];
      }
    })
  );
  bookingsMap.value = Object.fromEntries(entries);
}

watch(
  () => courts.value,
  () => loadAllBookings(),
  { immediate: true }
);

// ── Multi-slot selection ────────────────────────────────────────
const selectedSlots = ref<SelectedSlot[]>([]);

function onSlotClick({ court, date }: { court: Court; date: Date }) {
  const t = date.getTime();
  const idx = selectedSlots.value.findIndex(
    (s) => s.courtId === court.id && s.slotStart.getTime() === t
  );
  if (idx >= 0) {
    selectedSlots.value.splice(idx, 1);
  } else {
    selectedSlots.value.push({ courtId: court.id, slotStart: date });
  }
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
  loadAllBookings();
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

      <!-- ② Two-panel layout: mini calendar + resource grid -->
      <div
        class="grid grid-cols-1 gap-4 lg:grid-cols-[280px_1fr] lg:items-start"
      >
        <SchedulerMiniCalendar v-model="selectedDate" />
        <SchedulerResourceGrid
          :courts="courts ?? []"
          :bookings-map="bookingsMap"
          :selected-date="selectedDate"
          :selected-slots="selectedSlots"
          @slot-click="onSlotClick"
          @update:selected-date="selectedDate = $event"
        />
      </div>

      <!-- ③ Booking summary card -->
      <SchedulerBookingSummary
        :selected-slots="selectedSlots"
        :courts="courts ?? []"
        :hub-id="hubId"
        @booking-created="onBookingCreated"
        @clear="onClearSlots"
        @remove-slots="onRemoveSlots"
      />
    </template>
  </div>
</template>
