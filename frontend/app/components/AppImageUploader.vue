<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    modelValue: File | File[] | null;
    previewUrl?: string | string[] | null;
    accept?: string;
    maxMb?: number;
    maxFiles?: number;
    label?: string;
    hint?: string;
  }>(),
  {
    previewUrl: null,
    accept: 'image/jpeg,image/png,image/webp',
    maxMb: 10,
    maxFiles: 1,
    label: undefined,
    hint: undefined
  }
);

const emit = defineEmits<{
  'update:modelValue': [File | File[] | null];
  clear: [];
  'remove-existing': [index: number];
}>();

const fileInput = useTemplateRef<HTMLInputElement>('fileInput');
const isDragging = ref(false);
const fileError = ref('');
const localPreviews = ref<string[]>([]);

// Merges existing server URLs + newly added local blob URLs into one list for the UI.
// Each item carries its source (isLocal) and its index within that source group.
const previewItems = computed(() => {
  if (props.maxFiles === 1) {
    if (localPreviews.value.length)
      return [{ url: localPreviews.value[0], isLocal: true, index: 0 }];
    if (props.previewUrl) {
      const url = Array.isArray(props.previewUrl)
        ? props.previewUrl[0]
        : props.previewUrl;
      return [{ url, isLocal: false, index: 0 }];
    }
    return [];
  }

  const items: { url: string; isLocal: boolean; index: number }[] = [];
  if (props.previewUrl) {
    const urls = Array.isArray(props.previewUrl)
      ? props.previewUrl
      : [props.previewUrl];
    urls.forEach((url, i) => items.push({ url, isLocal: false, index: i }));
  }
  localPreviews.value.forEach((url, i) =>
    items.push({ url, isLocal: true, index: i })
  );
  return items;
});

const allowedTypes = computed(() => props.accept.split(',').map((t) => t.trim()));

function validate(file: File): string {
  if (!allowedTypes.value.includes(file.type))
    return 'Please upload a JPG, PNG, or WebP image.';
  if (file.size > props.maxMb * 1024 * 1024)
    return `Image must be under ${props.maxMb} MB.`;
  return '';
}

function processFiles(files: FileList | File[]) {
  fileError.value = '';
  const validFiles: File[] = [];
  for (const file of Array.from(files)) {
    const err = validate(file);
    if (err) { fileError.value = err; continue; }
    validFiles.push(file);
  }
  if (!validFiles.length) return;

  if (props.maxFiles === 1) {
    const file = validFiles[0]!;
    const existing = localPreviews.value[0];
    if (existing) URL.revokeObjectURL(existing);
    localPreviews.value = [URL.createObjectURL(file)];
    emit('update:modelValue', file);
    return;
  }

  // Multi mode: append to whatever is already there
  const slotsAvailable = props.maxFiles - previewItems.value.length;
  if (slotsAvailable <= 0) {
    fileError.value = `You can only upload up to ${props.maxFiles} images.`;
    return;
  }
  const filesToAdd = validFiles.slice(0, slotsAvailable);
  if (validFiles.length > slotsAvailable)
    fileError.value = `You can only upload up to ${props.maxFiles} images.`;

  localPreviews.value.push(...filesToAdd.map((f) => URL.createObjectURL(f)));

  const currentFiles = Array.isArray(props.modelValue)
    ? props.modelValue
    : props.modelValue
      ? [props.modelValue]
      : [];
  emit('update:modelValue', [...currentFiles, ...filesToAdd]);
}

function onFileChange(e: Event) {
  const files = (e.target as HTMLInputElement).files;
  if (files?.length) processFiles(files);
  if (fileInput.value) fileInput.value.value = '';
}

function onDrop(e: DragEvent) {
  isDragging.value = false;
  const files = e.dataTransfer?.files;
  if (files?.length) processFiles(files);
}

function removeFile(item: { isLocal: boolean; index: number }) {
  if (props.maxFiles === 1) { clear(); return; }

  if (item.isLocal) {
    URL.revokeObjectURL(localPreviews.value[item.index]!);
    localPreviews.value.splice(item.index, 1);
    const currentFiles = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
    currentFiles.splice(item.index, 1);
    emit('update:modelValue', currentFiles.length ? currentFiles : null);
  } else {
    emit('remove-existing', item.index);
  }
  fileError.value = '';
}

