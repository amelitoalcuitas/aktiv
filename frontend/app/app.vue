<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

const { init } = useAuth();

// Rehydrate auth state from cookie on every page load
await init();
</script>

<template>
  <UApp>
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
