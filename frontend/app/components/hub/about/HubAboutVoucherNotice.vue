<script setup lang="ts">
import type { HubEvent } from '~/types/hub';

const props = defineProps<{
  event: HubEvent;
  timezone?: string | null;
  availabilityLabel: string;
}>();

const emit = defineEmits<{
  (e: 'copy-voucher-code', code: string): void;
}>();
</script>

<template>
  <div class="rounded-xl border border-[#bbf7d0] bg-[#f0fdf4] px-4 py-3">
    <div class="flex items-start gap-3">
      <div
        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#dcfce7]"
      >
        <UIcon name="i-heroicons-ticket" class="h-5 w-5 text-[#166534]" />
      </div>

      <div class="min-w-0 flex-1">
        <p class="font-semibold text-[#166534]">
          {{ event.title || 'Voucher Available' }}
        </p>
        <p v-if="event.description" class="mt-0.5 text-sm text-[#15803d]">
          {{ event.description }}
        </p>
        <p class="mt-1 text-sm text-[#15803d]">
          {{ availabilityLabel }}
        </p>
        <div
          v-if="event.voucher_code"
          class="mt-1 flex flex-wrap items-center gap-2 text-sm font-medium text-[#166534]"
        >
          <span>Voucher code: {{ event.voucher_code }}</span>
          <UButton
            size="xs"
            variant="ghost"
            color="success"
            icon="i-heroicons-clipboard-document"
            @click="emit('copy-voucher-code', event.voucher_code)"
          />
        </div>
      </div>
    </div>
  </div>
</template>