function clear() {
  localPreviews.value.forEach((url) => URL.revokeObjectURL(url));
  localPreviews.value = [];
  fileError.value = '';
  if (fileInput.value) fileInput.value.value = '';
  emit('update:modelValue', null);
  emit('clear');
}

onUnmounted(() => {
  localPreviews.value.forEach((url) => URL.revokeObjectURL(url));
});

defineExpose({ clear });
</script>

<template>
  <div class="space-y-2">
    <div v-if="label" class="text-sm font-medium text-[#0f1728]">{{ label }}</div>
    <div v-if="hint" class="text-xs text-[#64748b]">{{ hint }}</div>

    <!-- Single image mode: preview lives inside the drop zone -->
    <div v-if="maxFiles === 1">
      <div
        class="relative flex cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 transition-colors"
        :class="[
          isDragging
            ? 'border-[#004e89] bg-[#e8f0f8]'
            : 'border-[var(--aktiv-border,#dbe4ef)] bg-[var(--aktiv-background)] hover:border-[#004e89] hover:bg-[#e8f0f8]',
          previewItems.length > 0 ? 'h-48 border-solid p-0' : 'border-dashed px-6 py-6 text-center'
        ]"
        @click="previewItems.length === 0 ? fileInput?.click() : null"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="onDrop"
      >
        <template v-if="previewItems.length > 0">
          <img
            :src="previewItems[0]!.url"
            alt="Preview"
            class="h-full w-full object-cover"
          />
          <button
            type="button"
            class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full border border-[var(--aktiv-border,#dbe4ef)] bg-white shadow-sm transition-colors hover:bg-red-50"
            @click.stop="clear"
          >
            <UIcon name="i-heroicons-x-mark" class="h-4 w-4 text-[#0f1728]" />
          </button>
        </template>

        <template v-else>
          <UIcon
            name="i-heroicons-photo"
            class="mx-auto h-7 w-7 text-[var(--aktiv-muted,#64748b)]"
          />
          <p class="mt-2 text-sm font-medium text-[var(--aktiv-ink,#0f1728)]">
            Click or drag to upload
          </p>
          <p class="mt-0.5 text-xs text-[var(--aktiv-muted,#64748b)]">
            JPG, PNG, WebP · max {{ maxMb }} MB
          </p>
        </template>
      </div>
    </div>

    <!-- Multi image mode: grid of thumbnails + drop zone below -->
    <div v-else class="space-y-4">
      <div
        v-if="previewItems.length > 0"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
      >
        <div
          v-for="item in previewItems"
          :key="item.url"
          class="group relative aspect-square overflow-hidden rounded-lg border border-[var(--aktiv-border,#dbe4ef)] bg-gray-50"
        >
          <img :src="item.url" alt="Preview" class="h-full w-full object-cover" />
          <button
            type="button"
            class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full border border-gray-200 bg-white/90 shadow-sm opacity-0 transition-all group-hover:opacity-100 hover:bg-red-50 focus:opacity-100"
            @click.stop="removeFile(item)"
          >
            <UIcon name="i-heroicons-x-mark" class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>

      <div
        v-if="previewItems.length < maxFiles"
        class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-6 text-center transition-colors"
        :class="
          isDragging
            ? 'border-[#004e89] bg-[#e8f0f8]'
            : 'border-[var(--aktiv-border,#dbe4ef)] bg-[var(--aktiv-background)] hover:border-[#004e89] hover:bg-[#e8f0f8]'
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
          Click or drag to upload
        </p>
        <p class="mt-0.5 text-xs text-[var(--aktiv-muted,#64748b)]">
          Up to {{ maxFiles }} images · max {{ maxMb }} MB each
        </p>
      </div>
    </div>

    <input
      ref="fileInput"
      type="file"
      :accept="accept"
      :multiple="maxFiles > 1"
      class="sr-only"
      @change="onFileChange"
    />

    <p v-if="fileError" class="text-xs font-medium text-red-600">{{ fileError }}</p>
  </div>
</template>
