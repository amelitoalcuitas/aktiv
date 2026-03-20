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
  <header class="absolute inset-x-0 top-0 z-30">
    <div
      class="mx-auto flex h-[76px] w-full max-w-[1280px] items-center justify-between px-4 md:px-8"
    >
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
          <NuxtLink
            to="/auth/login"
            class="rounded-lg px-3 py-2 text-sm font-medium text-[var(--aktiv-ink)] transition hover:text-[var(--aktiv-primary)]"
          >
            Sign In
          </NuxtLink>
          <UButton to="/auth/register" size="sm" color="primary">
            Register
          </UButton>
        </template>
      </nav>
    </div>
  </header>
</template>
