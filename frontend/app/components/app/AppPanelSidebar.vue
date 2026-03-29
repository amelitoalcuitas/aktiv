<script setup lang="ts">
const props = defineProps<{ open?: boolean }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const route = useRoute();

const navGroups = [
  {
    items: [{ label: 'Overview', icon: 'i-heroicons-home', to: '/panel' }]
  },
  {
    label: 'Platform',
    items: [
      { label: 'Users', icon: 'i-heroicons-users', to: '/panel/users' },
      {
        label: 'Owner Requests',
        icon: 'i-heroicons-inbox-stack',
        to: '/panel/requests'
      }
    ]
  }
];

const isActive = (to: string) => {
  if (to === '/panel') return route.path === '/panel';
  return route.path.startsWith(to);
};

const close = () => emit('update:open', false);

watch(() => route.path, close);
</script>

<template>
  <!-- Overlay (mobile only) -->
  <Transition name="fade">
    <div
      v-if="open"
      class="fixed inset-0 z-40 bg-black/40 md:hidden"
      @click="close"
    />
  </Transition>

  <aside
    :class="[
      'fixed inset-y-0 left-0 z-50 flex w-60 flex-col border-r border-[#dbe4ef] bg-white transition-transform duration-300',
      open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
    ]"
  >
    <!-- Logo -->
    <div class="flex h-16 items-center border-b border-[#dbe4ef] px-6">
      <NuxtLink to="/" class="inline-flex items-center gap-2">
        <AppIcon class="h-5 w-auto" />
        <AppLogo class="h-5 w-auto" />
      </NuxtLink>
      <!-- Close button (mobile only) -->
      <button
        class="ml-auto rounded-lg p-1 text-[#64748b] hover:bg-[#f0f4f8] md:hidden"
        aria-label="Close sidebar"
        @click="close"
      >
        <UIcon name="i-heroicons-x-mark" class="h-5 w-5" />
      </button>
    </div>

    <!-- Panel label -->
    <div class="border-b border-[#dbe4ef] px-6 py-3">
      <p
        class="text-[10px] font-semibold uppercase tracking-widest text-[#94a3b8]"
      >
        Super Admin
      </p>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 py-4">
      <div
        v-for="(group, gi) in navGroups"
        :key="gi"
        :class="gi > 0 ? 'mt-4' : ''"
      >
        <p
          v-if="group.label"
          class="mb-1 px-3 text-[10px] font-semibold uppercase tracking-widest text-[#94a3b8]"
        >
          {{ group.label }}
        </p>
        <ul class="space-y-0.5">
          <li v-for="link in group.items" :key="link.to">
            <NuxtLink
              :to="link.to"
              :class="[
                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                isActive(link.to)
                  ? 'bg-[#e8f0f8] text-[#004e89]'
                  : 'text-[#3a4a5c] hover:bg-[#f0f4f8] hover:text-[#004e89]'
              ]"
            >
              <UIcon :name="link.icon" class="h-5 w-5 flex-shrink-0" />
              {{ link.label }}
            </NuxtLink>
          </li>
        </ul>
      </div>
    </nav>
  </aside>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
