<script setup lang="ts">
import type { LinkPlatform, LinkRow } from '~/types/links';
import {
  LINK_PLATFORM_OPTIONS,
  createEmptyLinkRow,
  iconForLinkPlatform
} from '~/types/links';

const links = defineModel<LinkRow[]>({ default: () => [] });

const props = withDefaults(
  defineProps<{
    errors?: string[];
    placeholder?: string;
    addLabel?: string;
    maxLinks?: number;
  }>(),
  {
    errors: () => [],
    placeholder: 'https://...',
    addLabel: 'Add another',
    maxLinks: 5
  }
);

const emit = defineEmits<{
  blur: [index: number];
}>();

const canAddMore = computed(() => links.value.length < props.maxLinks);

function addLink() {
  if (!canAddMore.value) return;
  links.value.push(createEmptyLinkRow());
}

function removeLink(index: number) {
  links.value.splice(index, 1);
}

function updatePlatform(index: number, platform: LinkPlatform) {
  const row = links.value[index];
  if (!row) return;
  row.platform = platform;
}

function rowError(index: number): string {
  return props.errors[index] ?? '';
}
</script>

<template>
  <div class="space-y-2">
    <div
      v-for="(row, index) in links"
      :key="`${index}-${row.platform}`"
      class="flex items-start gap-2"
    >
      <UDropdownMenu
        :items="
          LINK_PLATFORM_OPTIONS.map((option) => ({
            label: option.label,
            icon: option.icon,
            onSelect: () => updatePlatform(index, option.value)
          }))
        "
      >
        <UButton
          variant="ghost"
          color="neutral"
          class="w-9 shrink-0 justify-center border border-[var(--aktiv-border)] px-0"
          :aria-label="row.platform"
        >
          <UIcon
            :name="iconForLinkPlatform(row.platform)"
            class="h-4 w-4 text-[var(--aktiv-muted)]"
          />
        </UButton>
      </UDropdownMenu>

      <div class="min-w-0 flex-1">
        <UInput
          v-model="row.url"
          :placeholder="placeholder"
          class="w-full"
          :color="rowError(index) ? 'error' : undefined"
          @blur="emit('blur', index)"
        />
        <p
          v-if="rowError(index)"
          class="mt-0.5 text-xs text-[var(--aktiv-danger-fg)]"
        >
          {{ rowError(index) }}
        </p>
      </div>

      <button
        type="button"
        class="mt-1.5 shrink-0 text-[var(--aktiv-muted)] transition hover:text-[var(--aktiv-danger-fg)]"
        aria-label="Remove link"
        @click="removeLink(index)"
      >
        <UIcon name="i-heroicons-x-mark" class="h-4 w-4" />
      </button>
    </div>

    <UButton
      v-if="canAddMore"
      type="button"
      variant="ghost"
      color="neutral"
      size="xs"
      icon="i-heroicons-plus"
      @click="addLink"
    >
      {{ addLabel }}
    </UButton>
  </div>
</template>
