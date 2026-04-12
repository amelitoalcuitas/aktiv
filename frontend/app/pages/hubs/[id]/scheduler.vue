<script setup lang="ts">
definePageMeta({ layout: 'hub' });

const route = useRoute();

await navigateTo(`/hubs/${route.params.id}/about`, { replace: true });
</script>

<template>
  <USkeleton class="h-[600px] w-full rounded-2xl" />
</template>
