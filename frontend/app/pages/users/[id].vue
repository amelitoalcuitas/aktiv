<script setup lang="ts">
import type { PublicUser } from '~/types/user';
import { useAuthStore } from '~/stores/auth';

const route = useRoute();
const authStore = useAuthStore();
const { fetchPublicProfile, toggleHeart } = useProfile();

const userId = computed(() => String(route.params.id));

const { data: profile, error, refresh } = await useAsyncData<PublicUser>(
  `user-profile-${userId.value}`,
  () => fetchPublicProfile(userId.value),
);

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: 'User not found', fatal: true });
}

useHead(() => ({
  title: profile.value
    ? `${profile.value.first_name} ${profile.value.last_name}'s Profile`
    : 'Profile',
}));

const privacy = computed(() => profile.value?.privacy ?? {
  show_visited_hubs: true,
  show_leaderboard: true,
  show_hearts: true,
  show_tournaments: true,
  show_open_play: true,
  show_favorite_sports: true,
});

const isOwnProfile = computed(() => authStore.user?.id === userId.value);

async function onToggleHeart() {
  if (!authStore.isAuthenticated) {
    await navigateTo('/auth/login');
    return;
  }
  const result = await toggleHeart(userId.value);
  if (profile.value) {
    profile.value.has_hearted = result.hearted;
    profile.value.hearts_count = result.hearts_count;
  }
}
</script>

<template>
  <div v-if="profile" class="min-h-screen bg-[var(--aktiv-background)]">
    <ProfileHeader
      :profile="profile"
      :is-own="false"
      @toggle-heart="onToggleHeart"
    />

    <div class="mx-auto max-w-4xl px-4 pb-12 md:px-6">
      <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-1">
        <div class="space-y-5">
          <ProfileVisitedHubs v-if="privacy.show_visited_hubs" :hidden="false" />
          <ProfileStatsCard v-if="privacy.show_leaderboard" :hidden="false" />
          <ProfileTournamentsCard v-if="privacy.show_tournaments" :hidden="false" />
          <ProfileOpenPlayCard v-if="privacy.show_open_play" :hidden="false" />

          <div
            v-if="!privacy.show_visited_hubs && !privacy.show_leaderboard && !privacy.show_tournaments && !privacy.show_open_play"
            class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] px-6 py-10 text-center"
          >
            <UIcon name="i-heroicons-lock-closed" class="mx-auto mb-3 h-8 w-8 text-[var(--aktiv-muted)] opacity-40" />
            <p class="text-sm text-[var(--aktiv-muted)]">This user's stats are private.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
