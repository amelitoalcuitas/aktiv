<script setup lang="ts">
import type { Hub } from '~/types/hub';

const props = defineProps<{
  modelValue: 'pay_on_site' | 'digital_bank' | null;
  hub: Hub | null | undefined;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: 'pay_on_site' | 'digital_bank'];
}>();

const hubPaymentMethods = computed(() => props.hub?.payment_methods ?? ['pay_on_site']);
const multipleOptions = computed(() => hubPaymentMethods.value.length > 1);

// Auto-select pay_on_site as default when multiple options and nothing chosen
onMounted(() => {
  if (!props.modelValue && hubPaymentMethods.value.includes('pay_on_site')) {
    emit('update:modelValue', 'pay_on_site');
  } else if (!props.modelValue && hubPaymentMethods.value.length > 0) {
    emit('update:modelValue', hubPaymentMethods.value[0]!);
  }
});

watch(hubPaymentMethods, (methods) => {
  if (methods.includes('pay_on_site')) {
    emit('update:modelValue', 'pay_on_site');
  } else if (methods.length > 0) {
    emit('update:modelValue', methods[0]!);
  }
}, { immediate: false });

async function downloadQr() {
  const url = props.hub?.payment_qr_url;
  if (!url) return;
  const res = await fetch(url);
  const blob = await res.blob();
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `${props.hub?.name ?? 'payment'}-qr.png`;
  a.click();
  URL.revokeObjectURL(a.href);
}
</script>

<template>
  <!-- Multiple options: card grid -->
  <div v-if="multipleOptions" class="space-y-2">
    <p class="text-sm font-medium text-[#0f1728]">
      How will you pay? <span class="text-red-500">*</span>
    </p>
    <div class="grid grid-cols-2 gap-2">
      <button
        v-if="hubPaymentMethods.includes('pay_on_site')"
        type="button"
        class="flex flex-col items-center gap-1.5 rounded-xl border-2 p-3 text-center transition-colors"
        :class="modelValue === 'pay_on_site'
          ? 'border-[#004e89] bg-[#f0f7ff]'
          : 'border-[#dbe4ef] bg-white hover:border-[#004e89]/40'"
        @click="emit('update:modelValue', 'pay_on_site')"
      >
        <UIcon name="i-heroicons-qr-code" class="h-5 w-5 text-[#004e89]" />
        <span class="text-sm font-medium text-[#0f1728]">Pay on Site</span>
        <span class="text-xs text-[#64748b]">Show QR at venue</span>
      </button>
      <button
        v-if="hubPaymentMethods.includes('digital_bank')"
        type="button"
        class="flex flex-col items-center gap-1.5 rounded-xl border-2 p-3 text-center transition-colors"
        :class="modelValue === 'digital_bank'
          ? 'border-[#004e89] bg-[#f0f7ff]'
          : 'border-[#dbe4ef] bg-white hover:border-[#004e89]/40'"
        @click="emit('update:modelValue', 'digital_bank')"
      >
        <UIcon name="i-heroicons-device-phone-mobile" class="h-5 w-5 text-[#004e89]" />
        <span class="text-sm font-medium text-[#0f1728]">Digital Bank</span>
        <span class="text-xs text-[#64748b]">GCash, Maya, etc.</span>
      </button>
    </div>

    <!-- Digital bank info when selected -->
    <div
      v-if="modelValue === 'digital_bank' && (hub?.payment_qr_url || hub?.digital_bank_name || hub?.digital_bank_account)"
      class="rounded-lg border border-[#dbe4ef] bg-[#f9fdf2] p-3 space-y-2"
    >
      <!-- Account info -->
      <div v-if="hub?.digital_bank_name || hub?.digital_bank_account" class="text-sm space-y-0.5">
        <div v-if="hub?.digital_bank_name" class="flex justify-between">
          <span class="text-[#64748b]">Digital Bank</span>
          <span class="font-medium text-[#0f1728]">{{ hub.digital_bank_name }}</span>
        </div>
        <div v-if="hub?.digital_bank_account" class="flex justify-between">
          <span class="text-[#64748b]">Account No.</span>
          <span class="font-mono font-medium text-[#0f1728]">{{ hub.digital_bank_account }}</span>
        </div>
      </div>
      <!-- QR image -->
      <div v-if="hub?.payment_qr_url" class="flex items-start gap-3">
        <img
          :src="hub.payment_qr_url"
          alt="Payment QR"
          class="h-28 w-28 shrink-0 rounded-lg border border-[#dbe4ef] object-contain bg-white"
        />
        <div class="space-y-1.5">
          <p class="text-xs text-[#64748b]">Scan to send payment</p>
          <button
            type="button"
            class="flex items-center gap-1 text-xs font-medium text-[#004e89] hover:underline"
            @click="downloadQr"
          >
            <UIcon name="i-heroicons-arrow-down-tray" class="h-3.5 w-3.5" />
            Download QR
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Single option: inline notice -->
  <div
    v-else-if="modelValue"
    class="rounded-lg border border-[#dbe4ef] bg-[#f9fdf2] px-3 py-2.5"
  >
    <div class="flex items-center gap-2 text-sm">
      <UIcon
        :name="modelValue === 'pay_on_site' ? 'i-heroicons-qr-code' : 'i-heroicons-device-phone-mobile'"
        class="h-4 w-4 shrink-0 text-[#004e89]"
      />
      <div>
        <span class="font-medium text-[#0f1728]">
          {{ modelValue === 'pay_on_site' ? 'Pay on Site' : 'Digital Bank (GCash, Maya, etc.)' }}
        </span>
        <span class="ml-1 text-[#64748b]">·</span>
        <span class="ml-1 text-[#64748b]">
          {{ modelValue === 'pay_on_site'
            ? "You'll receive a QR code to show at the venue."
            : 'Upload your receipt after sending payment.' }}
        </span>
      </div>
    </div>

    <!-- Digital bank account info + QR for single-method digital bank -->
    <div
      v-if="modelValue === 'digital_bank' && (hub?.payment_qr_url || hub?.digital_bank_name || hub?.digital_bank_account)"
      class="mt-2 ml-6 space-y-1.5"
    >
      <div v-if="hub?.digital_bank_name || hub?.digital_bank_account" class="text-xs space-y-0.5">
        <div v-if="hub?.digital_bank_name" class="flex gap-2">
          <span class="text-[#64748b]">Bank:</span>
          <span class="font-medium text-[#0f1728]">{{ hub.digital_bank_name }}</span>
        </div>
        <div v-if="hub?.digital_bank_account" class="flex gap-2">
          <span class="text-[#64748b]">Account:</span>
          <span class="font-mono font-medium text-[#0f1728]">{{ hub.digital_bank_account }}</span>
        </div>
      </div>
      <div v-if="hub?.payment_qr_url" class="flex items-start gap-3">
        <img
          :src="hub.payment_qr_url"
          alt="Payment QR"
          class="h-28 w-28 rounded-lg border border-[#dbe4ef] object-contain bg-white"
        />
        <div class="space-y-1.5">
          <p class="text-xs text-[#64748b]">Scan to send payment</p>
          <button
            type="button"
            class="flex items-center gap-1 text-xs font-medium text-[#004e89] hover:underline"
            @click="downloadQr"
          >
            <UIcon name="i-heroicons-arrow-down-tray" class="h-3.5 w-3.5" />
            Download QR
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
