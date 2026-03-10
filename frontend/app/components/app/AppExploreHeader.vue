<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

const { isAuthenticated, logout: doLogout } = useAuth();
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
          <NuxtLink
            to="/dashboard"
            class="rounded-lg px-3 py-2 text-sm font-medium text-[var(--aktiv-ink)] transition hover:text-[var(--aktiv-primary)]"
          >
            Dashboard
          </NuxtLink>
          <UButton
            color="neutral"
            variant="ghost"
            size="sm"
            icon="i-heroicons-arrow-right-on-rectangle"
            @click="doLogout"
          >
            Sign Out
          </UButton>
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
