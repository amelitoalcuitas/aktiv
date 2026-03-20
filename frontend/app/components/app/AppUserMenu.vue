<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

const props = defineProps<{ variant?: 'sidebar' | 'header' }>();

const { user, logout, isAdmin } = useAuth();

const menuItems = computed(() => {
  const items = [];

  if (isAdmin.value) {
    items.push([
      { label: 'Dashboard', icon: 'i-heroicons-squares-2x2', to: '/dashboard' }
    ]);
  }

  items.push([
    { label: 'Main Site', icon: 'i-heroicons-home', to: '/' },
    { label: 'Profile', icon: 'i-heroicons-user', to: '/profile' }
  ]);

  items.push([
    { label: 'Sign Out', icon: 'i-heroicons-arrow-right-on-rectangle', onSelect: logout }
  ]);

  return items;
});
</script>

<template>
  <UDropdownMenu v-if="user" :modal="false" :items="menuItems" :ui="{ content: 'w-52' }">
    <!-- Sidebar variant: avatar + name + ellipsis -->
    <button
      v-if="variant === 'sidebar'"
      class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-[#3a4a5c] transition hover:bg-[#f0f4f8] hover:text-[#004e89]"
    >
      <UAvatar
        :src="user?.avatar_url ?? undefined"
        :alt="user?.name"
        icon="i-heroicons-user"
        size="sm"
        class="flex-shrink-0"
      />
      <span class="min-w-0 flex-1 truncate text-left">{{ user?.name }}</span>
      <UIcon name="i-heroicons-ellipsis-horizontal" class="h-4 w-4 flex-shrink-0" />
    </button>

    <!-- Header variant: name + avatar -->
    <UButton v-else variant="ghost" color="neutral" class="flex items-center gap-2 rounded-full px-3 py-1.5">
      <span class="text-sm font-medium">{{ user?.name }}</span>
      <UAvatar
        :src="user?.avatar_url ?? undefined"
        :alt="user?.name"
        icon="i-heroicons-user"
        size="sm"
      />
    </UButton>
  </UDropdownMenu>
</template>
