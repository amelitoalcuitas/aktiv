<script setup lang="ts">
import type { JoinedHub } from '~/types/user';

defineProps<{
  hubs: JoinedHub[];
  hidden?: boolean;
  editing?: boolean;
  showEye?: boolean;
}>();

const emit = defineEmits<{
  togglePrivacy: [val: boolean];
}>();
</script>

<template>
  <div class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4">
    <div class="mb-3 flex items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <h3 class="text-lg font-bold text-[var(--aktiv-ink)]">Joined Hubs</h3>
        <UIcon
          v-if="editing || showEye"
          :name="hidden ? 'i-heroicons-eye-slash' : 'i-heroicons-eye'"
          class="h-5 w-5"
          :class="hidden ? 'text-[var(--aktiv-muted)]' : 'text-[var(--aktiv-primary)]'"
        />
      </div>
      <div v-if="editing" class="flex items-center gap-2">
        <span class="text-sm text-[var(--aktiv-muted)]">Visibility</span>
        <USwitch
          :model-value="!hidden"
          @update:model-value="(val) => emit('togglePrivacy', val)"
        />
      </div>
    </div>

    <div v-if="hubs.length === 0" class="rounded-md bg-[var(--aktiv-border)]/30 px-4 py-6 text-center">
      <UIcon name="i-heroicons-user-group" class="mx-auto mb-2 h-6 w-6 text-[var(--aktiv-muted)] opacity-50" />
      <p class="text-sm text-[var(--aktiv-muted)]">No joined hubs yet.</p>
    </div>

    <ul v-else class="divide-y divide-[var(--aktiv-border)]">
      <li
        v-for="hub in hubs"
        :key="hub.id"
        class="py-2.5 first:pt-0 last:pb-0"
      >
        <NuxtLink
          :to="hubPublicPath(hub, '/about')"
          class="flex items-center gap-3 rounded-md hover:opacity-80"
        >
          <div class="h-12 w-12 shrink-0 overflow-hidden rounded-md bg-[var(--aktiv-border)]">
            <img
              v-if="hub.cover_image_url"
              :src="hub.cover_image_url"
              :alt="hub.name"
              class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full w-full items-center justify-center">
              <UIcon name="i-heroicons-building-storefront" class="h-5 w-5 text-[var(--aktiv-muted)] opacity-50" />
            </div>
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-[var(--aktiv-ink)]">{{ hub.name }}</p>
            <p class="truncate text-xs text-[var(--aktiv-muted)]">{{ hub.city }}</p>
          </div>
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
