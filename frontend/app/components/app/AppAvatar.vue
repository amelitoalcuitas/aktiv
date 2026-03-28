<script setup lang="ts">
type AvatarSize = 'sm' | 'md' | 'xl' | '3xl' | 'full';

const props = withDefaults(
  defineProps<{
    src?: string | null;
    name?: string | null;
    alt?: string;
    size?: AvatarSize;
    premium?: boolean;
  }>(),
  { src: null, name: null, alt: '', size: 'md', premium: false }
);

const isFull = computed(() => props.size === 'full');

const ringClass = computed(() =>
  props.premium ? 'ring-[var(--aktiv-premium)]' : 'ring-[var(--aktiv-surface)]'
);

const fallbackInitial = computed(() =>
  props.name ? props.name.charAt(0).toUpperCase() : '?'
);
</script>

<template>
  <!-- 112px full-size mode (profile header) -->
  <div
    v-if="isFull"
    class="h-28 w-28 rounded-full ring-4 overflow-hidden bg-[var(--aktiv-border)] flex items-center justify-center shrink-0"
    :class="ringClass"
  >
    <img v-if="src" :src="src" :alt="alt" class="h-full w-full object-cover" />
    <span v-else class="text-3xl font-black text-[var(--aktiv-muted)] select-none uppercase">
      {{ fallbackInitial }}
    </span>
  </div>

  <!-- UAvatar-based sizes: sm / md / xl / 3xl -->
  <div v-else class="rounded-full ring-2 inline-flex" :class="ringClass">
    <UAvatar
      :src="src ?? undefined"
      :alt="alt"
      :size="size"
      icon="i-heroicons-user"
    />
  </div>
</template>
