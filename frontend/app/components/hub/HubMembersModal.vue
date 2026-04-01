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

function maskValue(value?: string | null, fallback = '*****') {
  const trimmed = value?.trim();

  if (!trimmed) {
    return fallback;
  }

  return `${trimmed.charAt(0)}${'*'.repeat(Math.min(5, Math.max(1, trimmed.length - 1)))}`;
}

function memberDisplayName(member: HubMember) {
  if (!member.is_private) {
    const name = member.name?.trim();

    if (name && !name.startsWith('@')) {
      return name;
    }

    return member.username ?? 'Member';
  }

  return maskValue(member.name, 'P*****');
}

function memberDisplayUsername(member: HubMember) {
  if (!member.is_private && member.username) {
    return `@${member.username}`;
  }

  return `@${maskValue(member.username, '*****')}`;
}

function memberCardTag(member: HubMember) {
  return member.is_premium ? '[ premium ]' : '';
}

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
  <AppModal
    v-model:open="isOpen"
    :title="`Members (${hub.members_count})`"
    :description="`People following ${hub.name}`"
    :ui="{ content: 'sm:max-w-3xl', body: 'p-0' }"
  >
    <template #body>
      <div
        class="flex max-h-[75vh] flex-col overflow-y-auto"
        @scroll="onModalScroll"
      >
        <!-- Skeleton -->
        <template v-if="loadingInitial">
          <div class="grid grid-cols-2 gap-3 p-4 md:grid-cols-3 lg:grid-cols-4">
            <div
              v-for="i in 10"
              :key="i"
              class="rounded-xl border border-[var(--aktiv-border)] bg-white px-4 py-5"
            >
              <div class="flex flex-col items-center text-center">
                <USkeleton class="mb-3 h-16 w-16 rounded-full" />
                <USkeleton class="h-4 w-24" />
                <USkeleton class="mt-2 h-3 w-20" />
                <USkeleton class="mt-3 h-5 w-20 rounded-full" />
              </div>
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
          <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
            <template v-for="member in members" :key="member.id">
              <NuxtLink
                v-if="member.username && !member.is_private"
                :to="`/profile/${member.username}`"
                class="group flex min-h-[184px] flex-col items-center rounded-xl border border-[var(--aktiv-border)] bg-white px-4 py-5 text-center transition hover:border-[var(--aktiv-primary)] hover:bg-[var(--aktiv-surface)]/60"
                @click="isOpen = false"
              >
                <div class="mb-3">
                  <AppAvatar
                    :src="member.avatar_thumb_url"
                    :name="memberDisplayName(member)"
                    :alt="memberDisplayName(member)"
                    size="xl"
                    :premium="member.is_premium"
                    class="transition-opacity group-hover:opacity-85"
                  />
                </div>
                <p
                  class="line-clamp-2 text-[15px] font-semibold leading-5 text-[var(--aktiv-ink)]"
                >
                  {{ memberDisplayName(member) }}
                </p>
                <p
                  class="mt-1 line-clamp-1 min-h-[1.125rem] text-xs font-medium text-[var(--aktiv-muted)]"
                >
                  {{ memberDisplayUsername(member) }}
                </p>
                <div class="flex mt-2 min-h-[1.5rem] items-center">
                  <UBadge
                    v-if="memberCardTag(member)"
                    color="neutral"
                    variant="soft"
                    size="sm"
                    class="rounded-full border border-[#f3d27a] bg-[#fff4d6] px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-[#9a6700]"
                  >
                    Premium
                  </UBadge>
                </div>
              </NuxtLink>

              <div
                v-else
                class="flex min-h-[184px] flex-col items-center rounded-xl border border-[var(--aktiv-border)] bg-white px-4 py-5 text-center"
              >
                <div class="mb-3">
                  <AppAvatar
                    :src="member.avatar_thumb_url"
                    :name="memberDisplayName(member)"
                    :alt="memberDisplayName(member)"
                    size="xl"
                    :premium="member.is_premium"
                  />
                </div>
                <p
                  class="line-clamp-2 text-[15px] font-semibold leading-5 text-[var(--aktiv-ink)]"
                >
                  {{ memberDisplayName(member) }}
                </p>
                <p
                  class="mt-1 line-clamp-1 min-h-[1.125rem] text-xs font-medium text-[var(--aktiv-muted)]"
                >
                  {{ memberDisplayUsername(member) }}
                </p>
                <div class="flex items-center">
                  <UBadge
                    v-if="memberCardTag(member)"
                    color="neutral"
                    variant="soft"
                    size="sm"
                    class="rounded-full border border-[#f3d27a] bg-[#fff4d6] px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-[#9a6700]"
                  >
                    Premium
                  </UBadge>
                </div>
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
