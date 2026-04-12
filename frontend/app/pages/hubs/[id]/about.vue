<script setup lang="ts">
import type { Hub, Court } from '~/types/hub';
import type { CalendarBooking } from '~/types/booking';
import type { OpenPlaySession } from '~/types/openPlay';
import { useAuthStore } from '~/stores/auth';
import OpenPlayJoinModal from '~/components/openPlay/OpenplayJoinModal.vue';

definePageMeta({ layout: 'hub' });

const route = useRoute();
const { fetchCourts } = useHubs();
const authStore = useAuthStore();
const toast = useToast();

const hubId = computed(() => String(route.params.id ?? ''));
const { data: hub } = useNuxtData<Hub>(`hub-${hubId.value}`);

const { data: courts, error } = await useAsyncData<Court[]>(
  `hub-courts-${hubId.value}`,
  () => fetchCourts(hubId.value)
);

const isOwner = computed(() => authStore.user?.id === hub.value?.owner_id);

const {
  selectedDate,
  bookingsMap,
  openPlaySessions,
  scheduleLoading,
  currentDayGroups,
  selectedDateGroups,
  showCurrentDayNoticeSection,
  showSelectedDateNoticeSection,
  filteredOpenPlaySessionsMap,
  gridMinTime,
  gridMaxTime,
  handleOpenPlayUpdated
} = useHubSchedule({
  hubId,
  hub,
  courts
});

const { selectedSlots, onSlotClick, clearSlots, removeSlots } =
  useHubSlotSelection({
    timeZone: computed(() => hub.value?.timezone)
  });

const filteredCourtId = ref<string | null>(null);
const drawerOpen = ref(false);

const filteredCourts = computed(() =>
  filteredCourtId.value
    ? (courts.value ?? []).filter((court) => court.id === filteredCourtId.value)
    : (courts.value ?? [])
);

const filteredCourtName = computed(
  () => courts.value?.find((court) => court.id === filteredCourtId.value)?.name ?? ''
);

const currentDayClosureEvents = computed(
  () => currentDayGroups.closureEvents.value
);
const currentDayAnnouncementEvents = computed(
  () => currentDayGroups.announcementEvents.value
);
const currentDayPromoEvents = computed(() => currentDayGroups.promoEvents.value);
const currentDayVoucherAnnouncementEvents = computed(
  () => currentDayGroups.voucherAnnouncementEvents.value
);
const selectedClosureEvents = computed(
  () => selectedDateGroups.closureEvents.value
);
const selectedAnnouncementEvents = computed(
  () => selectedDateGroups.announcementEvents.value
);
const selectedPromoEvents = computed(() => selectedDateGroups.promoEvents.value);
const selectedVoucherAnnouncementEvents = computed(
  () => selectedDateGroups.voucherAnnouncementEvents.value
);

function mergeUniqueEvents<T extends { id: string }>(...eventGroups: T[][]): T[] {
  const seen = new Set<string>();

  return eventGroups.flat().filter((event) => {
    if (seen.has(event.id)) return false;
    seen.add(event.id);
    return true;
  });
}

const effectivePromoEvents = computed(() =>
  mergeUniqueEvents(currentDayPromoEvents.value, selectedPromoEvents.value)
);

function scrollToSchedule(): void {
  const element = document.getElementById('schedule');
  if (!element) return;

  const offset = 140;
  const top = element.getBoundingClientRect().top + window.scrollY - offset;
  window.scrollTo({ top, behavior: 'smooth' });
}

function bookThisCourt(court: Court): void {
  filteredCourtId.value = court.id;
  clearSlots();
  scrollToSchedule();
}

async function copyVoucherCode(code: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(code);
    toast.add({ title: 'Voucher code copied', color: 'success' });
  } catch {
    toast.add({ title: 'Failed to copy voucher code', color: 'error' });
  }
}

const receiptModalOpen = ref(false);
const pendingReceiptBooking = ref<CalendarBooking | null>(null);
const pendingReceiptCourtId = ref<string | null>(null);
const pendingReceiptCourtName = ref('');

function onOwnBookingClick({
  booking,
  court
}: {
  booking: CalendarBooking;
  court: Court;
}): void {
  pendingReceiptBooking.value = booking;
  pendingReceiptCourtId.value = court.id;
  pendingReceiptCourtName.value = court.name;
  receiptModalOpen.value = true;
}

