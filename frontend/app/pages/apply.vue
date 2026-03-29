<script setup lang="ts">
definePageMeta({ layout: 'explore' });

useHead({ title: 'Apply as Hub Owner · Aktiv' });

const authStore = useAuthStore();
const toast = useToast();
const {
  currentRequest,
  currentStatus,
  fetchCurrentRequest,
  submitApplication
} = useHubOwnerRequest();

const loading = ref(true);
const submitting = ref(false);
const submitted = ref(false);
const form = reactive({
  hub_name: '',
  city: '',
  contact_number: '',
  message: ''
});
const errors = reactive({
  hub_name: '',
  city: '',
  contact_number: '',
  message: ''
});

const isRejected = computed(() => currentStatus.value === 'rejected');

function normalizeMessage(value: string): string {
  return value.replace(/\s+/g, ' ').trim();
}

function getMessageCharacterCount(value: string): number {
  return normalizeMessage(value).length;
}

function resetErrors() {
  Object.assign(errors, {
    hub_name: '',
    city: '',
    contact_number: '',
    message: ''
  });
}

function validate(): boolean {
  resetErrors();

  const normalizedMessage = normalizeMessage(form.message);

  if (!normalizedMessage) {
    errors.message = 'Tell us a bit about the hub you want to add.';
  } else if (normalizedMessage.length < 50) {
    errors.message =
      'Please provide at least 50 characters. Extra repeated spaces do not count toward the minimum.';
  }

  return !errors.message;
}

const isAdminUser = computed(
  () =>
    authStore.user?.role === 'admin' || authStore.user?.role === 'super_admin'
);

async function bootstrap() {
  if (!authStore.isAuthenticated) {
    await navigateTo('/auth/login?redirect=/apply');
    return;
  }

  if (!authStore.user) {
    await authStore.fetchUser();
  }

  if (authStore.user && !authStore.user.email_verified_at) {
    await navigateTo('/auth/verify-email');
    return;
  }

  if (isAdminUser.value) {
    loading.value = false;
    return;
  }

  form.contact_number = authStore.user?.contact_number ?? '';

  try {
    const request = await fetchCurrentRequest(true);

    if (request?.status === 'rejected') {
      form.hub_name = request.hub_name ?? '';
      form.city = request.city ?? '';
      form.contact_number = request.contact_number ?? form.contact_number;
      form.message = request.message;
    }
  } finally {
    loading.value = false;
  }
}

async function handleSubmit() {
  submitted.value = true;
  if (!validate()) return;

  submitting.value = true;
  try {
    await submitApplication({
      hub_name: form.hub_name.trim() || null,
      city: form.city.trim() || null,
      contact_number: form.contact_number.trim() || null,
      message: form.message.trim()
    });

    toast.add({
      title: 'Application submitted',
      description: 'We sent your request to the super admin for review.',
      color: 'success'
    });
  } catch (error: any) {
    const apiErrors = error?.data?.errors ?? {};
    if (apiErrors.hub_name) errors.hub_name = apiErrors.hub_name[0];
    if (apiErrors.city) errors.city = apiErrors.city[0];
    if (apiErrors.contact_number)
      errors.contact_number = apiErrors.contact_number[0];
    if (apiErrors.message) errors.message = apiErrors.message[0];

    toast.add({
      title: error?.data?.message ?? 'Failed to submit application.',
      color: 'error'
    });
  } finally {
    submitting.value = false;
  }
}

function formatDateTime(iso: string | null) {
  if (!iso) return '';

  return new Date(iso).toLocaleString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  });
}

await bootstrap();
</script>

