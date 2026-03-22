<script setup lang="ts">
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
    confirmLoading?: boolean;
    confirmDisabled?: boolean;
  }>(),
  {
    dismissible: true,
    cancel: 'Cancel'
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
</script>

<template>
  <UModal v-model:open="open" :title :description :fullscreen :dismissible :ui>
    <template v-for="(_, name) in nonFooterSlots" #[name]="slotData">
      <slot :name="name" v-bind="slotData ?? {}" />
    </template>

    <template v-if="slots.footer || confirm" #footer>
      <slot name="footer">
        <div class="flex justify-end gap-2">
          <UButton color="neutral" variant="ghost" @click="onCancel">
            {{ cancel }}
          </UButton>
          <UButton
            color="primary"
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
</template>
