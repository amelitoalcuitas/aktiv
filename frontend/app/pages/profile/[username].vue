<script setup lang="ts">
const route = useRoute();
const { resolveUsername } = useProfile();

const username = computed(() => String(route.params.username));

const { data: userId, error } = await useAsyncData(
  `resolve-username-${username.value}`,
  () => resolveUsername(username.value),
);

if (error.value || !userId.value) {
  throw createError({ statusCode: 404, statusMessage: 'Profile not found', fatal: true });
}

await navigateTo(`/users/${userId.value}`, { replace: true });
</script>

<template>
  <div />
</template>
