<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

const { isAuthenticated } = useAuth();
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
        <AppIcon class="h-6 w-auto" />
        <AppLogo class="h-6 w-auto" />
      </NuxtLink>

      <nav class="flex items-center gap-2">
        <template v-if="isAuthenticated">
          <AppUserMenu variant="header" />
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