function onBookingCreated(): void {
  // The websocket listener inside useHubSchedule refreshes the grid state.
}

const selectedOpenPlaySessionId = ref<string | null>(null);
const openPlayModalOpen = ref(false);

const selectedOpenPlaySession = computed<OpenPlaySession | null>(
  () =>
    openPlaySessions.value.find(
      (session) => session.id === selectedOpenPlaySessionId.value
    ) ?? null
);

function openOpenPlaySession(session: OpenPlaySession): void {
  selectedOpenPlaySessionId.value = session.id;
  openPlayModalOpen.value = true;
}
</script>

<template>
  <div>
    <div
      v-if="error"
      class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6"
    >
      <p class="text-[var(--aktiv-muted)]">Failed to load hub details.</p>
    </div>

    <template v-else-if="hub">
      <div
        class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2 xl:grid-cols-[2fr_2fr_minmax(320px,1.2fr)]"
      >
        <div class="space-y-6">
          <HubAboutDetailsCard :hub="hub" :is-owner="isOwner" />
          <HubAboutCourtsSection
            :courts="courts ?? []"
            @book-court="bookThisCourt"
          />
        </div>

        <HubAboutSchedulePanel
          v-if="courts && courts.length > 0"
          :hub="hub"
          :courts="courts"
          :selected-date="selectedDate"
          :bookings-map="bookingsMap"
          :selected-slots="selectedSlots"
          :schedule-loading="scheduleLoading"
          :filtered-courts="filteredCourts"
          :filtered-court-id="filteredCourtId"
          :filtered-court-name="filteredCourtName"
          :open-play-sessions-map="filteredOpenPlaySessionsMap"
          :current-day-closure-events="currentDayClosureEvents"
          :current-day-announcement-events="currentDayAnnouncementEvents"
          :current-day-promo-events="currentDayPromoEvents"
          :current-day-voucher-announcement-events="currentDayVoucherAnnouncementEvents"
          :selected-closure-events="selectedClosureEvents"
          :selected-announcement-events="selectedAnnouncementEvents"
          :selected-promo-events="selectedPromoEvents"
          :selected-voucher-announcement-events="selectedVoucherAnnouncementEvents"
          :show-current-day-notice-section="showCurrentDayNoticeSection"
          :show-selected-date-notice-section="showSelectedDateNoticeSection"
          :grid-min-time="gridMinTime"
          :grid-max-time="gridMaxTime"
          @update:selected-date="selectedDate = $event"
          @slot-click="onSlotClick"
          @own-booking-click="onOwnBookingClick"
          @open-play-click="openOpenPlaySession"
          @clear-court-filter="filteredCourtId = null"
          @copy-voucher-code="copyVoucherCode"
        />

        <div class="hidden xl:block xl:sticky xl:top-[160px]">
          <div
            v-if="!courts || courts.length === 0"
            class="rounded-2xl border border-dashed border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-8 text-center"
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
          <SchedulerBookingSummary
            v-else
            :selected-slots="selectedSlots"
            :courts="courts ?? []"
            :hub-id="hubId"
            :hub="hub ?? null"
            :promo-events="effectivePromoEvents"
            @booking-created="onBookingCreated"
            @clear="clearSlots"
            @remove-slots="removeSlots"
          />
        </div>
      </div>

      <SchedulerReceiptUploadModal
        v-model:open="receiptModalOpen"
        :booking="pendingReceiptBooking"
        :hub-id="hubId"
        :court-id="String(pendingReceiptCourtId ?? '')"
        :court-name="pendingReceiptCourtName"
        @receipt-uploaded="onBookingCreated"
      />

      <OpenPlayJoinModal
        v-model:open="openPlayModalOpen"
        :hub-id="hubId"
        :hub="hub ?? null"
        :session="selectedOpenPlaySession"
        @updated="handleOpenPlayUpdated"
      />

      <HubAboutMobileBookingBar
        v-model:open="drawerOpen"
        :selected-slots="selectedSlots"
        :courts="courts ?? []"
        :hub-id="hubId"
        :hub="hub ?? null"
        :promo-events="effectivePromoEvents"
        @scroll-to-schedule="scrollToSchedule"
        @booking-created="onBookingCreated"
        @clear="clearSlots"
        @remove-slots="removeSlots"
      />
    </template>

    <template v-else>
      <USkeleton class="h-[600px] w-full rounded-2xl" />
    </template>
  </div>
</template>
