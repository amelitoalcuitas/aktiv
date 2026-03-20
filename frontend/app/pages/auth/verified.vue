<script setup lang="ts">
definePageMeta({ layout: 'auth' });

const route = useRoute();
const status = computed(() => route.query.status);
const isSuccess = computed(() => status.value === 'success');
</script>

<template>
  <UCard :ui="{ root: 'shadow-lg ring-1 ring-[#dbe4ef]' }">
    <template #header>
      <h1 class="text-xl font-bold text-[#0f1728]">
        {{ isSuccess ? 'Email verified' : 'Verification failed' }}
      </h1>
    </template>

    <div v-if="isSuccess" class="space-y-4">
      <p class="text-sm text-[#64748b]">
        Your email has been verified. You can now access your dashboard.
      </p>
      <UButton
        block
        class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
        @click="navigateTo('/dashboard')"
      >
        Go to Dashboard
      </UButton>
    </div>

    <div v-else class="space-y-4">
      <p class="text-sm text-[#64748b]">
        The verification link is invalid or has expired.
      </p>
      <UButton
        block
        class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
        @click="navigateTo('/auth/verify-email')"
      >
        Resend verification email
      </UButton>
    </div>
  </UCard>
</template>
