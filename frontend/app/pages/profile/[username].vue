<script setup lang="ts">
import type { User, PublicUser } from '~/types/user';
import { useAuthStore } from '~/stores/auth';

const route = useRoute();
const authStore = useAuthStore();
const {
  fetchPublicProfile,
  fetchOwnProfile,
  resolveUsername,
  toggleHeart,
  uploadAvatar,
  uploadBanner,
  updateProfile,
  updateHubShowOnProfile
} = useProfile();
const toast = useToast();

const param = computed(() => String(route.params.username));

// Resolve username to UUID (fall back to treating param as UUID for old users)
const { data: resolvedId } = await useAsyncData(`resolve-${param.value}`, () =>
  resolveUsername(param.value).catch(() => null)
);

const userId = resolvedId.value ? String(resolvedId.value) : param.value;

// Always fetch public profile first (used for ownership check + public view)
const {
  data: publicProfile,
  error,
  refresh: refreshPublic
} = await useAsyncData<PublicUser>(`user-profile-${userId}`, () =>
  fetchPublicProfile(userId)
);

if (error.value || !publicProfile.value) {
  throw createError({
    statusCode: 404,
    statusMessage: 'Profile not found',
    fatal: true
  });
}

// Re-fetch client-side to get auth-aware data (has_hearted).
// SSR may not carry the auth token, so has_hearted can be stale in the payload.
onMounted(() => {
  if (authStore.isAuthenticated) refreshPublic();
});

const isOwnProfile = computed(
  () => authStore.user?.id === publicProfile.value?.id
);

const isPrivateProfile = computed(
  () => publicProfile.value?.is_private === true && !isOwnProfile.value
);

useHead(() => ({
  title: publicProfile.value
    ? `${publicProfile.value.first_name ?? publicProfile.value.username ?? 'User'}'s Profile`
    : 'Profile'
}));

// ── Own profile ──────────────────────────────────────────────────────────────

const { data: ownProfile, refresh: refreshOwn } = isOwnProfile.value
  ? await useAsyncData<User>('own-profile', () => fetchOwnProfile())
  : { data: ref<User | null>(null), refresh: async () => {} };

const editing = ref(false);
const editModalOpen = ref(false);

const privacy = computed(() => ({
  show_owned_hubs: true,
  show_visited_hubs: true,
  show_leaderboard: true,
  show_hearts: true,
  show_tournaments: true,
  show_open_play: true,
  show_favorite_sports: true,
  show_joined_hubs: true,
  ...ownProfile.value?.profile_privacy
}));

async function saveHubOrder(ids: string[]) {
  const updated = await updateProfile({ hub_display_order: ids });
  authStore.setUser(updated);
  await refreshOwn();
}

async function toggleHubVisibility(id: string, val: boolean) {
  await updateHubShowOnProfile(id, val);
  await refreshOwn();
}

async function onUploadAvatar(file: File) {
  try {
    await uploadAvatar(file);
  } catch {
    toast.add({ title: 'Failed to upload avatar', color: 'error' });
    return;
  }
  toast.add({ title: 'Avatar updated', color: 'success' });
  await refreshOwn();
}

async function onUploadBanner(file: File) {
  try {
    await uploadBanner(file);
  } catch {
    toast.add({ title: 'Failed to upload banner', color: 'error' });
    return;
  }
  toast.add({ title: 'Banner updated', color: 'success' });
  await refreshOwn();
}

function onProfileSaved(updated: User) {
  toast.add({ title: 'Profile updated', color: 'success' });
  authStore.setUser(updated);
  refreshOwn();
}

// ── Public profile ────────────────────────────────────────────────────────────

const publicPrivacy = computed(
  () =>
    publicProfile.value?.privacy ?? {
      show_owned_hubs: true,
      show_visited_hubs: true,
      show_leaderboard: true,
      show_hearts: true,
      show_tournaments: true,
      show_open_play: true,
      show_favorite_sports: true,
      show_joined_hubs: true
    }
);

