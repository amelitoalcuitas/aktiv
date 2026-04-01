<script setup lang="ts">
import type { OpenPlaySession } from '~/types/openPlay';

const props = defineProps<{
  session: OpenPlaySession;
}>();

const emit = defineEmits<{
  open: [sessionId: string];
}>();

function formatDate(session: OpenPlaySession): string {
  if (!session.booking) return '';

  const start = new Date(session.booking.start_time);
  const end = new Date(session.booking.end_time);

  return `${session.booking.court?.name ?? 'Court'} · ${start.toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    weekday: 'short',
    month: 'short',
    day: 'numeric'
  })} · ${start.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  })} – ${end.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  })}`;
}

const ctaLabel = computed(() => {
  if (props.session.viewer_participant?.payment_status === 'confirmed') return 'Joined';
  if (props.session.viewer_participant?.payment_status === 'payment_sent') return 'Under Review';
  if (props.session.viewer_participant?.payment_status === 'pending_payment') {
    return props.session.viewer_participant.payment_method === 'digital_bank'
      ? 'Upload Receipt'
      : 'Pending Payment';
  }
  if (props.session.status === 'full') return 'Full';
  return 'Join';
});

const badgeColor = computed(() => {
  if (props.session.viewer_participant?.payment_status === 'confirmed') return 'success';
  if (props.session.status === 'full') return 'warning';
  return 'primary';
});
</script>

<template>
  <UCard
    class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
    :ui="{ root: 'ring-0', body: 'p-5' }"
  >
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
      <div class="min-w-0 flex-1">
        <div class="flex items-start gap-3">
          <div class="rounded-xl bg-[var(--aktiv-primary)]/10 p-3 text-[var(--aktiv-primary)]">
            <UIcon name="i-heroicons-user-group" class="h-5 w-5" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-base font-bold text-[var(--aktiv-ink)]">
                {{ session.sport ?? 'Open Play' }}
              </h3>
              <UBadge :color="badgeColor" variant="soft">
                {{ ctaLabel }}
              </UBadge>
            </div>
            <p class="mt-1 text-sm text-[var(--aktiv-muted)]">
              {{ formatDate(session) }}
            </p>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 text-sm">
          <span class="rounded-md bg-[var(--aktiv-background)] px-2.5 py-1 text-[var(--aktiv-ink)]">
            {{ session.participants_count }} / {{ session.max_players }} players
          </span>
          <span class="rounded-md bg-[var(--aktiv-background)] px-2.5 py-1 text-[var(--aktiv-ink)]">
            {{ Number(session.price_per_player) === 0 ? 'Free' : `P${Number(session.price_per_player).toLocaleString('en-PH')} / player` }}
          </span>
          <span
            v-if="session.guests_can_join"
            class="rounded-md bg-[var(--aktiv-background)] px-2.5 py-1 text-[var(--aktiv-ink)]"
          >
            Guests allowed
          </span>
        </div>

        <p
          v-if="session.notes"
          class="mt-3 text-sm text-[var(--aktiv-muted)]"
        >
          "{{ session.notes }}"
        </p>
      </div>

      <div class="md:pl-4">
        <UButton
          color="primary"
          :variant="session.status === 'full' && !session.viewer_participant ? 'soft' : 'solid'"
          @click="emit('open', session.id)"
        >
          {{ ctaLabel }}
        </UButton>
      </div>
    </div>
  </UCard>
</template>
