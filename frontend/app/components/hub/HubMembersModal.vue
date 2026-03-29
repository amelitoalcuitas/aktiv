<script setup lang="ts">
import type { Hub, HubMember } from '~/types/hub';
import { useAuthStore } from '~/stores/auth';

const props = defineProps<{
  hub: Hub;
  open: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'left'): void;
}>();

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
});

const authStore = useAuthStore();
const { fetchHubMembersList, leaveHub } = useHubs();

const members = ref<HubMember[]>([]);
const nextCursor = ref<string | null>(null);
const loadingInitial = ref(false);
const loadingMore = ref(false);

const confirmLeaveOpen = ref(false);
const leavingHub = ref(false);

async function loadInitial() {
  loadingInitial.value = true;
  members.value = [];
  nextCursor.value = null;
  try {
    const res = await fetchHubMembersList(props.hub.id, undefined, 50);
    members.value = res.data;
    nextCursor.value = res.next_cursor ?? null;
  } finally {
    loadingInitial.value = false;
  }
}

async function loadMore() {
  if (!nextCursor.value || loadingMore.value) return;
  loadingMore.value = true;
  try {
    const res = await fetchHubMembersList(props.hub.id, nextCursor.value, 20);
    members.value.push(...res.data);
    nextCursor.value = res.next_cursor ?? null;
  } finally {
    loadingMore.value = false;
  }
}

function onModalScroll(e: Event) {
  const el = e.target as HTMLElement;
  if (!el) return;
  if (el.scrollHeight - el.scrollTop - el.clientHeight < 200) {
    loadMore();
  }
}

const toast = useToast();

async function confirmLeave() {
  leavingHub.value = true;
  try {
    await leaveHub(props.hub.id);
    confirmLeaveOpen.value = false;
    isOpen.value = false;
    emit('left');
    await refreshNuxtData(`hub-${props.hub.id}`);
    toast.add({
      title: `Left ${props.hub.name}`,
      description: 'You have left the hub.',
      color: 'info'
    });
  } finally {
    leavingHub.value = false;
  }
}

const isMember = computed(
  () =>
    !!authStore.user && members.value.some((m) => m.id === authStore.user!.id)
);

watch(
  () => props.open,
  (open) => {
    if (open) loadInitial();
  }
);
</script>

<template>
  <AppModal v-model:open="isOpen" :title="`Members (${hub.members_count})`" :ui="{ body: 'p-0' }">
    <template #body>
      <div
        class="flex max-h-[75vh] flex-col overflow-y-auto"
        @scroll="onModalScroll"
      >
        <!-- Skeleton -->
        <template v-if="loadingInitial">
          <div class="grid grid-cols-5 gap-3 p-4 sm:grid-cols-6">
            <div
              v-for="i in 10"
              :key="i"
              class="flex flex-col items-center gap-1.5"
            >
              <USkeleton class="h-12 w-12 rounded-full" />
            </div>
          </div>
        </template>

        <!-- Empty -->
        <div
          v-else-if="members.length === 0"
          class="py-12 text-center text-sm text-[var(--aktiv-muted)]"
        >
          No members yet. Be the first to join!
        </div>

        <!-- Grid -->
        <div v-else class="p-4">
          <div class="grid grid-cols-5 gap-3 sm:grid-cols-6">
            <template v-for="member in members" :key="member.id">
              <NuxtLink
                v-if="member.username"
                :to="`/profile/${member.username}`"
                class="flex flex-col items-center"
                @click="isOpen = false"
              >
                <AppAvatar
                  :src="member.avatar_thumb_url"
                  :alt="member.name"
                  size="3xl"
                  :premium="member.is_premium"
                  class="transition-opacity hover:opacity-80"
                />
              </NuxtLink>
              <div v-else class="flex flex-col items-center">
                <AppAvatar
                  :src="null"
                  alt="?"
                  size="3xl"
                  class="opacity-50"
                />
              </div>
            </template>
          </div>
        </div>

        <!-- Load more spinner -->
        <div v-if="loadingMore" class="flex justify-center py-4">
          <UIcon
            name="i-heroicons-arrow-path"
            class="h-5 w-5 animate-spin text-[var(--aktiv-muted)]"
          />
        </div>
      </div>
    </template>

    <!-- Leave Hub footer — only for members -->
    <template v-if="isMember" #footer>
      <div class="flex justify-end w-full">
        <UButton color="error" variant="ghost" @click="confirmLeaveOpen = true">
          Leave Hub
        </UButton>
      </div>
    </template>
  </AppModal>

  <!-- Leave confirmation -->
  <AppModal
    v-model:open="confirmLeaveOpen"
    title="Leave Hub"
    confirm="Leave"
    confirm-color="error"
    :confirm-loading="leavingHub"
    @confirm="confirmLeave"
    @cancel="confirmLeaveOpen = false"
  >
    <template #body>
      <p class="px-5 py-4 text-sm text-[var(--aktiv-ink)]">
        Are you sure you want to leave <strong>{{ hub.name }}</strong
        >?
      </p>
    </template>
  </AppModal>
</template>
