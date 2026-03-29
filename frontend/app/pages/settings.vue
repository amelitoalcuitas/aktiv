<script setup lang="ts">
definePageMeta({ middleware: ['auth'], layout: 'page' });
useHead({ title: 'Settings · Aktiv' });

const route = useRoute();
const authStore = useAuthStore();
const {
  updateProfile,
  canChangeName,
  canChangeUsername,
  nextNameChangeDate,
  nextUsernameChangeDate
} = useProfile();
const { requestDeletion, requestPasswordChange, requestPasswordChangeStatus } = useSettings();
const { logout } = useAuth();
const toast = useToast();
const { remainingSeconds: passwordCooldown, isCoolingDown: isPasswordCoolingDown, sync: syncPasswordCooldown } = useCooldownTimer();

const user = computed(() => authStore.user!);

// ── Basic info form ───────────────────────────────────────────────────────────
const form = reactive({
  first_name: '',
  last_name: '',
  username: '',
  contact_number: ''
});
const savingProfile = ref(false);

watchEffect(() => {
  if (user.value) {
    form.first_name = user.value.first_name ?? '';
    form.last_name = user.value.last_name ?? '';
    form.username = user.value.username ?? '';
    form.contact_number = user.value.contact_number ?? '';
  }
});

async function saveProfile() {
  savingProfile.value = true;
  try {
    await updateProfile({
      first_name: form.first_name,
      last_name: form.last_name,
      username: form.username || null,
      contact_number: form.contact_number || null
    });
    toast.add({ title: 'Profile updated', color: 'success' });
  } catch (e: unknown) {
    const errors = (e as { data?: { errors?: Record<string, string[]> } })?.data
      ?.errors;
    const firstError = errors ? Object.values(errors)[0]?.[0] : null;
    toast.add({
      title: firstError ?? 'Failed to save changes.',
      color: 'error'
    });
  } finally {
    savingProfile.value = false;
  }
}

const nameChangeBlocked = computed(
  () => !!user.value && !canChangeName(user.value)
);
const usernameChangeBlocked = computed(
  () => !!user.value && !canChangeUsername(user.value)
);

function formatDate(d: Date | null) {
  if (!d) return '';
  return d.toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  });
}

// ── Password change ───────────────────────────────────────────────────────────
const changePasswordOpen = ref(false);
const changingPassword = ref(false);

onMounted(async () => {
  try {
    const response = await requestPasswordChangeStatus();
    syncPasswordCooldown(response.cooldown);
  } catch {
    syncPasswordCooldown(null);
  }
});

const cooldownLabel = computed(() => {
  if (passwordCooldown.value <= 0) return 'Change Password';
  const m = Math.floor(passwordCooldown.value / 60);
  const s = String(passwordCooldown.value % 60).padStart(2, '0');
  return `Resend in ${m}:${s}`;
});

async function handleChangePassword() {
  changePasswordOpen.value = false;
  changingPassword.value = true;
  try {
    const response = await requestPasswordChange();
    syncPasswordCooldown(response.cooldown);
    toast.add({
      title: 'Check your email',
      description: 'A password change link has been sent to your email address.',
      color: 'success'
    });
  } catch (err: any) {
    syncPasswordCooldown(err?.data?.cooldown ?? null);

    const is429 = err?.response?.status === 429 || err?.status === 429;
    if (is429) {
      const retryAfter = Number(
        err?.data?.cooldown?.remaining_seconds ??
        err?.response?.headers?.get?.('Retry-After') ??
        err?.response?.headers?.['retry-after'] ??
        0
      );
      const mins = retryAfter > 0 ? Math.ceil(retryAfter / 60) : 5;

      toast.add({
        title: 'Please wait',
        description: `You can request another password email in ${mins} minute${mins !== 1 ? 's' : ''}.`,
        color: 'error'
      });
    } else {
      toast.add({ title: 'Something went wrong. Please try again.', color: 'error' });
    }
  } finally {
    changingPassword.value = false;
  }
}

// ── Account deletion ──────────────────────────────────────────────────────────
const step1Open = ref(false);
const step2Open = ref(false);
const currentPassword = ref('');
const deletingAccount = ref(false);

watch(step2Open, (val) => {
  if (!val) currentPassword.value = '';
});

async function confirmDeletion() {
  deletingAccount.value = true;
  try {
    await requestDeletion({ current_password: currentPassword.value });
    step2Open.value = false;
    currentPassword.value = '';
    toast.add({
      title: 'Account scheduled for deletion',
      description: 'You have 30 days to log back in and change your mind.',
      color: 'warning',
      duration: 10000
    });
    await logout();
  } catch (e: unknown) {
    const errors = (e as { data?: { errors?: Record<string, string[]> } })?.data
      ?.errors;
    const firstError = errors ? Object.values(errors)[0]?.[0] : null;
    toast.add({ title: firstError ?? 'Incorrect password.', color: 'error' });
  } finally {
    deletingAccount.value = false;
  }
}

