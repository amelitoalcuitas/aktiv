<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';

const open = defineModel<boolean>('open', { default: false });

const props = withDefaults(
  defineProps<{
    title?: string;
    description?: string;
    fullscreen?: boolean;
    dismissible?: boolean;
    ui?: Record<string, any>;
    confirm?: string;
    cancel?: string;
    confirmColor?:
      | 'primary'
      | 'neutral'
      | 'secondary'
      | 'success'
      | 'info'
      | 'warning'
      | 'error';
    confirmLoading?: boolean;
    confirmDisabled?: boolean;
    cancelVariant?: 'ghost' | 'link' | 'solid' | 'outline' | 'soft' | 'subtle';
  }>(),
  {
    dismissible: true,
    cancel: 'Cancel',
    confirmColor: 'primary',
    cancelVariant: 'ghost'
  }
);

const emit = defineEmits<{
  confirm: [];
  cancel: [];
}>();

const slots = useSlots();

const nonFooterSlots = computed(() =>
  Object.fromEntries(
    Object.entries(slots).filter(([name]) => name !== 'footer')
  )
);

function onCancel() {
  emit('cancel');
  open.value = false;
}

const isDesktop = useMediaQuery('(min-width: 768px)');

const drawerUi = computed(() => {
  const { content: _content, ...rest } = props.ui ?? {};
  return { content: 'max-h-[85dvh]', body: 'overflow-y-auto', ...rest };
});
</script>

<template>
  <UModal
    v-if="isDesktop"
    v-model:open="open"
    :title
    :description
    :fullscreen
    :dismissible
    :ui
  >
    <template v-for="(_, name) in nonFooterSlots" #[name]="slotData">
      <slot :name="name" v-bind="slotData ?? {}" />
    </template>

    <template v-if="slots.footer || confirm" #footer>
      <slot name="footer">
        <div class="flex justify-end gap-2 w-full">
          <UButton color="neutral" :variant="cancelVariant" @click="onCancel">
            {{ cancel }}
          </UButton>
          <UButton
            :color="confirmColor"
            :loading="confirmLoading"
            :disabled="confirmDisabled"
            @click="emit('confirm')"
          >
            {{ confirm }}
          </UButton>
        </div>
      </slot>
    </template>
  </UModal>

  <UDrawer
    v-else
    v-model:open="open"
    :title
    :description
    :dismissible
    :ui="drawerUi"
  >
    <template v-for="(_, name) in nonFooterSlots" #[name]="slotData">
      <slot :name="name" v-bind="slotData ?? {}" />
    </template>

    <template v-if="slots.footer || confirm" #footer>
      <slot name="footer">
        <div class="flex justify-end gap-2 w-full">
          <UButton color="neutral" :variant="cancelVariant" @click="onCancel">
            {{ cancel }}
          </UButton>
          <UButton
            :color="confirmColor"
            :loading="confirmLoading"
            :disabled="confirmDisabled"
            @click="emit('confirm')"
          >
            {{ confirm }}
          </UButton>
        </div>
      </slot>
    </template>
  </UDrawer>
</template>
