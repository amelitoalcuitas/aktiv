<script setup lang="ts">
import type { User, ProfilePrivacy } from '~/types/user';

const props = defineProps<{
  user: User;
}>();

const emit = defineEmits<{
  updated: [user: User];
}>();

const { updateProfile } = useProfile();

const privacy = reactive<ProfilePrivacy>({ ...props.user.profile_privacy });

const saving = ref(false);

const sections: { key: keyof ProfilePrivacy; label: string; description: string }[] = [
  { key: 'show_visited_hubs', label: 'Most Visited Hubs', description: 'Show which hubs you visit most' },
  { key: 'show_leaderboard', label: 'Leaderboard Stats', description: 'Show your rankings and sport stats' },
  { key: 'show_hearts', label: 'Hearts', description: 'Show your heart count on your public profile' },
  { key: 'show_tournaments', label: 'Tournaments', description: 'Show tournaments you\'ve participated in' },
  { key: 'show_open_play', label: 'Open Play', description: 'Show open play sessions you\'ve joined' },
  { key: 'show_favorite_sports', label: 'Favorite Sports', description: 'Show your most-played sports' },
];

async function toggle(key: keyof ProfilePrivacy, val: boolean) {
  saving.value = true;
  try {
    const updated = await updateProfile({
      profile_privacy: { [key]: val },
    });
    emit('updated', updated);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-4">
    <div class="mb-3 flex items-center gap-2">
      <UIcon name="i-heroicons-eye" class="h-4 w-4 text-[var(--aktiv-muted)]" />
      <h3 class="text-sm font-semibold text-[var(--aktiv-ink)]">Public Visibility</h3>
    </div>
    <p class="mb-4 text-xs text-[var(--aktiv-muted)]">
      Control what visitors see on your public profile.
    </p>
    <div class="space-y-3">
      <div
        v-for="section in sections"
        :key="section.key"
        class="flex items-center justify-between gap-3"
      >
        <div class="min-w-0">
          <p class="text-sm font-medium text-[var(--aktiv-ink)]">{{ section.label }}</p>
          <p class="text-xs text-[var(--aktiv-muted)]">{{ section.description }}</p>
        </div>
        <USwitch
          :model-value="privacy[section.key]"
          :disabled="saving"
          @update:model-value="(val) => { privacy[section.key] = val; toggle(section.key, val); }"
        />
      </div>
    </div>
  </div>
</template>
