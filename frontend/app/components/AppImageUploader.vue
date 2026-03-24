<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    modelValue: File | null;
    previewUrl?: string | null;
    accept?: string;
    maxMb?: number;
    label?: string;
    hint?: string;
  }>(),
  {
    previewUrl: null,
    accept: 'image/jpeg,image/png,image/webp',
    maxMb: 10,
    label: undefined,
    hint: undefined
  }
);

const emit = defineEmits<{
  'update:modelValue': [File | null];
  clear: [];
}>();

const fileInput = useTemplateRef<HTMLInputElement>('fileInput');
const localPreview = ref<string | null>(null);
const isDragging = ref(false);
const fileError = ref('');

const displayPreview = computed(
  () => localPreview.value ?? props.previewUrl ?? null
);

const allowedTypes = computed(() => props.accept.split(',').map((t) => t.trim()));

function validate(file: File): string {
  if (!allowedTypes.value.includes(file.type)) {
    return 'Please upload a JPG, PNG, or WebP image.';
  }
  if (file.size > props.maxMb * 1024 * 1024) {
    return `Image must be under ${props.maxMb} MB.`;
  }
  return '';
}

function pick(file: File) {
  const error = validate(file);
  if (error) {
    fileError.value = error;
    return;
  }
  fileError.value = '';
  if (localPreview.value) URL.revokeObjectURL(localPreview.value);
  localPreview.value = URL.createObjectURL(file);
  emit('update:modelValue', file);
}

function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (file) pick(file);
}

function onDrop(e: DragEvent) {
  isDragging.value = false;
  const file = e.dataTransfer?.files?.[0];
  if (file) pick(file);
}

function clear() {
  if (localPreview.value) URL.revokeObjectURL(localPreview.value);
  localPreview.value = null;
  fileError.value = '';
  if (fileInput.value) fileInput.value.value = '';
  emit('update:modelValue', null);
  emit('clear');
}

onUnmounted(() => {
  if (localPreview.value) URL.revokeObjectURL(localPreview.value);
});

defineExpose({ clear });
</script>

<template>
  <div class="space-y-2">
    <div v-if="label" class="text-sm font-medium text-[#0f1728]">{{ label }}</div>
    <div v-if="hint" class="text-xs text-[#64748b]">{{ hint }}</div>

    <!-- Preview -->
    <div v-if="displayPreview" class="relative inline-block">
      <img
        :src="displayPreview"
        alt="Preview"
        class="max-h-40 rounded-lg border border-[var(--aktiv-border,#dbe4ef)] object-contain"
      />
      <button
        type="button"
        class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full border border-[var(--aktiv-border,#dbe4ef)] bg-white transition-colors hover:bg-red-50"
        @click="clear"
      >
        <UIcon name="i-heroicons-x-mark" class="h-3.5 w-3.5 text-[#0f1728]" />
      </button>
    </div>

    <!-- Drop zone -->
    <div
      class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-6 text-center transition-colors"
      :class="
        isDragging
          ? 'border-[#004e89] bg-[#e8f0f8]'
          : 'border-[var(--aktiv-border,#dbe4ef)] bg-[var(--aktiv-bg,#f9fdf2)] hover:border-[#004e89] hover:bg-[#e8f0f8]'
      "
      @click="fileInput?.click()"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
    >
      <UIcon
        name="i-heroicons-photo"
        class="mx-auto h-7 w-7 text-[var(--aktiv-muted,#64748b)]"
      />
      <p class="mt-2 text-sm font-medium text-[var(--aktiv-ink,#0f1728)]">
        {{ modelValue ? modelValue.name : 'Click or drag to upload' }}
      </p>
      <p class="mt-0.5 text-xs text-[var(--aktiv-muted,#64748b)]">
        JPG, PNG, WebP · max {{ maxMb }} MB
      </p>
    </div>

    <input
      ref="fileInput"
      type="file"
      :accept="accept"
      class="sr-only"
      @change="onFileChange"
    />

    <p v-if="fileError" class="text-xs text-red-600">{{ fileError }}</p>
  </div>
</template>
