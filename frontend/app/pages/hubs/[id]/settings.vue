<script setup lang="ts">
import type { Hub } from '~/types/hub';
import { useHubs } from '~/composables/useHubs';

definePageMeta({ middleware: 'auth', layout: 'dashboard-hub' });

const route = useRoute();
const { fetchHub, updateHub } = useHubs();
const toast = useToast();

const hubId = computed(() => String(route.params.id));

const manageTabs = computed(() => [
  { label: 'Hub', icon: 'i-heroicons-building-storefront', to: `/hubs/${hubId.value}/edit` },
  { label: 'Courts', icon: 'i-heroicons-squares-2x2', to: `/hubs/${hubId.value}/courts` },
  { label: 'Bookings', icon: 'i-heroicons-calendar-days', to: `/hubs/${hubId.value}/bookings` },
  { label: 'Events', icon: 'i-heroicons-megaphone', to: `/hubs/${hubId.value}/events` },
  { label: 'Reviews', icon: 'i-heroicons-star', to: `/hubs/${hubId.value}/reviews` },
  { label: 'Settings', icon: 'i-heroicons-cog-6-tooth', to: `/hubs/${hubId.value}/settings` }
]);

const hubData = ref<Hub | null>(null);
const loadingHub = ref(true);

// ── Booking settings ──────────────────────────────────────────
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

function applyHubToForm(hub: Hub) {
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

onMounted(async () => {
  loadingHub.value = true;
  try {
    const hub = await fetchHub(hubId.value);
    hubData.value = hub;
    applyHubToForm(hub);
  } catch {
    toast.add({ title: 'Failed to load hub', color: 'error' });
  } finally {
    loadingHub.value = false;
  }
});

function onPaymentQrPicked(file: File | null) {
  if (!file) return;
  paymentQrFile.value = file;
  removePaymentQr.value = false;
}

function clearPaymentQr() {
  paymentQrFile.value = null;
  paymentQrPreview.value = null;
  removePaymentQr.value = true;
}

// ── Save ─────────────────────────────────────────────────────

const isSaving = ref(false);

async function saveSettings() {
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

    await updateHub(hubId.value, {
      require_account_to_book: requireAccount.value,
      guest_booking_limit: requireAccount.value ? undefined : guestBookingLimit.value,
      guest_max_hours: requireAccount.value ? undefined : guestMaxHours.value,
      payment_methods: paymentMethods,
      payment_qr_image: paymentQrFile.value,
      ...(removePaymentQr.value ? { remove_payment_qr: true } : {}),
      digital_bank_name: digitalBankName.value || null,
      digital_bank_account: digitalBankAccount.value || null
    });

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
    <HubTabNav :tabs="manageTabs" />

    <div class="mx-auto w-full max-w-[1400px] px-4 py-8 md:px-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-[#0f1728]">Settings</h1>
        <p class="mt-1 text-sm text-[#64748b]">Configure hub preferences.</p>
      </div>

      <!-- Loading -->
      <div v-if="loadingHub" class="flex items-center gap-2 text-[#64748b]">
        <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
        <span class="text-sm">Loading settings…</span>
      </div>

      <div v-else class="space-y-6">
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
                    <strong>
                      Take note that you have to manually confirm their
                      payment.</strong
                    >
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
                  <div v-if="digitalBank" class="mt-3 space-y-2">
                    <p class="text-xs font-medium text-[#0f1728]">
                      Payment QR Code <span class="text-red-500">*</span>
                    </p>
                    <AppImageUploader
                      :model-value="paymentQrFile"
                      :preview-url="paymentQrPreview"
                      @update:model-value="onPaymentQrPicked"
                      @clear="clearPaymentQr"
                    />
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
        <div class="flex justify-end">
          <UButton
            :loading="isSaving"
            class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
            @click="saveSettings"
          >
            Save Settings
          </UButton>
        </div>
      </div>
    </div>
  </div>
</template>
