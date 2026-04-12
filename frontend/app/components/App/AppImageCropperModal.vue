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
    :ui="{ content: 'max-w-4xl' }"
    @update:open="emit('update:open', $event)"
  >
    <template #content>
      <div class="flex max-h-[90vh] flex-col gap-4 p-4 sm:p-5">
        <h3 class="text-base font-bold text-[var(--aktiv-ink)]">Crop Image</h3>

        <div
          class="overflow-hidden rounded-lg bg-black"
          style="height: min(68vh, 760px); min-height: 360px;"
        >
          <Cropper
            ref="cropperRef"
            :src="src"
            :stencil-component="stencilComponent"
            :stencil-props="stencilProps"
            :transitions="false"
            image-restriction="fit-area"
            class="h-full w-full"
          />
        </div>

        <p class="text-sm text-[var(--aktiv-muted)]">
          Tip: Use your mouse wheel on desktop, or pinch with two fingers on mobile, to zoom in and out.
        </p>

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