async function onToggleHeart() {
  if (!authStore.isAuthenticated) {
    await navigateTo('/auth/login');
    return;
  }
  const result = await toggleHeart(userId);
  if (publicProfile.value) {
    publicProfile.value = {
      ...publicProfile.value,
      has_hearted: result.hearted,
      hearts_count: result.hearts_count
    };
  }
}
</script>

<template>
  <!-- Own profile (editable) -->
  <div
    v-if="isOwnProfile && ownProfile"
    class="min-h-screen bg-[var(--aktiv-background)]"
  >
    <ProfileHeader
      :profile="ownProfile"
      :is-own="true"
      :editing="editing"
      @upload-avatar="onUploadAvatar"
      @upload-banner="onUploadBanner"
      @edit-info="editModalOpen = true"
    />

    <div class="mx-auto w-full max-w-5xl flex-1 px-4 pb-8 md:px-8">
      <div class="mb-5 flex items-center justify-end gap-2">
        <NuxtLink v-if="editing" to="/settings?tab=privacy">
          <UButton variant="ghost" color="neutral" size="sm" icon="i-heroicons-eye">
            Visibility Settings
          </UButton>
        </NuxtLink>
        <UButton
          v-if="editing"
          icon="i-heroicons-check"
          size="sm"
          @click="editing = false"
        >
          Done
        </UButton>
        <UButton
          v-else
          variant="ghost"
          color="neutral"
          size="sm"
          @click="editing = true"
        >
          Edit Profile
        </UButton>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-[320px_1fr]">
        <!-- Left column (sticky) -->
        <div class="space-y-4 lg:sticky lg:top-6 lg:self-start">
          <!-- About -->
          <div
            class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4 md:p-6"
          >
            <div class="flex items-center justify-between gap-2">
              <h3 class="mb-1 text-lg font-bold text-[var(--aktiv-ink)]">
                About
              </h3>
              <button
                v-if="editing"
                type="button"
                class="flex h-7 w-7 items-center justify-center rounded-full text-[var(--aktiv-muted)] hover:bg-[var(--aktiv-border)] hover:text-[var(--aktiv-ink)]"
                @click="editModalOpen = true"
              >
                <UIcon name="i-heroicons-pencil" class="h-3.5 w-3.5" />
              </button>
            </div>
            <p
              v-if="ownProfile.bio"
              class="mt-1 whitespace-pre-wrap text-base leading-relaxed text-[var(--aktiv-muted)]"
            >
              {{ ownProfile.bio }}
            </p>
            <p v-else class="mt-1 text-sm italic text-[var(--aktiv-muted)]">
              No bio yet.
            </p>
          </div>

          <!-- Joined Hubs -->
          <ProfileJoinedHubsCard
            :hubs="ownProfile.joined_hubs"
            :hidden="!privacy.show_joined_hubs"
            :show-eye="true"
          />

          <!-- Hubs Owned -->
          <ProfileOwnedHubsCard
            v-if="ownProfile.is_hub_owner"
            :hubs="ownProfile.owned_hubs"
            :hidden="!privacy.show_owned_hubs"
            :editing="editing"
            :show-eye="true"
            :show-privacy-toggle="false"
            @reorder="saveHubOrder"
            @toggle-hub-visibility="toggleHubVisibility"
          />
        </div>

        <!-- Right column -->
        <div class="space-y-4">
          <ProfileVisitedHubs :hidden="!privacy.show_visited_hubs" :show-eye="true" />
          <ProfileStatsCard :hidden="!privacy.show_leaderboard" :show-eye="true" />
          <ProfileTournamentsCard :hidden="!privacy.show_tournaments" :show-eye="true" />
          <ProfileOpenPlayCard :hidden="!privacy.show_open_play" :show-eye="true" />
        </div>
      </div>
    </div>

    <ProfileEditModal
      v-model:open="editModalOpen"
      :user="ownProfile"
      @saved="onProfileSaved"
    />
  </div>

  <!-- Private profile -->
  <div
    v-else-if="isPrivateProfile && publicProfile"
    class="min-h-screen bg-[var(--aktiv-background)]"
  >
    <ProfileHeader
      :profile="publicProfile"
      :is-own="false"
      :is-authenticated="false"
    />

    <div class="mx-auto max-w-5xl px-4 pb-12 md:px-6">
      <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[320px_1fr]">
        <!-- Left column -->
        <div class="space-y-4">
          <!-- About -->
          <div class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4 md:p-6">
            <h3 class="mb-1 text-lg font-bold text-[var(--aktiv-ink)]">About</h3>
            <p
              v-if="publicProfile.bio"
              class="mt-1 whitespace-pre-wrap text-base leading-relaxed text-[var(--aktiv-muted)]"
            >
              {{ publicProfile.bio }}
            </p>
            <p v-else class="mt-1 text-sm italic text-[var(--aktiv-muted)]">
              No bio yet.
            </p>
          </div>
        </div>

        <!-- Right column -->
        <div class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] px-6 py-10 text-center">
          <UIcon name="i-heroicons-lock-closed" class="mx-auto mb-3 h-8 w-8 text-[var(--aktiv-muted)] opacity-40" />
          <p class="text-sm text-[var(--aktiv-muted)]">This profile is private.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Public profile (read-only) -->
  <div
    v-else-if="publicProfile"
    class="min-h-screen bg-[var(--aktiv-background)]"
  >
    <ProfileHeader
      :profile="publicProfile"
      :is-own="false"
      :is-authenticated="authStore.isAuthenticated"
      @toggle-heart="onToggleHeart"
    />

    <div class="mx-auto max-w-5xl px-4 pb-12 md:px-6">
      <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[320px_1fr]">
        <!-- Left column (sticky) -->
        <div class="space-y-4 lg:sticky lg:top-6 lg:self-start">
          <!-- About -->
          <div
            class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4 md:p-6"
          >
            <h3 class="mb-1 text-lg font-bold text-[var(--aktiv-ink)]">
              About
            </h3>
            <p
              v-if="publicProfile.bio"
              class="mt-1 whitespace-pre-wrap text-base leading-relaxed text-[var(--aktiv-muted)]"
            >
              {{ publicProfile.bio }}
            </p>
            <p v-else class="mt-1 text-sm italic text-[var(--aktiv-muted)]">
              No bio yet.
            </p>
          </div>

          <!-- Joined Hubs -->
          <ProfileJoinedHubsCard
            v-if="publicPrivacy.show_joined_hubs"
            :hubs="publicProfile.joined_hubs"
            :hidden="false"
          />

          <!-- Hubs Owned -->
          <ProfileOwnedHubsCard
            v-if="publicProfile.is_hub_owner && publicPrivacy.show_owned_hubs"
            :hubs="publicProfile.owned_hubs"
            :hidden="false"
          />
        </div>

        <!-- Right column -->
        <div class="space-y-4">
          <ProfileVisitedHubs
            v-if="publicPrivacy.show_visited_hubs"
            :user-id="userId"
            :hidden="false"
          />
          <ProfileStatsCard
            v-if="publicPrivacy.show_leaderboard"
            :hidden="false"
          />
          <ProfileTournamentsCard
            v-if="publicPrivacy.show_tournaments"
            :hidden="false"
          />
          <ProfileOpenPlayCard
            v-if="publicPrivacy.show_open_play"
            :hidden="false"
          />

          <div
            v-if="
              !publicPrivacy.show_visited_hubs &&
              !publicPrivacy.show_leaderboard &&
              !publicPrivacy.show_tournaments &&
              !publicPrivacy.show_open_play
            "
            class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] px-6 py-10 text-center"
          >
            <UIcon
              name="i-heroicons-lock-closed"
              class="mx-auto mb-3 h-8 w-8 text-[var(--aktiv-muted)] opacity-40"
            />
            <p class="text-sm text-[var(--aktiv-muted)]">
              This user's stats are private.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
