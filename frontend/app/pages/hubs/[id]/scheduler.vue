<script setup lang="ts">
import type { Hub, Court } from '~/types/hub';
import type { CalendarBooking, SelectedSlot } from '~/types/booking';

definePageMeta({ layout: 'hub' });

const route = useRoute();
const { fetchCourts } = useHubs();
const { fetchBookings } = useBooking();

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
  const entries = await Promise.all(
    courts.value.map(async (court) => {
      try {
        const bookings = await fetchBookings(hubId.value, court.id, {
          date_from: dateStr,
          date_to: dateStr
        });
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

watch(selectedDate, () => loadAllBookings());

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
