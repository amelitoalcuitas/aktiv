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
const imageFailed = ref(false);

const compactSizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'h-8 w-8';
    case 'xl':
      return 'h-12 w-12';
    case '3xl':
      return 'h-16 w-16';
    case 'md':
    default:
      return 'h-10 w-10';
  }
});

const fallbackInitial = computed(() => {
  const normalized = (props.name ?? '').trim().replace(/^@+/, '');
  const initial = normalized.match(/[A-Za-z0-9]/)?.[0];

  return initial ? initial.toUpperCase() : '?';
});

watch(
  () => props.src,
  () => {
    imageFailed.value = false;
  }
);
</script>

<template>
  <!-- 112px full-size mode (profile header) -->
  <div
    v-if="isFull"
    class="rounded-full"
    :class="
      props.premium
        ? 'premium-avatar premium-avatar--full p-1'
        : 'bg-[var(--aktiv-surface)] p-1'
    "
  >
    <div
      class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[var(--aktiv-border)]"
      :class="props.premium ? 'premium-avatar__inner' : ''"
    >
      <img
        v-if="src && !imageFailed"
        :src="src"
        :alt="alt"
        referrerpolicy="no-referrer"
        crossorigin="anonymous"
        class="block h-full w-full object-cover"
        @error="imageFailed = true"
      />
      <span
        v-else
        class="flex h-full w-full select-none items-center justify-center text-3xl font-black uppercase text-[var(--aktiv-muted)]"
      >
        {{ fallbackInitial }}
      </span>
    </div>
  </div>

  <!-- Compact sizes: sm / md / xl / 3xl -->
  <div
    v-else
    class="inline-flex shrink-0 rounded-full"
    :class="
      props.premium
        ? 'premium-avatar premium-avatar--compact p-[2px]'
        : 'bg-[var(--aktiv-surface)] p-[2px]'
    "
  >
    <div
      class="flex shrink-0 items-center justify-center overflow-hidden rounded-full"
      :class="[
        compactSizeClasses,
        props.premium ? 'premium-avatar__inner' : ''
      ]"
    >
      <img
        v-if="src && !imageFailed"
        :src="src"
        :alt="alt"
        referrerpolicy="no-referrer"
        crossorigin="anonymous"
        class="block h-full w-full rounded-full object-cover"
        @error="imageFailed = true"
      />
      <div
        v-else
        class="flex h-full w-full items-center justify-center rounded-full bg-[var(--aktiv-border)] font-black uppercase text-[var(--aktiv-muted)]"
        :class="{
          'text-xs': size === 'sm',
          'text-sm': size === 'md',
          'text-base': size === 'xl',
          'text-lg': size === '3xl'
        }"
      >
        {{ fallbackInitial }}
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes premium-avatar-rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.premium-avatar {
  position: relative;
  overflow: hidden;
  isolation: isolate;
}

.premium-avatar--full {
  border: 1px solid #ffd700;
}

.premium-avatar--compact {
  border: 1px solid #ffd700;
}

.premium-avatar::before {
  content: '';
  position: absolute;
  inset: -35%;
  border-radius: 50%;
  background: conic-gradient(
    from 210deg,
    #c99600 0deg,
    #ffd700 65deg,
    #fff3b0 95deg,
    #ffd700 130deg,
    #b8860b 215deg,
    #ffd700 300deg,
    #c99600 360deg
  );
  animation: premium-avatar-rotate 4.5s linear infinite;
  z-index: -1;
}

.premium-avatar__inner {
  position: relative;
  border: 1px solid rgba(184, 134, 11, 0.9);
  z-index: 1;
}
</style>
