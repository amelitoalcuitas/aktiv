<script setup lang="ts">
import type { Hub, HubMember } from '~/types/hub';
import { useAuthStore } from '~/stores/auth';

const route = useRoute();
const { fetchHub } = useHubs();
const authStore = useAuthStore();

const hubId = computed(() => String(route.params.id ?? ''));

const { data: activeHub, error: hubError } = await useAsyncData<Hub>(
  `hub-${hubId.value}`,
  () => fetchHub(hubId.value),
  {
    default: () =>
      ({
        id: '',
        name: '',
        description: null,
        city: '',
        address: '',
        address_line2: null,
        landmark: null,
        zip_code: null,
        province: null,
        country: null,
        lat: null,
        lng: null,
        cover_image_url: null,
        gallery_images: [],
        is_approved: true,
        is_verified: false,
        is_active: true,
        owner_id: '',
        sports: [],
        operating_hours: [],
        courts_count: 0,
        lowest_price_per_hour: null,
        require_account_to_book: false,
        guest_booking_limit: 0,
        guest_max_hours: 0,
        payment_methods: [],
        payment_qr_url: null,
        digital_bank_name: null,
        digital_bank_account: null,
        contact_numbers: [],
        websites: [],
        rating: null,
        reviews_count: 0,
        rating_breakdown: null,
        members_count: 0,
        member_preview: [],
        is_member: false,
        has_active_promo: false,
        has_active_announcement: false,
        created_at: ''
      }) as Hub
  }
);

// Any API error (including 404 for inactive hubs) should render the error page
if (hubError.value) {
  throw createError({
    statusCode: (hubError.value as { statusCode?: number }).statusCode ?? 404,
    statusMessage: 'Not Found',
    fatal: true
  });
}

const currentUserId = computed(() => authStore.user?.id ?? null);

const isInactiveOwnerView = computed(
  () =>
    !!activeHub.value?.id &&
    activeHub.value.is_active === false &&
    currentUserId.value === activeHub.value.owner_id
);

const tabs = computed(() => {
  const id = hubId.value || String(activeHub.value?.id ?? '');
  return [
    {
      label: 'Overview',
      icon: 'i-heroicons-information-circle',
      to: `/hubs/${id}/about`
    },
    {
      label: 'Open Play',
      icon: 'i-heroicons-user-group',
      to: `/hubs/${id}/open-play`
    },
    {
      label: 'Tournaments',
      icon: 'i-heroicons-trophy',
      to: `/hubs/${id}/tournaments`
    },
    {
      label: 'Leaderboard',
      icon: 'i-heroicons-chart-bar-square',
      to: `/hubs/${id}/leaderboard`
    }
  ];
});

const isCurrentlyOpen = computed(() => {
  const hours = activeHub.value?.operating_hours;
  if (!hours?.length) return false;
  const now = new Date();
  const todayHours = hours.find((oh) => oh.day_of_week === now.getDay());
  if (!todayHours || todayHours.is_closed) return false;
  const [openH = 0, openM = 0] = todayHours.opens_at.split(':').map(Number);
  const [closeH = 0, closeM = 0] = todayHours.closes_at.split(':').map(Number);
  const nowMins = now.getHours() * 60 + now.getMinutes();
  return nowMins >= openH * 60 + openM && nowMins < closeH * 60 + closeM;
});

const coverImage = ref(
  activeHub.value?.cover_image_url ?? activeHub.value?.coverImageUrl ?? ''
);

function onCoverImgError() {
  coverImage.value = '';
}

const ratingsModalOpen = ref(false);
const membersModalOpen = ref(false);

const { joinHub, leaveHub } = useHubs();
const joiningHub = ref(false);

const isOwner = computed(
  () => !!authStore.user && authStore.user.id === activeHub.value?.owner_id
);

const localIsMember = ref(activeHub.value?.is_member ?? false);
const localMembersCount = ref(activeHub.value?.members_count ?? 0);
const localMemberPreview = ref<HubMember[]>([
  ...(activeHub.value?.member_preview ?? [])
]);

watch(
  () => activeHub.value?.is_member,
  (val) => {
    localIsMember.value = val ?? false;
  }
);
watch(
  () => activeHub.value?.members_count,
  (val) => {
    localMembersCount.value = val ?? 0;
  }
);
watch(
  () => activeHub.value?.member_preview,
  (val) => {
    localMemberPreview.value = [...(val ?? [])];
  }
);

function onLeftFromModal() {
  localIsMember.value = false;
  localMembersCount.value = Math.max(0, localMembersCount.value - 1);
  localMemberPreview.value = localMemberPreview.value.filter(
    (m) => m.id !== authStore.user?.id
  );
}

const toast = useToast();

async function toggleMembership() {
  if (!authStore.isAuthenticated || isOwner.value || joiningHub.value) return;
  joiningHub.value = true;
  try {
    if (localIsMember.value) {
      await leaveHub(activeHub.value!.id);
      localIsMember.value = false;
      localMembersCount.value = Math.max(0, localMembersCount.value - 1);
      localMemberPreview.value = localMemberPreview.value.filter(
        (m) => m.id !== authStore.user?.id
      );
    } else {
      await joinHub(activeHub.value!.id);
      localIsMember.value = true;
      localMembersCount.value += 1;
      if (authStore.user) {
        localMemberPreview.value = [
          {
            id: authStore.user.id,
            name: authStore.user.first_name + ' ' + authStore.user.last_name,
            username: authStore.user.username ?? '',
            avatar_thumb_url: authStore.user.avatar_thumb_url ?? null,
            is_premium: authStore.user.is_premium
          },
          ...localMemberPreview.value.slice(0, 4)
        ];
      }

      toast.add({
        title: `Joined ${activeHub.value?.name}`,
        description: 'You are now a member of this hub.',
        color: 'success'
      });
    }
    await refreshNuxtData(`hub-${hubId.value}`);
  } catch {
    await refreshNuxtData(`hub-${hubId.value}`);
  } finally {
    joiningHub.value = false;
  }
}
</script>

