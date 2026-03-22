<script setup lang="ts">
import type { Hub } from '~/types/hub';
import { useHubStore } from '~/stores/hub';
import { useHubs } from '~/composables/useHubs';

definePageMeta({ layout: 'dashboard', middleware: ['auth', 'admin'] });

const hubStore = useHubStore();
const { updateHub } = useHubs();
const toast = useToast();

// ── Hub selector ──────────────────────────────────────────────

const selectedHubId = ref<number | undefined>(undefined);

const hubOptions = computed(() =>
  hubStore.myHubs.map((h: Hub) => ({ label: h.name, value: h.id }))
);

const selectedHub = computed<Hub | undefined>(() =>
  hubStore.myHubs.find((h: Hub) => h.id === selectedHubId.value)
);

// ── Booking settings ──────────────────────────────────────────

// true = require account (default), false = allow guests
const requireAccount = ref(true);
const guestBookingLimit = ref(1);
const guestMaxHours = ref(2);

// ── Payment settings ──────────────────────────────────────────

const payOnSite = ref(true);
const digitalBank = ref(false);
const paymentQrFile = ref<File | null>(null);
const paymentQrPreview = ref<string | null>(null);
const removePaymentQr = ref(false);
const digitalBankName = ref('');
const digitalBankAccount = ref('');

watch(selectedHub, (hub) => {
  if (hub) {
    requireAccount.value = hub.require_account_to_book;
    guestBookingLimit.value = hub.guest_booking_limit ?? 1;
    guestMaxHours.value = hub.guest_max_hours ?? 2;
    const methods = hub.payment_methods ?? ['pay_on_site'];
    payOnSite.value = methods.includes('pay_on_site');
    digitalBank.value = methods.includes('digital_bank');
    paymentQrPreview.value = hub.payment_qr_url ?? null;
    paymentQrFile.value = null;
    removePaymentQr.value = false;
    digitalBankName.value = hub.digital_bank_name ?? '';
    digitalBankAccount.value = hub.digital_bank_account ?? '';
  }
});

function onPaymentQrChange(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;
  if (file.size > 10 * 1024 * 1024) {
    toast.add({
      title: 'File too large',
      description: 'Max file size is 10MB.',
      color: 'error'
    });
    return;
  }
  paymentQrFile.value = file;
  paymentQrPreview.value = URL.createObjectURL(file);
  removePaymentQr.value = false;
}

function clearPaymentQr() {
  paymentQrFile.value = null;
  paymentQrPreview.value = null;
  removePaymentQr.value = true;
}

// ── Init ─────────────────────────────────────────────────────

onMounted(async () => {
  await hubStore.fetchMyHubs();
  if (hubStore.myHubs.length > 0) {
    selectedHubId.value = hubStore.myHubs[0]!.id;
  }
});

// ── Apply to all hubs ─────────────────────────────────────────

const showApplyAllModal = ref(false);
const isApplyingAll = ref(false);

