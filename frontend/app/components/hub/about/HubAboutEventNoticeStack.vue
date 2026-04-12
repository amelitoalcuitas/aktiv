<script setup lang="ts">
import type { Court, HubEvent } from '~/types/hub';

const props = withDefaults(
  defineProps<{
    heading?: string;
    showHeading?: boolean;
    closureEvents: HubEvent[];
    announcementEvents: HubEvent[];
    promoEvents: HubEvent[];
    voucherAnnouncementEvents: HubEvent[];
    courts?: Court[];
    timezone?: string | null;
    closurePrefix: string;
    announcementPrefix?: string;
    voucherPrefix: string;
  }>(),
  {
    heading: '',
    showHeading: false,
    courts: () => [],
    timezone: null,
    announcementPrefix: 'Applies'
  }
);

const emit = defineEmits<{
  (e: 'copy-voucher-code', code: string): void;
}>();

function formatEventDateRange(event: HubEvent): string {
  const dateFrom = formatInHubTimezone(
    event.start_time,
    {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    },
    'en-PH',
    props.timezone
  );
  const dateTo = formatInHubTimezone(
    event.end_time,
    {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    },
    'en-PH',
    props.timezone
  );

  if (dateFrom === dateTo) {
    return `on ${dateFrom}`;
  }

  return `from ${dateFrom} - ${dateTo}`;
}

function formatEventTimeRange(event: HubEvent): string {
  return `${formatInHubTimezone(
    event.start_time,
    {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true
    },
    'en-PH',
    props.timezone
  )} - ${formatInHubTimezone(
    event.end_time,
    {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true
    },
    'en-PH',
    props.timezone
  )}`;
}

function voucherAvailabilityLabel(event: HubEvent): string {
  return `${props.voucherPrefix} ${formatEventDateRange(event)} · ${formatEventTimeRange(event)}`;
}
</script>

<template>
  <div class="space-y-3">
    <div v-if="showHeading" class="flex items-center gap-2">
      <UIcon
        name="i-heroicons-bell-alert"
        class="h-4 w-4 text-[var(--aktiv-primary)]"
      />
      <p class="text-sm font-semibold text-[var(--aktiv-ink)]">
        {{ heading }}
      </p>
    </div>

    <div
      v-for="event in closureEvents"
      :key="event.id"
      class="rounded-xl border border-[#fecaca] bg-[#fef2f2] px-4 py-3"
    >
      <p class="font-semibold text-[#991b1b]">
        <UIcon name="i-heroicons-x-circle" class="mr-1 inline h-4 w-4" />
        {{ event.title }}
      </p>
      <p v-if="event.description" class="mt-0.5 text-sm text-[#b91c1c]">
        {{ event.description }}
      </p>
      <p class="mt-0.5 text-sm text-[#b91c1c]">
        {{ closurePrefix }} {{ formatEventDateRange(event) }} ·
        {{ formatEventTimeRange(event) }}
      </p>
    </div>

    <div
      v-for="event in announcementEvents"
      :key="event.id"
      class="rounded-xl border border-[#bfdbfe] bg-[#eff6ff] px-4 py-3"
    >
      <p class="font-semibold text-[#1e40af]">{{ event.title }}</p>
      <p v-if="event.description" class="mt-0.5 text-sm text-[#1d4ed8]">
        {{ event.description }}
      </p>
      <p
        v-if="!showHeading"
        class="mt-1 text-sm text-[#1d4ed8]"
      >
        {{ announcementPrefix }} {{ formatEventDateRange(event) }} ·
        {{ formatEventTimeRange(event) }}
      </p>
    </div>

    <HubPromoAlert
      v-for="event in promoEvents"
      :key="event.id"
      :event="event"
      :courts="courts"
      :timezone="timezone"
    />

    <HubAboutVoucherNotice
      v-for="event in voucherAnnouncementEvents"
      :key="event.id"
      :event="event"
      :timezone="timezone"
      :availability-label="voucherAvailabilityLabel(event)"
      @copy-voucher-code="emit('copy-voucher-code', $event)"
    />
  </div>
</template>
