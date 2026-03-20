<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

const { isAuthenticated, isAdmin, user, logout: doLogout } = useAuth();

const menuItems = computed(() => {
  const items = [];

  if (isAdmin.value) {
    items.push([
      {
        label: 'Dashboard',
        icon: 'i-heroicons-squares-2x2',
        to: '/dashboard'
      }
    ]);
  }

  items.push([
    {
      label: 'Profile',
      icon: 'i-heroicons-user',
      to: '/profile'
    }
  ]);

  items.push([
    {
      label: 'Sign Out',
      icon: 'i-heroicons-arrow-right-on-rectangle',
      onSelect: doLogout
    }
  ]);

  return items;
});
</script>

<template>
  <header
    class="border-b border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
  >
    <div
      class="mx-auto flex h-[76px] w-full max-w-[1120px] items-center justify-between px-8"
    >
      <!-- Logo -->
      <NuxtLink
        to="/"
        class="inline-flex items-center gap-2.5"
        aria-label="Aktiv home"
      >
        <span
          class="text-xl font-extrabold tracking-tight text-[var(--aktiv-primary)]"
        >
          Aktiv
        </span>
      </NuxtLink>

      <!-- Nav -->
      <nav class="flex items-center gap-2">
        <template v-if="isAuthenticated">
          <UDropdownMenu :modal="false" :items="menuItems">
            <UButton variant="ghost" color="neutral" class="rounded-full p-0.5">
              <UAvatar
                :src="user?.avatar_url ?? undefined"
                :alt="user?.name"
                icon="i-heroicons-user"
                size="sm"
              />
            </UButton>
          </UDropdownMenu>
        </template>
        <template v-else>
          <UButton to="/auth/login" size="sm" color="primary">
            Sign In
          </UButton>
        </template>
      </nav>
    </div>
  </header>
</template>