<template>
  <div class="bg-[#f4f6f8] py-10">
    <section class="mx-auto w-full max-w-4xl px-4 py-10 md:px-6 md:py-14">
      <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-[0.24em] text-[#0f76bf]">
          Hub Owner Access
        </p>
        <h1 class="mt-3 text-3xl font-black text-[#0f1728] md:text-5xl">
          Apply to bring your sports hub to
          <span class="text-[#004e89]">Aktiv</span>
        </h1>
        <p class="mt-3 max-w-2xl text-sm leading-7 text-[#5d7086] md:text-base">
          Tell us a little about the venue you want to manage. Our team will
          review your request before we unlock your dashboard access.
        </p>
      </div>

      <UCard
        v-if="loading"
        class="rounded-3xl border border-[#dbe4ef] bg-white"
        :ui="{ root: 'ring-0 divide-y-0' }"
      >
        <div class="flex items-center gap-3 py-4 text-sm text-[#64748b]">
          <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
          Loading your application status...
        </div>
      </UCard>

      <UCard
        v-else-if="isAdminUser"
        class="rounded-3xl border border-[#dbe4ef] bg-white"
        :ui="{ root: 'ring-0 divide-y-0' }"
      >
        <div class="text-center">
          <div
            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#e8f0f8]"
          >
            <UIcon
              name="i-heroicons-shield-check"
              class="h-7 w-7 text-[#004e89]"
            />
          </div>
          <h2 class="mt-5 text-2xl font-bold text-[#0f1728]">
            You already have hub owner access
          </h2>
          <p class="mx-auto mt-2 max-w-xl text-sm leading-7 text-[#64748b]">
            Your account already has dashboard access, so you can start creating
            and managing hubs right away.
          </p>
          <div class="mt-6 flex justify-center gap-3">
            <UButton to="/dashboard" class="bg-[#004e89] hover:bg-[#003d6b]">
              Open Dashboard
            </UButton>
            <UButton to="/hubs/create" color="neutral" variant="outline">
              Create a Hub
            </UButton>
          </div>
        </div>
      </UCard>

      <UCard
        v-else-if="currentStatus === 'pending'"
        class="rounded-3xl border border-[#dbe4ef] bg-white"
        :ui="{ root: 'ring-0 divide-y-0' }"
      >
        <div class="text-center">
          <div
            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100"
          >
            <UIcon name="i-heroicons-clock" class="h-7 w-7 text-amber-700" />
          </div>
          <h2 class="mt-5 text-2xl font-bold text-[#0f1728]">
            Request Pending
          </h2>
          <p class="mx-auto mt-2 max-w-xl text-sm leading-7 text-[#64748b]">
            Your hub owner request is currently in pending review. We&apos;ll
            unlock your dashboard access once a super admin approves it.
          </p>

          <div
            v-if="currentRequest"
            class="mx-auto mt-6 max-w-xl rounded-2xl border border-[#e2e8f0] bg-[#f8fafc] p-5 text-left"
          >
            <p
              class="text-xs font-semibold uppercase tracking-[0.18em] text-[#94a3b8]"
            >
              Submitted
            </p>
            <p class="mt-2 text-sm text-[#0f1728]">
              {{ formatDateTime(currentRequest.created_at) }}
            </p>
            <p
              class="mt-4 text-xs font-semibold uppercase tracking-[0.18em] text-[#94a3b8]"
            >
              Your Message
            </p>
            <p class="mt-2 text-sm leading-7 text-[#475569]">
              {{ currentRequest.message }}
            </p>
          </div>
        </div>
      </UCard>

      <UCard
        v-else
        class="rounded-3xl border border-[#dbe4ef] bg-white"
        :ui="{ root: 'ring-0 divide-y-0' }"
      >
        <div class="grid gap-8 md:grid-cols-[1.2fr_0.8fr]">
          <div>
            <div
              v-if="isRejected && currentRequest"
              class="mb-6 rounded-2xl border border-[#fecaca] bg-[#fff1f2] p-5"
            >
              <div class="flex items-start gap-3">
                <div
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100"
                >
                  <UIcon
                    name="i-heroicons-arrow-path-rounded-square"
                    class="h-5 w-5 text-rose-700"
                  />
                </div>
                <div>
                  <h2 class="text-lg font-bold text-[#0f1728]">
                    Update and reapply
                  </h2>
                  <p class="mt-1 text-sm leading-6 text-[#5d7086]">
                    Your last application was reviewed. You can update the
                    details below and submit a new request anytime.
                  </p>
                </div>
              </div>

              <div
                class="mt-4 rounded-2xl border border-[#fee2e2] bg-white/70 p-4"
              >
                <p
                  class="text-xs font-semibold uppercase tracking-[0.18em] text-[#94a3b8]"
                >
                  Reviewed
                </p>
                <p class="mt-2 text-sm text-[#0f1728]">
                  {{ formatDateTime(currentRequest.reviewed_at) }}
                </p>
                <div v-if="currentRequest.review_notes" class="mt-4">
                  <p
                    class="text-xs font-semibold uppercase tracking-[0.18em] text-[#94a3b8]"
                  >
                    Review Note
                  </p>
                  <p class="mt-2 text-sm leading-7 text-[#7f1d1d]">
                    {{ currentRequest.review_notes }}
                  </p>
                </div>
              </div>
            </div>

            <h2 class="text-2xl font-bold text-[#0f1728]">
              {{
                isRejected
                  ? 'Submit a new application'
                  : 'Send your application'
              }}
            </h2>
            <p class="mt-2 text-sm leading-7 text-[#64748b]">
              {{
                isRejected
                  ? 'We prefilled your last submission so you can revise it quickly. Add the missing context from the review and send it back for another look.'
                  : 'Keep it simple for now. Share a few details about the venue you want to bring on board and why you want owner access.'
              }}
            </p>

            <form class="mt-6 space-y-5" @submit.prevent="handleSubmit">
              <UFormField
                label="Hub or business name"
                :error="
                  submitted && errors.hub_name ? errors.hub_name : undefined
                "
              >
                <UInput
                  v-model="form.hub_name"
                  placeholder="Example Sports Hub"
                  class="w-full"
                />
              </UFormField>

              <UFormField
                label="City"
                :error="submitted && errors.city ? errors.city : undefined"
              >
                <UInput
                  v-model="form.city"
                  placeholder="Quezon City"
                  class="w-full"
                />
              </UFormField>

              <UFormField
                label="Contact number"
                :error="
                  submitted && errors.contact_number
                    ? errors.contact_number
                    : undefined
                "
              >
                <UInput
                  v-model="form.contact_number"
                  placeholder="0917 123 4567"
                  class="w-full"
                />
              </UFormField>

              <UFormField
                label="Why should we approve your access?"
                required
                :error="
                  submitted && errors.message ? errors.message : undefined
                "
              >
                <UTextarea
                  v-model="form.message"
                  :rows="6"
                  :maxlength="1000"
                  placeholder="Tell us about your venue, what you plan to manage, and how you’ll use Aktiv."
                  class="w-full"
                />

                <div
                  class="justify-between flex mt-1 text-right text-xs text-[#64748b]"
                >
                  <p>(Min. 50 characters, repeated spaces do not count)</p>
                  <p>
                    {{ getMessageCharacterCount(form.message) }} / 1000
                    characters
                  </p>
                </div>
              </UFormField>

              <UButton
                type="submit"
                :loading="submitting"
                class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
              >
                {{ isRejected ? 'Reapply for Review' : 'Submit Application' }}
              </UButton>
            </form>
          </div>

          <div class="rounded-3xl bg-[#f8fbff] p-6">
            <p
              class="text-xs font-semibold uppercase tracking-[0.2em] text-[#0f76bf]"
            >
              What happens next
            </p>
            <ol class="mt-4 space-y-4 text-sm leading-7 text-[#475569]">
              <li>
                1. Your request is sent to our team as soon as you submit it.
              </li>
              <li>
                2. It is reviewed manually before any owner access is granted.
              </li>
              <li>
                3. If approved, you’ll receive an email with a button to access
                your dashboard.
              </li>
              <li>
                4. From there, you can create your first hub and continue the
                setup process.
              </li>
            </ol>
          </div>
        </div>
      </UCard>
    </section>
  </div>
</template>
