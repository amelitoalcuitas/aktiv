<script setup lang="ts">
definePageMeta({ middleware: 'auth' });

const authStore = useAuthStore();
const slug = authStore.user?.username || authStore.user?.id;
await navigateTo(`/profile/${slug}`, { replace: true });
</script>

<template>
  <div />
</template>
