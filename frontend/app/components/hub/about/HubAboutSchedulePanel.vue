<script setup lang="ts">
import type { Court, Hub, HubEvent } from '~/types/hub';
import type { CalendarBooking, SelectedSlot } from '~/types/booking';
import type { OpenPlaySession } from '~/types/openPlay';

const emit = defineEmits<{
  (e: 'update:selectedDate', value: Date): void;
  (e: 'slot-click', payload: { court: Court; date: Date }): void;
  (e: 'own-booking-click', payload: { booking: CalendarBooking; court: Court }): void;
  (e: 'open-play-click', session: OpenPlaySession): void;
  (e: 'clear-court-filter'): void;
  (e: 'copy-voucher-code', code: string): void;
}>();

const props = defineProps<{
  hub: Hub;
  courts: Court[];
  selectedDate: Date;
  bookingsMap: Record<string, CalendarBooking[]>;
  selectedSlots: SelectedSlot[];
  scheduleLoading: boolean;
  filteredCourts: Court[];
  filteredCourtId: string | null;
  filteredCourtName: string;
  openPlaySessionsMap: Record<string, OpenPlaySession>;
  currentDayClosureEvents: HubEvent[];
  currentDayAnnouncementEvents: HubEvent[];
  currentDayPromoEvents: HubEvent[];
  currentDayVoucherAnnouncementEvents: HubEvent[];
  selectedClosureEvents: HubEvent[];
  selectedAnnouncementEvents: HubEvent[];
  selectedPromoEvents: HubEvent[];
  selectedVoucherAnnouncementEvents: HubEvent[];
  showCurrentDayNoticeSection: boolean;
  showSelectedDateNoticeSection: boolean;
  gridMinTime: string;
  gridMaxTime: string;
}>();

function mergeUniqueEvents(...eventGroups: HubEvent[][]): HubEvent[] {
  const seen = new Set<string>();

  return eventGroups.flat().filter((event) => {
    if (seen.has(event.id)) return false;
    seen.add(event.id);
    return true;
  });
}

const gridClosureEvents = computed(() =>
  mergeUniqueEvents(props.currentDayClosureEvents, props.selectedClosureEvents)
);

const gridPromoEvents = computed(() =>
  mergeUniqueEvents(props.currentDayPromoEvents, props.selectedPromoEvents)
);
</script>

<template>
  <div
    v-if="courts.length > 0"
    id="schedule"
    class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4 md:p-6"
  >
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <h2 class="text-base font-bold text-[var(--aktiv-ink)]">Schedule</h2>
      <div v-if="filteredCourtId" class="flex items-center gap-2">
        <span class="text-xs font-medium">Court Selected:</span>
        <UBadge
          color="primary"
          variant="subtle"
          class="cursor-pointer select-none"
          @click="emit('clear-court-filter')"
        >
          {{ filteredCourtName }}
          <UIcon name="i-heroicons-x-mark" class="ml-1 h-3.5 w-3.5" />
        </UBadge>
      </div>
    </div>

    <div v-if="showCurrentDayNoticeSection" class="mb-4">
      <HubAboutEventNoticeStack
        heading="Happening today"
        :show-heading="true"
        :closure-events="currentDayClosureEvents"
        :announcement-events="currentDayAnnouncementEvents"
        :promo-events="currentDayPromoEvents"
        :voucher-announcement-events="currentDayVoucherAnnouncementEvents"
        :courts="courts"
        :timezone="hub.timezone"
        closure-prefix="Closed today"
        voucher-prefix="Valid until"
        @copy-voucher-code="emit('copy-voucher-code', $event)"
      />
    </div>

    <div v-if="showSelectedDateNoticeSection" class="mb-4">
      <HubAboutEventNoticeStack
        :closure-events="selectedClosureEvents"
        :announcement-events="selectedAnnouncementEvents"
        :promo-events="selectedPromoEvents"
        :voucher-announcement-events="selectedVoucherAnnouncementEvents"
        :courts="courts"
        :timezone="hub.timezone"
        closure-prefix="Closed"
        announcement-prefix="Applies"
        voucher-prefix="Available"
        @copy-voucher-code="emit('copy-voucher-code', $event)"
      />
    </div>

    <div class="space-y-4">
      <SchedulerResourceGrid
        :courts="filteredCourts"
        :bookings-map="bookingsMap"
        :selected-date="selectedDate"
        :selected-slots="selectedSlots"
        :loading="scheduleLoading"
        :time-zone="hub.timezone"
        :open-play-sessions-map="openPlaySessionsMap"
        :min-time="gridMinTime"
        :max-time="gridMaxTime"
        :operating-hours="hub.operating_hours ?? []"
        :closure-events="gridClosureEvents"
        :promo-events="gridPromoEvents"
        @slot-click="emit('slot-click', $event)"
        @update:selected-date="emit('update:selectedDate', $event)"
        @own-booking-click="emit('own-booking-click', $event)"
        @open-play-click="emit('open-play-click', $event)"
      />
    </div>

    <HubAboutLocationMap :lat="hub.lat" :lng="hub.lng" />
  </div>
</template>