async function applyToAllHubs() {
  const otherHubs = hubStore.myHubs.filter(
    (h: Hub) => h.id !== selectedHubId.value
  );
  if (otherHubs.length === 0) return;

  if (!payOnSite.value && !digitalBank.value) {
    toast.add({
      title: 'Select a payment method',
      description: 'At least one payment method must be enabled.',
      color: 'error'
    });
    showApplyAllModal.value = false;
    return;
  }

  if (digitalBank.value && !paymentQrPreview.value && !paymentQrFile.value) {
    toast.add({
      title: 'Payment QR code required',
      description: 'Upload a QR code for Digital Bank payments.',
      color: 'error'
    });
    showApplyAllModal.value = false;
    return;
  }

  isApplyingAll.value = true;
  try {
    const paymentMethods: Array<'pay_on_site' | 'digital_bank'> = [];
    if (payOnSite.value) paymentMethods.push('pay_on_site');
    if (digitalBank.value) paymentMethods.push('digital_bank');

    // If there's an existing QR from the server (no new file selected), fetch it as a File
    let qrFile = paymentQrFile.value;
    if (
      digitalBank.value &&
      !qrFile &&
      paymentQrPreview.value &&
      !removePaymentQr.value
    ) {
      const res = await fetch(paymentQrPreview.value);
      const blob = await res.blob();
      qrFile = new File([blob], 'payment_qr.jpg', { type: blob.type });
    }

    await Promise.all(
      otherHubs.map((h: Hub) =>
        updateHub(h.id, {
          require_account_to_book: requireAccount.value,
          guest_booking_limit: requireAccount.value
            ? undefined
            : guestBookingLimit.value,
          guest_max_hours: requireAccount.value
            ? undefined
            : guestMaxHours.value,
          payment_methods: paymentMethods,
          payment_qr_image: qrFile,
          ...(removePaymentQr.value ? { remove_payment_qr: true } : {}),
          digital_bank_name: digitalBankName.value || null,
          digital_bank_account: digitalBankAccount.value || null
        })
      )
    );

    await hubStore.fetchMyHubs();

    toast.add({
      title: 'Applied to all hubs',
      description: 'Settings have been applied to all your hubs.',
      color: 'success'
    });
  } catch {
    toast.add({
      title: 'Failed to apply',
      description: 'Something went wrong. Please try again.',
      color: 'error'
    });
  } finally {
    isApplyingAll.value = false;
    showApplyAllModal.value = false;
  }
}

// ── Save ─────────────────────────────────────────────────────

const isSaving = ref(false);

