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

const fallbackInitial = computed(() =>
  props.name ? props.name.charAt(0).toUpperCase() : '?'
);
</script>

<template>
  <!-- 112px full-size mode (profile header) -->
  <div
    v-if="isFull"
    class="inline-flex shrink-0 rounded-full"
    :class="
      props.premium
        ? 'premium-avatar premium-avatar--full p-1'
        : 'bg-[var(--aktiv-surface)] p-1'
    "
  >
    <div
      class="h-28 w-28 rounded-full overflow-hidden bg-[var(--aktiv-border)] flex items-center justify-center"
      :class="props.premium ? 'premium-avatar__inner' : ''"
    >
      <img
        v-if="src"
        :src="src"
        :alt="alt"
        class="h-full w-full object-cover"
      />
      <span
        v-else
        class="text-3xl font-black text-[var(--aktiv-muted)] select-none uppercase"
      >
        {{ fallbackInitial }}
      </span>
    </div>
  </div>

  <!-- UAvatar-based sizes: sm / md / xl / 3xl -->
  <div
    v-else
    class="inline-flex rounded-full"
    :class="
      props.premium
        ? 'premium-avatar premium-avatar--compact p-[2px]'
        : 'bg-[var(--aktiv-surface)] p-[2px]'
    "
  >
    <div
      class="rounded-full overflow-hidden"
      :class="props.premium ? 'premium-avatar__inner' : ''"
    >
      <UAvatar
        :src="src ?? undefined"
        :alt="alt"
        :size="size"
        icon="i-heroicons-user"
      />
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
