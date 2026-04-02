<script setup lang="ts">
import type { OwnedHub } from '~/types/user';

const props = defineProps<{
  hubs: OwnedHub[];
  hidden?: boolean;
  editing?: boolean;
  showEye?: boolean;
  showPrivacyToggle?: boolean;
}>();

const emit = defineEmits<{
  togglePrivacy: [val: boolean];
  reorder: [ids: string[]];
  toggleHubVisibility: [id: string, val: boolean];
}>();

// Local reactive copy for drag reordering
const localHubs = ref<OwnedHub[]>([]);

watch(
  () => props.hubs,
  (hubs) => { localHubs.value = [...hubs]; },
  { immediate: true },
);

// Drag state
const dragIndex = ref<number | null>(null);

function onDragStart(index: number) {
  dragIndex.value = index;
}

function onDragOver(e: DragEvent, index: number) {
  e.preventDefault();
  if (dragIndex.value === null || dragIndex.value === index) return;
  const items = [...localHubs.value];
  const dragged = items.splice(dragIndex.value, 1)[0]!;
  items.splice(index, 0, dragged);
  localHubs.value = items;
  dragIndex.value = index;
}

function onDrop() {
  dragIndex.value = null;
  emit('reorder', localHubs.value.map((h) => h.id));
}

// In non-edit mode on own profile, hide hubs where show_on_profile is explicitly false
const visibleHubs = computed(() =>
  props.editing ? localHubs.value : localHubs.value.filter((h) => h.show_on_profile !== false),
);
</script>

<template>
  <div class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4">
    <div class="mb-3 flex items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <h3 class="text-lg font-bold text-[var(--aktiv-ink)]">Hubs Owned</h3>
        <UIcon
          v-if="editing || showEye"
          :name="hidden ? 'i-heroicons-eye-slash' : 'i-heroicons-eye'"
          class="h-5 w-5"
          :class="hidden ? 'text-[var(--aktiv-muted)]' : 'text-[var(--aktiv-primary)]'"
        />
      </div>
      <div v-if="editing && showPrivacyToggle !== false" class="flex items-center gap-2">
        <span class="text-sm text-[var(--aktiv-muted)]">Visibility</span>
        <USwitch
          :model-value="!hidden"
          @update:model-value="(val) => emit('togglePrivacy', val)"
        />
      </div>
    </div>

    <div v-if="visibleHubs.length === 0" class="rounded-md bg-[var(--aktiv-border)]/30 px-4 py-6 text-center">
      <UIcon name="i-heroicons-building-storefront" class="mx-auto mb-2 h-6 w-6 text-[var(--aktiv-muted)] opacity-50" />
      <p class="text-sm text-[var(--aktiv-muted)]">No hubs to show.</p>
    </div>

    <ul v-else class="divide-y divide-[var(--aktiv-border)]">
      <li
        v-for="(hub, index) in visibleHubs"
        :key="hub.id"
        :draggable="editing"
        class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0"
        :class="hub.show_on_profile === false && editing ? 'opacity-40' : ''"
        @dragstart="onDragStart(index)"
        @dragover="onDragOver($event, index)"
        @drop="onDrop"
      >
        <!-- Drag handle -->
        <UIcon
          v-if="editing"
          name="i-heroicons-bars-3"
          class="h-4 w-4 shrink-0 cursor-grab text-[var(--aktiv-muted)] active:cursor-grabbing"
        />

        <!-- Clickable hub info -->
        <NuxtLink
          :to="hubPublicPath(hub, '/about')"
          class="flex min-w-0 flex-1 items-center gap-3 rounded-md hover:opacity-80"
        >
          <!-- Cover image -->
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

          <!-- Name + city + description -->
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-[var(--aktiv-ink)]">{{ hub.name }}</p>
            <p class="truncate text-xs text-[var(--aktiv-muted)]">{{ hub.city }}</p>
            <p v-if="hub.description" class="mt-0.5 line-clamp-1 text-xs text-[var(--aktiv-muted)] opacity-70">{{ hub.description }}</p>
          </div>

          <!-- Rating -->
          <div class="ml-2 flex shrink-0 items-center gap-1">
            <UIcon
              name="i-heroicons-star-solid"
              class="h-3.5 w-3.5"
              :class="hub.rating !== null ? 'text-yellow-400' : 'text-[var(--aktiv-muted)] opacity-40'"
            />
            <span class="text-xs font-medium" :class="hub.rating !== null ? 'text-[var(--aktiv-ink)]' : 'text-[var(--aktiv-muted)]'">
              {{ hub.rating !== null ? hub.rating : '—' }}
            </span>
          </div>
        </NuxtLink>

        <!-- Per-hub visibility toggle (edit mode only) -->
        <button
          v-if="editing"
          type="button"
          class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[var(--aktiv-muted)] hover:bg-[var(--aktiv-border)] hover:text-[var(--aktiv-ink)]"
          :title="hub.show_on_profile === false ? 'Show on profile' : 'Hide from profile'"
          @click="emit('toggleHubVisibility', hub.id, hub.show_on_profile === false)"
        >
          <UIcon
            :name="hub.show_on_profile === false ? 'i-heroicons-eye-slash' : 'i-heroicons-eye'"
            class="h-4 w-4"
          />
        </button>
      </li>
    </ul>
  </div>
</template>
