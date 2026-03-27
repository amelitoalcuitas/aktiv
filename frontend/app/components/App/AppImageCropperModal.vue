<script setup lang="ts">
import { Cropper, CircleStencil, RectangleStencil } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';

const props = withDefaults(defineProps<{
  open: boolean;
  src: string;
  aspectRatio?: number;
  stencilShape?: 'circle' | 'rectangle';
}>(), {
  stencilShape: 'rectangle',
});

const emit = defineEmits<{
  'update:open': [value: boolean];
  'confirm': [blob: Blob];
}>();

const cropperRef = ref<InstanceType<typeof Cropper> | null>(null);
const saving = ref(false);

const stencilComponent = computed(() =>
  props.stencilShape === 'circle' ? CircleStencil : RectangleStencil
);

const stencilProps = computed(() =>
  props.aspectRatio !== undefined ? { aspectRatio: props.aspectRatio } : {}
);

function onCancel() {
  emit('update:open', false);
}

function onConfirm() {
  if (!cropperRef.value) return;
  const { canvas } = cropperRef.value.getResult();
  if (!canvas) return;
  saving.value = true;
  canvas.toBlob(
    (blob) => {
      if (blob) emit('confirm', blob);
      saving.value = false;
    },
    'image/jpeg',
    0.9,
  );
}
</script>

<template>
  <UModal
    :open="open"
    :dismissible="false"
    :ui="{ content: 'max-w-xl' }"
    @update:open="emit('update:open', $event)"
  >
    <template #content>
      <div class="flex flex-col gap-4 p-4">
        <h3 class="text-base font-bold text-[var(--aktiv-ink)]">Crop Image</h3>

        <div class="overflow-hidden rounded-lg bg-black" style="max-height: 60vh;">
          <Cropper
            ref="cropperRef"
            :src="src"
            :stencil-component="stencilComponent"
            :stencil-props="stencilProps"
            class="max-h-[60vh]"
          />
        </div>

        <div class="flex justify-end gap-2">
          <UButton variant="outline" color="neutral" :disabled="saving" @click="onCancel">
            Cancel
          </UButton>
          <UButton :loading="saving" @click="onConfirm">
            Crop & Save
          </UButton>
        </div>
      </div>
    </template>
  </UModal>
</template>
