<script setup lang="ts">
import type { User } from '~/types/user';
import { useAuthStore } from '~/stores/auth';

definePageMeta({ middleware: 'auth' });

useHead({ title: 'My Profile' });

const authStore = useAuthStore();
const { fetchOwnProfile, uploadAvatar, uploadBanner, updateProfile } =
  useProfile();

const { data: profile, refresh } = await useAsyncData<User>('own-profile', () =>
  fetchOwnProfile()
);

const editing = ref(false);
const editModalOpen = ref(false);
const savingPrivacy = ref(false);

const privacy = computed(() => ({
  show_visited_hubs: true,
  show_leaderboard: true,
  show_hearts: true,
  show_tournaments: true,
  show_open_play: true,
  show_favorite_sports: true,
  ...profile.value?.profile_privacy
}));

async function onUploadAvatar(file: File) {
  await uploadAvatar(file);
  await refresh();
}

async function onUploadBanner(file: File) {
  await uploadBanner(file);
  await refresh();
}

function onProfileSaved(updated: User) {
  authStore.setUser(updated);
  refresh();
}

async function togglePrivacy(key: string, val: boolean) {
  savingPrivacy.value = true;
  try {
    const updated = await updateProfile({ profile_privacy: { [key]: val } });
    authStore.setUser(updated);
    await refresh();
  } finally {
    savingPrivacy.value = false;
  }
}
</script>

<template>
  <div v-if="profile" class="min-h-screen bg-[var(--aktiv-background)]">
    <ProfileHeader
      :profile="profile"
      :is-own="true"
      :editing="editing"
      @upload-avatar="onUploadAvatar"
      @upload-banner="onUploadBanner"
      @edit-info="editModalOpen = true"
    />

    <div class="mx-auto max-w-4xl px-4 pb-12 md:px-6">
      <!-- Toolbar -->
      <div class="mb-5 flex items-center justify-end gap-2">
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

      <!-- Cards -->
      <div class="space-y-4">
        <!-- Remaining privacy settings (no dedicated card) — shown only while editing -->
        <div
          v-if="editing"
          class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4"
        >
          <h3 class="mb-3 text-lg font-bold text-[var(--aktiv-ink)]">
            More Visibility Settings
          </h3>
          <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="text-sm font-medium text-[var(--aktiv-ink)]">
                  Hearts
                </p>
                <p class="text-xs text-[var(--aktiv-muted)]">
                  Show your heart count on your public profile
                </p>
              </div>
              <USwitch
                :model-value="privacy.show_hearts"
                :disabled="savingPrivacy"
                @update:model-value="(val) => togglePrivacy('show_hearts', val)"
              />
            </div>
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="text-sm font-medium text-[var(--aktiv-ink)]">
                  Favorite Sports
                </p>
                <p class="text-xs text-[var(--aktiv-muted)]">
                  Show your most-played sports
                </p>
              </div>
              <USwitch
                :model-value="privacy.show_favorite_sports"
                :disabled="savingPrivacy"
                @update:model-value="
                  (val) => togglePrivacy('show_favorite_sports', val)
                "
              />
            </div>
          </div>
        </div>

        <!-- Bio card -->
        <div
          class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4 md:p-6"
        >
          <div class="flex items-center justify-between gap-2">
            <h3 class="mb-1 text-lg font-bold text-[var(--aktiv-ink)]">About</h3>
            <button
              type="button"
              v-if="editing"
              class="flex items-center justify-center h-7 w-7 rounded-full hover:bg-[var(--aktiv-border)] text-[var(--aktiv-muted)] hover:text-[var(--aktiv-ink)]"
              @click="editModalOpen = true"
            >
              <UIcon name="i-heroicons-pencil" class="h-3.5 w-3.5" />
            </button>
          </div>
          <p
            v-if="profile.bio"
            class="mt-1 whitespace-pre-wrap text-base leading-relaxed text-[var(--aktiv-muted)]"
          >
            {{ profile.bio }}
          </p>
          <p v-else class="mt-1 text-sm italic text-[var(--aktiv-muted)]">
            No bio yet.
          </p>
        </div>

        <ProfileVisitedHubs
          :hidden="!privacy.show_visited_hubs"
          :editing="editing"
          @toggle-privacy="(val) => togglePrivacy('show_visited_hubs', val)"
        />
        <ProfileStatsCard
          :hidden="!privacy.show_leaderboard"
          :editing="editing"
          @toggle-privacy="(val) => togglePrivacy('show_leaderboard', val)"
        />
        <ProfileTournamentsCard
          :hidden="!privacy.show_tournaments"
          :editing="editing"
          @toggle-privacy="(val) => togglePrivacy('show_tournaments', val)"
        />
        <ProfileOpenPlayCard
          :hidden="!privacy.show_open_play"
          :editing="editing"
          @toggle-privacy="(val) => togglePrivacy('show_open_play', val)"
        />
      </div>
    </div>

    <ProfileEditModal
      v-if="profile"
      v-model:open="editModalOpen"
      :user="profile"
      @saved="onProfileSaved"
    />
  </div>
</template>
