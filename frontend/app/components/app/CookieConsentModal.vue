<script setup lang="ts">
const emit = defineEmits<{ accept: []; decline: [] }>();
</script>

<template>
  <AppModal
    :open="true"
    title="We value your privacy"
    :dismissible="false"
    confirm="Okay, I understand"
    cancel="Decline"
    :handle="false"
    @confirm="emit('accept')"
    @cancel="emit('decline')"
  >
    <template #body>
      <p class="text-sm text-muted">
        We use cookies to make Aktiv better for you — from keeping things
        running smoothly to understanding what works best. Tap “Okay, I
        understand” to accept, or “Decline” if you'd prefer not to share this
        data.
      </p>
    </template>
  </AppModal>
</template>
