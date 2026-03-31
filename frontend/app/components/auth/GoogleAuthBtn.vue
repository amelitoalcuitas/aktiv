<script setup lang="ts">
withDefaults(
  defineProps<{
    loading?: boolean;
    disabled?: boolean;
    type?: 'button' | 'submit';
  }>(),
  {
    loading: false,
    disabled: false,
    type: 'button'
  }
);

defineEmits<{
  click: [event: MouseEvent];
}>();
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="google-auth-btn"
    @click="$emit('click', $event)"
  >
    <svg
      v-if="!loading"
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 48 48"
      class="h-5 w-5 flex-shrink-0"
      aria-hidden="true"
    >
      <path
        fill="#EA4335"
        d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"
      />
      <path
        fill="#4285F4"
        d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"
      />
      <path
        fill="#FBBC05"
        d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"
      />
      <path
        fill="#34A853"
        d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"
      />
      <path fill="none" d="M0 0h48v48H0z" />
    </svg>

    <span
      v-else
      class="google-auth-btn__spinner"
      aria-hidden="true"
    />

    <span>Continue with Google</span>
  </button>
</template>

<style scoped>
.google-auth-btn {
  display: inline-flex;
  width: 100%;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  border: 1px solid #dadce0;
  border-radius: 0.5rem;
  background: #fff;
  color: #3c4043;
  padding: 0.75rem 1rem;
  font-size: 0.95rem;
  font-weight: 500;
  line-height: 1.25rem;
  transition:
    background-color 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease,
    color 0.15s ease;
  cursor: pointer;
  outline: none;
  appearance: none;
}

.google-auth-btn:hover:not(:disabled) {
  background: #f8f9fa;
}

.google-auth-btn:focus,
.google-auth-btn:focus-visible {
  outline: none;
  box-shadow: none;
}

.google-auth-btn:active:not(:disabled) {
  background: #f1f3f4;
}

.google-auth-btn:disabled {
  cursor: not-allowed;
  opacity: 0.72;
}

.google-auth-btn__spinner {
  width: 1rem;
  height: 1rem;
  border-radius: 9999px;
  border: 2px solid #d0d7de;
  border-top-color: #0f76bf;
  animation: google-auth-spin 0.8s linear infinite;
}

@keyframes google-auth-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
