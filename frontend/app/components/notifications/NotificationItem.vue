<script setup lang="ts">
import type { AppNotification, NotificationActivityType } from '~/types/notification';

const props = defineProps<{
  notification: AppNotification;
  clickable?: boolean;
}>();

const emit = defineEmits<{
  'toggle-read': [id: string];
  'open-booking': [
    itemId: string | undefined,
    bookingId: string | undefined,
    hubId: string | undefined,
    activityType: NotificationActivityType,
    sessionId: string | undefined
  ];
}>();

const iconMap: Record<string, string> = {
  booking_created: 'i-heroicons-calendar-days',
  receipt_uploaded: 'i-heroicons-document-arrow-up',
  booking_confirmed: 'i-heroicons-check-circle',
  booking_rejected: 'i-heroicons-x-circle',
  booking_cancelled: 'i-heroicons-no-symbol',
  booking_cancelled_by_guest: 'i-heroicons-no-symbol',
  open_play_receipt_uploaded: 'i-heroicons-document-arrow-up',
  open_play_participant_joined: 'i-heroicons-user-plus',
  open_play_participant_cancelled_by_customer: 'i-heroicons-arrow-uturn-left',
  open_play_participant_confirmed: 'i-heroicons-check-circle',
  open_play_participant_rejected: 'i-heroicons-x-circle',
  open_play_participant_cancelled: 'i-heroicons-no-symbol',
  open_play_session_cancelled: 'i-heroicons-no-symbol',
  open_play_session_started: 'i-heroicons-play-circle',
  open_play_session_updated: 'i-heroicons-pencil-square'
};

const iconColorMap: Record<string, string> = {
  booking_created: 'text-[#004e89]',
  receipt_uploaded: 'text-amber-500',
  booking_confirmed: 'text-green-500',
  booking_rejected: 'text-red-500',
  booking_cancelled: 'text-slate-400',
  booking_cancelled_by_guest: 'text-slate-400',
  open_play_receipt_uploaded: 'text-amber-500',
  open_play_participant_joined: 'text-[#004e89]',
  open_play_participant_cancelled_by_customer: 'text-slate-400',
  open_play_participant_confirmed: 'text-green-500',
  open_play_participant_rejected: 'text-red-500',
  open_play_participant_cancelled: 'text-slate-400',
  open_play_session_cancelled: 'text-slate-400',
  open_play_session_started: 'text-green-500',
  open_play_session_updated: 'text-[#004e89]'
};

const icon = computed(
  () => iconMap[props.notification.activity_type] ?? 'i-heroicons-bell'
);
const iconColor = computed(
  () => iconColorMap[props.notification.activity_type] ?? 'text-slate-400'
);
const isUnread = computed(() => props.notification.read_at === null);

function formatRelativeTime(iso: string): string {
  const diff = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  });
}

function handleClick() {
  if (!props.clickable) return;
  if (isUnread.value) emit('toggle-read', props.notification.id);
  emit(
    'open-booking',
    props.notification.data.item_id,
    props.notification.data.booking_id,
    props.notification.data.hub_id,
    props.notification.activity_type,
    props.notification.data.session_id
  );
}
</script>

<template>
  <div
    :class="[
      'group flex items-start gap-3 px-3 py-2.5 transition',
      isUnread ? 'bg-[#e8f0f8]' : 'bg-white',
      clickable ? 'cursor-pointer hover:bg-[#dbe8f5]' : 'hover:bg-[#f0f4f8]'
    ]"
    @click="handleClick"
  >
    <!-- Icon -->
    <div
      class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-white"
    >
      <UIcon :name="icon" :class="['h-4 w-4', iconColor]" />
    </div>

    <!-- Content -->
    <div class="min-w-0 flex-1">
      <p
        :class="[
          'text-sm leading-snug text-[#0f1728]',
          isUnread ? 'font-medium' : 'font-normal'
        ]"
      >
        {{ notification.data.message }}
      </p>
      <p class="mt-0.5 text-xs text-[#64748b]">
        {{ formatRelativeTime(notification.created_at) }}
      </p>
    </div>

    <!-- Unread dot + toggle -->
    <div class="flex flex-row items-center gap-2 self-center">
      <UButton
        :icon="isUnread ? 'i-heroicons-eye' : 'i-heroicons-eye-slash'"
        size="xs"
        class="invisible rounded-full text-[#64748b] hover:bg-white hover:text-[#004e89] group-hover:visible"
        :title="isUnread ? 'Mark as read' : 'Mark as unread'"
        variant="ghost"
        @click.stop="emit('toggle-read', notification.id)"
      />
      <span
        v-if="isUnread"
        class="inline-block h-2 w-2 rounded-full bg-[#004e89]"
      />
    </div>
  </div>
</template>
