<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';
import { useSettings } from '~/composables/useSettings';

definePageMeta({ layout: 'auth' });

const authStore = useAuthStore();
const { cancelDeletion } = useSettings();
const toast = useToast();

const deletionDate = computed(() => {
  const iso = authStore.user?.deletion_scheduled_at;
  if (!iso) return '';
  return formatInViewerTimezone(iso, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  }, 'en-PH');
});

const restoring = ref(false);

async function handleRestore() {
  restoring.value = true;
  try {
    const res = await cancelDeletion();
    authStore.setUser((res as { data: typeof authStore.user }).data);
    toast.add({ title: 'Account restored', description: 'Welcome back! Your account is active again.', color: 'success' });
    await navigateTo('/dashboard');
  } catch {
    toast.add({ title: 'Something went wrong', description: 'Please try again.', color: 'error' });
  } finally {
    restoring.value = false;
  }
}

function handleContinueDeletion() {
  authStore.logout();
  navigateTo('/auth/login');
}
</script>

<template>
  <UCard :ui="{ root: 'ring-1 ring-[#dbe4ef]' }">
    <template #header>
      <h1 class="text-xl font-bold text-[#0f1728]">Your account is scheduled for deletion</h1>
      <p class="mt-1 text-sm text-[#64748b]">
        Hi {{ authStore.user?.first_name }}, your account will be permanently deleted on
        <span class="font-medium text-red-600">{{ deletionDate }}</span>.
      </p>
    </template>

    <p class="text-sm text-[#64748b] mb-6">
      Changed your mind? You can restore your account right now and everything will go back to normal.
      Once the deletion date passes, your account and all associated data will be permanently removed and cannot be recovered.
    </p>

    <div class="flex flex-col gap-3">
      <UButton
        block
        :loading="restoring"
        class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
        @click="handleRestore"
      >
        Restore My Account
      </UButton>

      <UButton
        block
        variant="ghost"
        color="error"
        :disabled="restoring"
        @click="handleContinueDeletion"
      >
        Continue with Deletion
      </UButton>
    </div>
  </UCard>
</template>
