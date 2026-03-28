<script setup lang="ts">
import { useHubStore } from '~/stores/hub';

const props = defineProps<{ open?: boolean }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const route = useRoute();
const router = useRouter();
const hubStore = useHubStore();

const isVerifyModalOpen = ref(false);
const pendingPath = ref<string | null>(null);

const activePath = computed(() => pendingPath.value ?? route.path);

router.beforeEach((to) => { pendingPath.value = to.path; });
router.afterEach(() => { pendingPath.value = null; });

const verifyHub = computed(() => hubStore.myHubs[0] ?? null);

const navGroups = [
  {
    items: [{ label: 'Overview', icon: 'i-heroicons-home', to: '/dashboard' }]
  },
  {
    label: 'Hub Management',
    items: [
      {
        label: 'My Hubs',
        icon: 'i-heroicons-building-office-2',
        to: '/dashboard/hubs'
      }
    ]
  }
];

const isActive = (to: string, exact = false) => {
  if (exact || to === '/dashboard') return activePath.value === to;
  return activePath.value.startsWith(to);
};

const isHubSubActive = computed(() =>
  hubStore.myHubs.some(h => activePath.value.startsWith(`/hubs/${h.id}`))
);

const close = () => emit('update:open', false);

// Close sidebar on route change (mobile)
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

    <!-- Verify Booking button -->
    <div class="border-b border-[#dbe4ef] px-3 py-3">
      <button
        class="flex w-full items-center gap-3 rounded-xl bg-[#004e89] px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-[#003d6b]"
        @click="isVerifyModalOpen = true"
      >
        <UIcon name="i-heroicons-qr-code" class="h-5 w-5 flex-shrink-0" />
        Verify Booking
      </button>
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
                isActive(link.to) && !(link.to === '/dashboard/hubs' && isHubSubActive)
                  ? 'bg-[#e8f0f8] text-[#004e89]'
                  : 'text-[#3a4a5c] hover:bg-[#f0f4f8] hover:text-[#004e89]'
              ]"
            >
              <UIcon :name="link.icon" class="h-5 w-5 flex-shrink-0" />
              {{ link.label }}
            </NuxtLink>
            <!-- Hub sub-items under My Hubs -->
            <div
              v-if="link.to === '/dashboard/hubs' && hubStore.myHubs.length"
              class="mt-0.5 ml-[22px] flex"
            >
              <div class="w-px bg-[#dbe4ef] mx-3 flex-shrink-0" />
              <ul class="flex-1 space-y-0.5">
                <li v-for="hub in hubStore.myHubs" :key="hub.id">
                  <NuxtLink
                    :to="`/hubs/${hub.id}/edit`"
                    :class="[
                      'flex items-center rounded-xl px-3 py-2 text-sm transition',
                      isActive(`/hubs/${hub.id}`)
                        ? 'font-medium text-[#004e89] bg-[#e8f0f8]'
                        : 'text-[#64748b] hover:bg-[#f0f4f8] hover:text-[#004e89]'
                    ]"
                  >
                    <span class="truncate">{{ hub.name }}</span>
                  </NuxtLink>
                </li>
              </ul>
            </div>
          </li>
        </ul>
      </div>
    </nav>
  </aside>

  <BookingVerifyModal v-model:open="isVerifyModalOpen" :hub="verifyHub" />
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
