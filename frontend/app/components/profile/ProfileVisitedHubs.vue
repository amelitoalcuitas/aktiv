<script setup lang="ts">
import type { VisitedHub } from '~/types/user';

const props = defineProps<{
  hidden?: boolean;
  editing?: boolean;
  showEye?: boolean;
  userId?: string; // present when viewing a public profile
}>();

const emit = defineEmits<{
  togglePrivacy: [val: boolean];
}>();

const { fetchMostVisitedHubs, fetchPublicMostVisitedHubs } = useProfile();

const hubs = ref<VisitedHub[]>([]);
const loading = ref(false);
const fetched = ref(false);

async function load() {
  if (fetched.value || loading.value) return;
  loading.value = true;
  try {
    hubs.value = props.userId
      ? await fetchPublicMostVisitedHubs(props.userId)
      : await fetchMostVisitedHubs();
    fetched.value = true;
  } finally {
    loading.value = false;
  }
}

// Fetch on mount only if visible
onMounted(() => {
  if (!props.hidden) load();
});

// Lazy-load when card becomes visible after being hidden
watch(
  () => props.hidden,
  (nowHidden) => {
    if (!nowHidden) load();
  }
);
</script>

<template>
  <div class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4">
    <div class="mb-3 flex items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <h3 class="text-lg font-bold text-[var(--aktiv-ink)]">Most Visited Hubs</h3>
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

    <!-- Skeleton -->
    <ul v-if="loading" class="divide-y divide-[var(--aktiv-border)]">
      <li v-for="i in 3" :key="i" class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
        <USkeleton class="h-12 w-12 shrink-0 rounded-md" />
        <div class="flex-1 space-y-1.5">
          <USkeleton class="h-3.5 w-2/3 rounded" />
          <USkeleton class="h-3 w-1/3 rounded" />
        </div>
      </li>
    </ul>

    <!-- Empty state -->
    <div
      v-else-if="fetched && hubs.length === 0"
      class="rounded-md bg-[var(--aktiv-border)]/30 px-4 py-6 text-center"
    >
      <UIcon name="i-heroicons-map-pin" class="mx-auto mb-2 h-6 w-6 text-[var(--aktiv-muted)] opacity-50" />
      <p class="text-sm text-[var(--aktiv-muted)]">No visited hubs yet.</p>
      <p class="mt-0.5 text-xs text-[var(--aktiv-muted)] opacity-70">
        Completed bookings will appear here.
      </p>
    </div>

    <!-- Hub list -->
    <ul v-else-if="hubs.length > 0" class="divide-y divide-[var(--aktiv-border)]">
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
          <UBadge
            :label="`Visited ${hub.visit_count} ${hub.visit_count === 1 ? 'time' : 'times'}`"
            color="neutral"
            variant="soft"
            size="sm"
            class="shrink-0"
          />
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