const tabs = [
  { label: 'Account', value: 'account', slot: 'account' },
  { label: 'Privacy', value: 'privacy', slot: 'privacy' },
  { label: 'Security', value: 'security', slot: 'security' }
];

const activeTab = ref('account');
watch(
  () => route.query.tab,
  (tab) => {
    activeTab.value = (tab as string) ?? 'account';
  },
  { immediate: true }
);

// ── Privacy settings ──────────────────────────────────────────────────────────
const savingPrivacy = ref(false);

const privacyDefaults = {
  profile_visible_to: 'everyone' as 'everyone' | 'no_one',
  show_full_name: true,
  show_owned_hubs: true,
  show_visited_hubs: true,
  show_leaderboard: true,
  show_hearts: true,
  show_tournaments: true,
  show_open_play: true,
  show_favorite_sports: true,
  show_joined_hubs: true
};

const privacy = computed(() => ({
  ...privacyDefaults,
  ...user.value?.profile_privacy
}));


async function togglePrivacy(key: string, val: boolean | string) {
  savingPrivacy.value = true;
  try {
    const updated = await updateProfile({ profile_privacy: { [key]: val } });
    authStore.setUser(updated);
    toast.add({ title: 'Privacy settings saved', color: 'success' });
  } catch {
    toast.add({ title: 'Failed to save privacy settings.', color: 'error' });
  } finally {
    savingPrivacy.value = false;
  }
}
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold">Settings</h1>

    <UTabs :items="tabs" v-model="activeTab">
      <!-- Account Tab -->
      <template #account>
        <div class="mt-6 space-y-8">
          <!-- Basic Info -->
          <div class="rounded-xl border border-[#d0dce8] bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-[#3a4a5c]">
              Basic Information
            </h2>
            <div class="space-y-4">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <UFormField label="First name">
                  <UInput
                    v-model="form.first_name"
                    :disabled="nameChangeBlocked"
                    class="w-full"
                  />
                  <template v-if="nameChangeBlocked" #hint>
                    <span class="text-xs text-[var(--ui-text-muted)]">
                      Can change again on
                      {{ formatDate(nextNameChangeDate(user)) }}.
                    </span>
                  </template>
                </UFormField>
                <UFormField label="Last name">
                  <UInput
                    v-model="form.last_name"
                    :disabled="nameChangeBlocked"
                    class="w-full"
                  />
                </UFormField>
              </div>
              <UFormField label="Username">
                <UInput
                  v-model="form.username"
                  :disabled="usernameChangeBlocked"
                  placeholder="your_username"
                  class="w-full"
                />
                <template v-if="usernameChangeBlocked" #hint>
                  <span class="text-xs text-[var(--ui-text-muted)]">
                    Can change again on
                    {{ formatDate(nextUsernameChangeDate(user)) }}.
                  </span>
                </template>
              </UFormField>
              <UFormField label="Email">
                <UInput :model-value="user.email" disabled class="w-full" />
                <template #hint>
                  <span class="text-xs text-[var(--ui-text-muted)]"
                    >Email cannot be changed.</span
                  >
                </template>
              </UFormField>
              <UFormField label="Contact number">
                <UInput
                  v-model="form.contact_number"
                  placeholder="+63 912 345 6789"
                  class="w-full"
                />
              </UFormField>
              <div class="flex justify-end">
                <UButton
                  color="primary"
                  :loading="savingProfile"
                  @click="saveProfile"
                >
                  Save Changes
                </UButton>
              </div>
            </div>
          </div>

          <!-- Danger Zone -->
          <div class="rounded-xl border border-red-200 bg-white p-6">
            <h2 class="mb-1 text-base font-semibold text-red-600">
              Danger Zone
            </h2>
            <p class="mb-4 text-sm text-[var(--ui-text-muted)]">
              Once you request account deletion, your account is hidden
              immediately. You have 30 days to log back in and cancel before all
              data is permanently removed.
            </p>
            <UButton color="error" variant="soft" @click="step1Open = true">
              Delete Account
            </UButton>
          </div>
        </div>
      </template>

      <!-- Privacy Tab -->
      <template #privacy>
        <div class="mt-6 space-y-6">
          <!-- Profile -->
          <div class="rounded-xl border border-[#d0dce8] bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-[#3a4a5c]">Profile</h2>
            <div class="space-y-5">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-sm font-medium text-[var(--aktiv-ink)]">Profile visible to everyone</p>
                  <p class="text-xs text-[var(--ui-text-muted)]">
                    When off, your profile page will show as private and your info will be hidden in hub member lists.
                  </p>
                </div>
                <USwitch
                  :model-value="privacy.profile_visible_to === 'everyone'"
                  :disabled="savingPrivacy"
                  @update:model-value="(val) => togglePrivacy('profile_visible_to', val ? 'everyone' : 'no_one')"
                />
              </div>
              <USeparator />
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-sm font-medium text-[var(--aktiv-ink)]">
                    Show full name
                  </p>
                  <p class="text-xs text-[var(--ui-text-muted)]">
                    When off, your first and last name are hidden from your
                    public profile and hub member listings.
                  </p>
                </div>
                <USwitch
                  :model-value="privacy.show_full_name"
                  :disabled="savingPrivacy"
                  @update:model-value="
                    (val) => togglePrivacy('show_full_name', val)
                  "
                />
              </div>
            </div>
          </div>

          <!-- Profile Sections -->
          <div class="rounded-xl border border-[#d0dce8] bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-[#3a4a5c]">
              Profile Sections
            </h2>
            <p class="mb-4 text-sm text-[var(--ui-text-muted)]">
              Choose which sections are visible on your public profile.
            </p>
            <div class="space-y-4">
              <div
                v-for="item in [
                  { key: 'show_joined_hubs', label: 'Joined Hubs' },
                  { key: 'show_owned_hubs', label: 'Owned Hubs' },
                  { key: 'show_visited_hubs', label: 'Visited Hubs' },
                  { key: 'show_leaderboard', label: 'Rankings & Stats' },
                  { key: 'show_tournaments', label: 'Tournaments' },
                  { key: 'show_open_play', label: 'Open Play' },
                  { key: 'show_hearts', label: 'Hearts' },
                  { key: 'show_favorite_sports', label: 'Favorite Sports' }
                ]"
                :key="item.key"
                class="flex items-center justify-between gap-4"
              >
                <p class="text-sm font-medium text-[var(--aktiv-ink)]">
                  {{ item.label }}
                </p>
                <USwitch
                  :model-value="
                    privacy[item.key as keyof typeof privacy] as boolean
                  "
                  :disabled="savingPrivacy"
                  @update:model-value="(val) => togglePrivacy(item.key, val)"
                />
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Security Tab -->
      <template #security>
        <div class="mt-6 rounded-xl border border-[#d0dce8] bg-white p-6">
          <h3 class="mb-1 text-sm font-semibold text-[var(--ui-text)]">Password</h3>
          <p class="mb-4 text-sm text-[var(--ui-text-muted)]">
            We'll send a secure link to your email address. Follow it to set a new password.
          </p>
          <UButton
            :label="cooldownLabel"
            color="neutral"
            variant="outline"
            :loading="changingPassword"
            :disabled="isPasswordCoolingDown"
            @click="changePasswordOpen = true"
          />
        </div>
      </template>
    </UTabs>

    <!-- Change password confirmation -->
    <AppModal
      v-if="changePasswordOpen"
      v-model:open="changePasswordOpen"
      title="Change your password?"
      description="We'll send a password change link to your email address."
      confirm="Send Link"
      cancel="Cancel"
      :confirm-loading="changingPassword"
      @confirm="handleChangePassword"
      @cancel="changePasswordOpen = false"
    />

    <!-- Step 1: What you'll lose -->
    <AppModal
      v-if="step1Open"
      v-model:open="step1Open"
      title="Delete your account?"
      description="This will schedule your account for permanent deletion. You have 30 days to change your mind."
      confirm="Continue"
      cancel="Cancel"
      confirm-color="error"
      @confirm="
        step1Open = false;
        step2Open = true;
      "
      @cancel="step1Open = false"
    >
      <template #body>
        <div class="space-y-3 py-2">
          <p class="text-sm font-medium text-[#3a4a5c]">
            You will lose access to:
          </p>
          <ul class="space-y-2 text-sm text-[var(--ui-text-muted)]">
            <li class="flex items-start gap-2">
              <UIcon
                name="i-heroicons-user-circle"
                class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-400"
              />
              <span>Your profile, avatar, and banner</span>
            </li>
            <li class="flex items-start gap-2">
              <UIcon
                name="i-heroicons-calendar-days"
                class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-400"
              />
              <span>All past and upcoming bookings</span>
            </li>
            <li class="flex items-start gap-2">
              <UIcon
                name="i-heroicons-building-storefront"
                class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-400"
              />
              <span>Hub memberships and any hub you own</span>
            </li>
            <li class="flex items-start gap-2">
              <UIcon
                name="i-heroicons-star"
                class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-400"
              />
              <span>Reviews written and hearts given or received</span>
            </li>
          </ul>
          <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
            Your account will be hidden immediately and permanently deleted
            after 30 days. Log back in any time during that period to cancel.
          </p>
        </div>
      </template>
    </AppModal>

    <!-- Step 2: Password confirmation -->
    <AppModal
      v-if="step2Open"
      v-model:open="step2Open"
      title="Confirm your identity"
      description="Enter your current password to confirm account deletion."
      confirm="Delete My Account"
      cancel="Cancel"
      confirm-color="error"
      :confirm-loading="deletingAccount"
      :confirm-disabled="!currentPassword"
      @confirm="confirmDeletion"
      @cancel="
        step2Open = false;
        currentPassword = '';
      "
    >
      <template #body>
        <div class="py-2">
          <UFormField label="Current password">
            <UInput
              v-model="currentPassword"
              type="password"
              placeholder="Enter your password"
              autofocus
              class="w-full"
              @keydown.enter="currentPassword && confirmDeletion()"
            />
          </UFormField>
        </div>
      </template>
    </AppModal>
  </div>
</template>