async function saveSettings() {
  if (!selectedHubId.value) return;

  if (!payOnSite.value && !digitalBank.value) {
    toast.add({
      title: 'Select a payment method',
      description: 'At least one payment method must be enabled.',
      color: 'error'
    });
    return;
  }

  if (digitalBank.value && !paymentQrPreview.value && !paymentQrFile.value) {
    toast.add({
      title: 'Payment QR code required',
      description: 'Upload a QR code for Digital Bank payments.',
      color: 'error'
    });
    return;
  }

  isSaving.value = true;
  try {
    const paymentMethods: Array<'pay_on_site' | 'digital_bank'> = [];
    if (payOnSite.value) paymentMethods.push('pay_on_site');
    if (digitalBank.value) paymentMethods.push('digital_bank');

    await updateHub(selectedHubId.value, {
      require_account_to_book: requireAccount.value,
      guest_booking_limit: requireAccount.value
        ? undefined
        : guestBookingLimit.value,
      guest_max_hours: requireAccount.value ? undefined : guestMaxHours.value,
      payment_methods: paymentMethods,
      payment_qr_image: paymentQrFile.value,
      ...(removePaymentQr.value ? { remove_payment_qr: true } : {}),
      digital_bank_name: digitalBankName.value || null,
      digital_bank_account: digitalBankAccount.value || null
    });

    // Update local store
    await hubStore.fetchMyHubs();

    toast.add({
      title: 'Settings saved',
      description: 'Hub settings have been updated.',
      color: 'success'
    });
  } catch {
    toast.add({
      title: 'Failed to save',
      description: 'Something went wrong. Please try again.',
      color: 'error'
    });
  } finally {
    isSaving.value = false;
  }
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-[#0f1728]">Settings</h1>
      <p class="mt-1 text-sm text-[#64748b]">Configure your hub preferences.</p>
    </div>

    <!-- No hubs state -->
    <div
      v-if="hubStore.myHubs.length === 0"
      class="rounded-xl border border-[#dbe4ef] bg-white p-8 text-center text-sm text-[#64748b]"
    >
      You don't have any hubs yet.
      <NuxtLink
        to="/hubs/create"
        class="ml-1 font-semibold text-[#004e89] hover:underline"
        >Create one.</NuxtLink
      >
    </div>

    <template v-else>
      <!-- Hub selector -->
      <div class="mb-6 flex items-center gap-3">
        <label class="text-sm font-medium text-[#0f1728]">Hub:</label>
        <USelect
          v-model="selectedHubId"
          :items="hubOptions"
          option-attribute="label"
          value-attribute="value"
          placeholder="Select a hub"
        />
      </div>

      <div v-if="selectedHub" class="max-w-2xl space-y-6">
        <!-- Booking section -->
        <div class="rounded-xl border border-[#dbe4ef] bg-white p-6">
          <h2 class="mb-4 text-base font-semibold text-[#0f1728]">Booking</h2>

          <div class="space-y-4">
            <!-- Require Account toggle -->
            <div class="flex flex-col gap-3">
              <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-[#0f1728]"
                    >Require Account when Booking</span
                  >
                  <UPopover mode="hover" :open-delay="100">
                    <UIcon
                      name="i-heroicons-information-circle"
                      class="h-4 w-4 text-[#64748b] cursor-help"
                    />

                    <template #content>
                      <div
                        class="max-w-xs rounded-md bg-white p-3 text-sm text-[#0f1728] shadow-lg border border-[#dbe4ef]"
                      >
                        <span>
                          When enabled, only registered users can book courts at
                          this hub. When disabled, guests may book with email
                          verification (limits configurable below).
                        </span>
                      </div>
                    </template>
                  </UPopover>
                </div>
                <USwitch v-model="requireAccount" />
              </div>

              <!-- Guest booking limit fields (shown when guests are allowed) -->
              <div
                v-if="!requireAccount"
                class="ml-4 border-l-2 border-[#dbe4ef] pl-4 flex flex-col gap-3"
              >
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-[#0f1728]"
                    >Max active bookings per guest</label
                  >
                  <UInput
                    v-model.number="guestBookingLimit"
                    type="number"
                    :min="1"
                    :max="10"
                    class="max-w-[100px]"
                  />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-[#0f1728]"
                    >Max hours per guest session</label
                  >
                  <UInput
                    v-model.number="guestMaxHours"
                    type="number"
                    :min="1"
                    :max="12"
                    class="max-w-[100px]"
                  />
                </div>
              </div>
            </div>

            <template v-if="!requireAccount">
              <UAlert
                color="info"
                variant="soft"
                icon="i-heroicons-envelope"
                title="Guest bookings enabled"
                :description="`Guests will be required to verify their email address before booking. This confirms their identity and prevents spam bookings. Guests are limited to ${guestBookingLimit} active booking${guestBookingLimit !== 1 ? 's' : ''} and a maximum of ${guestMaxHours} hour${guestMaxHours !== 1 ? 's' : ''} per session.`"
              />
            </template>
          </div>
        </div>

        <!-- Payment section -->
        <div class="rounded-xl border border-[#dbe4ef] bg-white p-6">
          <h2 class="mb-1 text-base font-semibold text-[#0f1728]">Payment</h2>
          <p class="mb-4 text-sm text-[#64748b]">
            Choose how customers can pay for their bookings. You can enable
            multiple options.
          </p>

          <div class="space-y-4">
            <!-- Pay on Site -->
            <div
              class="rounded-lg border border-[#dbe4ef] p-4 transition-colors"
              :class="
                payOnSite ? 'bg-[#f0f7ff] border-[#004e89]/30' : 'bg-white'
              "
            >
              <div class="flex items-start gap-3">
                <UCheckbox v-model="payOnSite" class="mt-0.5" />
                <div>
                  <p class="text-sm font-medium text-[#0f1728]">Pay on Site</p>
                  <p class="mt-0.5 text-xs text-[#64748b]">
                    Customers receive a unique booking code and QR when they
                    book. They present it at the venue and you scan it to
                    confirm their payment on arrival.
                  </p>
                </div>
              </div>
            </div>

            <!-- Digital Bank -->
            <div
              class="rounded-lg border border-[#dbe4ef] p-4 transition-colors"
              :class="
                digitalBank ? 'bg-[#f0f7ff] border-[#004e89]/30' : 'bg-white'
              "
            >
              <div class="flex items-start gap-3">
                <UCheckbox v-model="digitalBank" class="mt-0.5" />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-[#0f1728]">
                    Digital Bank
                    <span class="font-normal text-[#64748b]"
                      >(GCash, Maya, Maribank, etc.)</span
                    >
                  </p>
                  <p class="mt-0.5 text-xs text-[#64748b]">
                    Customers send payment digitally before arriving. Upload
                    your payment QR code so customers know where to send their
                    payment.
                  </p>

                  <!-- Digital bank account info -->
                  <div v-if="digitalBank" class="mt-4 grid grid-cols-2 gap-3">
                    <UFormField label="Digital Bank">
                      <UInput
                        v-model="digitalBankName"
                        placeholder="e.g. GCash, Maya"
                        class="w-full"
                      />
                    </UFormField>
                    <UFormField label="Account Number">
                      <UInput
                        v-model="digitalBankAccount"
                        placeholder="e.g. 09XX XXX XXXX"
                        class="w-full"
                      />
                    </UFormField>
                  </div>

                  <!-- QR uploader — only shown when digital bank is enabled -->
                  <div v-if="digitalBank" class="mt-3 space-y-3">
                    <p class="text-xs font-medium text-[#0f1728]">
                      Payment QR Code <span class="text-red-500">*</span>
                    </p>

                    <!-- Existing QR preview -->
                    <div v-if="paymentQrPreview" class="flex items-start gap-3">
                      <AppImageViewer
                        :src="paymentQrPreview"
                        alt="Payment QR code"
                        image-class="h-28 w-28 rounded-lg border border-[#dbe4ef] object-contain bg-white"
                      />
                      <div class="text-xs text-[#64748b] space-y-1.5">
                        <p>
                          {{
                            paymentQrFile
                              ? 'New image selected.'
                              : 'Current QR code.'
                          }}
                        </p>
                        <p>Upload a new image to replace it.</p>
                        <button
                          type="button"
                          class="flex items-center gap-1 text-red-500 hover:text-red-600 font-medium"
                          @click="clearPaymentQr"
                        >
                          <UIcon name="i-heroicons-trash" class="h-3.5 w-3.5" />
                          Remove
                        </button>
                      </div>
                    </div>

                    <label
                      class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-[#dbe4ef] bg-[#f9fdf2] px-4 py-3 text-sm text-[#64748b] hover:border-[#004e89]/40 transition-colors"
                    >
                      <UIcon
                        name="i-heroicons-arrow-up-tray"
                        class="h-4 w-4 shrink-0"
                      />
                      <span>{{
                        paymentQrFile
                          ? paymentQrFile.name
                          : 'Upload QR image (JPG, PNG, WebP · max 10MB)'
                      }}</span>
                      <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden"
                        @change="onPaymentQrChange"
                      />
                    </label>

                    <p
                      v-if="!paymentQrPreview && !paymentQrFile"
                      class="text-xs text-amber-600"
                    >
                      A payment QR code is required for Digital Bank payments.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- More options coming soon -->
            <UAlert
              color="info"
              variant="soft"
              icon="i-heroicons-sparkles"
              title="More payment options coming soon"
              description="Additional payment integrations are on the way."
            />
          </div>
        </div>

        <!-- Save -->
        <div class="flex items-center justify-between">
          <UButton
            v-if="hubStore.myHubs.length > 1"
            variant="solid"
            color="primary"
            icon="i-heroicons-squares-2x2"
            @click="showApplyAllModal = true"
          >
            Apply to all Hubs
          </UButton>
          <div v-else />
          <UButton
            :loading="isSaving"
            class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
            @click="saveSettings"
          >
            Save Settings
          </UButton>
        </div>

        <!-- Apply to all hubs confirmation modal -->
        <UModal v-model:open="showApplyAllModal" title="Apply to all Hubs">
          <template #body>
            <p class="text-sm">
              This will overwrite the booking and payment settings for all your
              other hubs with the current values, including the payment QR code.
            </p>
            <p class="mt-2 text-sm text-muted">
              Are you sure you want to continue?
            </p>
          </template>
          <template #footer>
            <div class="flex justify-end gap-3">
              <UButton
                variant="ghost"
                color="neutral"
                @click="showApplyAllModal = false"
              >
                Cancel
              </UButton>
              <UButton
                :loading="isApplyingAll"
                color="primary"
                class="font-semibold"
                @click="applyToAllHubs"
              >
                Apply to all Hubs
              </UButton>
            </div>
          </template>
        </UModal>
      </div>
    </template>
  </div>
</template>