<template>
  <!-- Hero -->
  <section
    class="relative isolate overflow-hidden border-b border-[var(--aktiv-border)]"
  >
    <img
      v-if="coverImage"
      :src="coverImage"
      :alt="activeHub?.name"
      class="h-[168px] sm:h-[260px] w-full object-cover"
      @error="onCoverImgError"
    />
    <div
      v-else
      class="flex h-[168px] w-full items-center justify-center bg-[var(--aktiv-border)]"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-16 w-16 text-[var(--aktiv-muted)] opacity-30"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="1"
      >
        <rect x="3" y="3" width="18" height="18" rx="2" />
        <circle cx="8.5" cy="8.5" r="1.5" />
        <path d="M21 15l-5-5L5 21" />
      </svg>
    </div>
    <div
      class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"
    ></div>

    <div class="absolute inset-x-0 bottom-0">
      <div
        class="mx-auto flex w-full max-w-[1400px] items-end justify-between px-4 pb-4 md:px-6"
      >
        <!-- Left: name + meta -->
        <div class="min-w-0 flex-1 pr-3">
          <h1
            class="text-2xl font-black leading-tight text-white drop-shadow-md md:text-4xl"
          >
            {{ activeHub?.name }}
          </h1>

          <p
            v-if="activeHub?.address || activeHub?.city"
            class="mt-1 text-sm text-white/70 drop-shadow"
          >
            {{
              [activeHub?.address, activeHub?.city].filter(Boolean).join(', ')
            }}
          </p>

          <div class="mt-2 flex flex-wrap items-center gap-2">
            <!-- City + open/closed inline -->
            <span
              class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-white"
            >
              <UIcon name="i-heroicons-map-pin" class="h-3.5 w-3.5" />
              {{ activeHub?.city }}
              <template v-if="activeHub?.operating_hours?.length">
                <span class="opacity-40">·</span>
                <span
                  class="inline-flex items-center gap-1 normal-case tracking-normal"
                  :class="
                    isCurrentlyOpen ? 'text-green-300' : 'text-red-300/80'
                  "
                >
                  <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="isCurrentlyOpen ? 'bg-green-400' : 'bg-red-400'"
                  />
                  {{ isCurrentlyOpen ? 'Open now' : 'Closed' }}
                </span>
              </template>
            </span>

            <!-- Rating -->
            <button
              v-if="
                activeHub?.rating != null ||
                (activeHub?.reviews_count ?? 0) >= 0
              "
              type="button"
              class="inline-flex items-center gap-1 rounded-full bg-white/15 px-3 py-1 text-xs font-bold text-white transition hover:bg-white/25"
              @click="ratingsModalOpen = true"
            >
              <UIcon
                name="i-heroicons-star-solid"
                class="h-4 w-4 text-[#F0A202]"
              />
              {{ activeHub.rating != null ? activeHub.rating.toFixed(1) : '–' }}
              ({{ activeHub.reviews_count ?? 0 }})
            </button>

            <HubRatingsModal
              v-if="activeHub?.id"
              v-model:open="ratingsModalOpen"
              :hub="activeHub"
            />

            <!-- Verified -->
            <UBadge
              v-if="activeHub?.is_verified"
              color="primary"
              variant="soft"
            >
              Verified
            </UBadge>
          </div>
        </div>

        <!-- Right: members card + join -->
        <div class="flex shrink-0 flex-col items-end gap-2">
          <!-- Members card -->
          <div
            v-if="localMembersCount > 0"
            class="cursor-pointer rounded-xl bg-white/20 px-3 py-2 transition hover:bg-white/30"
            @click="membersModalOpen = true"
          >
            <p class="mb-1.5 text-xs font-bold text-white">
              Members · {{ localMembersCount }}
            </p>
            <div class="flex gap-1.5">
              <AppAvatar
                v-for="member in localMemberPreview.slice(0, 5)"
                :key="member.id"
                :src="member.avatar_thumb_url"
                :alt="member.name"
                size="sm"
                :premium="member.is_premium"
              />
            </div>
          </div>

          <!-- Join button — only shown when not yet a member -->
          <button
            v-if="authStore.isAuthenticated && !isOwner && !localIsMember"
            type="button"
            :disabled="joiningHub"
            class="inline-flex items-center gap-1.5 rounded-full bg-success/80 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-success/90 disabled:opacity-60"
            @click="toggleMembership"
          >
            <UIcon name="i-heroicons-plus-circle" class="h-4 w-4" />
            Join Hub
          </button>
        </div>

        <HubMembersModal
          v-if="activeHub?.id"
          v-model:open="membersModalOpen"
          :hub="activeHub"
          @left="onLeftFromModal"
        />
      </div>
    </div>
  </section>

  <!-- Inactive hub banner (owner only) -->
  <div
    v-if="isInactiveOwnerView"
    class="border-b border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
  >
    <div class="mx-auto w-full max-w-[1400px] px-4 py-3 md:px-6">
      <UAlert
        icon="i-heroicons-eye-slash"
        color="warning"
        variant="subtle"
        title="This hub is currently inactive"
        description="Only you (the owner) can see this page. This hub is hidden from the public listing. You can activate it from the hub edit page."
      />
    </div>
  </div>

  <!-- Tab navigation -->
  <HubTabNav :tabs="tabs" />
</template>
