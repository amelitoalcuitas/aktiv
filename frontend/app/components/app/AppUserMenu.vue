<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

const props = defineProps<{ variant?: 'sidebar' | 'header' }>();

const { user, logout, isOwner, isSuperAdmin } = useAuth();
const { canApply, applyCtaLabel, applyRoute, hasPendingRequest } = useHubOwnerRequest();

const fullName = computed(() =>
  user.value ? `${user.value.first_name} ${user.value.last_name}`.trim() : ''
);

const profileLink = computed(() =>
  user.value?.username
    ? `/profile/${user.value.username}`
    : user.value?.id
      ? `/profile/${user.value.id}`
      : '/profile'
);

const menuItems = computed(() => {
  const items = [];

  if (isSuperAdmin.value) {
    items.push([
      { label: 'Super Admin Panel', icon: 'i-heroicons-shield-check', to: '/panel' }
    ]);
  }

  if (isOwner.value) {
    items.push([
      { label: 'Dashboard', icon: 'i-heroicons-squares-2x2', to: '/dashboard' }
    ]);
  }

  items.push([
    { label: 'Main Site', icon: 'i-heroicons-home', to: '/' },
    {
      label: 'My Bookings',
      icon: 'i-heroicons-calendar-days',
      to: '/bookings'
    },
    { label: 'Profile', icon: 'i-heroicons-user', to: profileLink },
    { label: 'Settings', icon: 'i-heroicons-cog-6-tooth', to: '/settings' }
  ]);

  items.push([
    {
      label: 'Sign Out',
      icon: 'i-heroicons-arrow-right-on-rectangle',
      onSelect: logout
    }
  ]);

  return items;
});
</script>

<template>
  <div
    v-if="user"
    :class="
      variant === 'sidebar'
        ? 'flex w-full flex-col gap-2'
        : 'flex items-center gap-2'
    "
  >
    <UButton
      v-if="canApply && variant !== 'header'"
      :to="applyRoute"
      color="primary"
      :variant="hasPendingRequest ? 'soft' : 'solid'"
      :class="
        variant === 'sidebar'
          ? 'w-full justify-center rounded-xl bg-[var(--aktiv-primary)] font-semibold text-white hover:bg-[var(--aktiv-primary-hover)]'
          : 'rounded-full bg-[var(--aktiv-primary)] px-4 font-semibold text-white hover:bg-[var(--aktiv-primary-hover)]'
      "
    >
      {{ applyCtaLabel }}
    </UButton>

    <UDropdownMenu
      :modal="false"
      :items="menuItems"
      :ui="{ content: 'w-52' }"
    >
      <!-- Sidebar variant: avatar + name + ellipsis -->
      <button
        v-if="variant === 'sidebar'"
        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-[#3a4a5c] transition hover:bg-[#f0f4f8] hover:text-[#004e89]"
      >
        <AppAvatar
          :src="user?.avatar_thumb_url"
          :name="fullName"
          :alt="fullName"
          size="sm"
          :premium="user?.is_premium ?? false"
          class="flex-shrink-0"
        />
        <span class="min-w-0 flex-1 truncate text-left">{{ fullName }}</span>
        <UIcon
          name="i-heroicons-ellipsis-horizontal"
          class="h-4 w-4 flex-shrink-0"
        />
      </button>

      <!-- Header variant: name + avatar -->
      <UButton
        v-else
        variant="ghost"
        color="neutral"
        class="flex items-center gap-2 rounded-full"
      >
        <span class="hidden text-sm font-medium sm:block">{{ fullName }}</span>
        <AppAvatar
          :src="user?.avatar_thumb_url"
          :name="fullName"
          :alt="fullName"
          :premium="user?.is_premium ?? false"
        />
      </UButton>
    </UDropdownMenu>
  </div>
</template>
