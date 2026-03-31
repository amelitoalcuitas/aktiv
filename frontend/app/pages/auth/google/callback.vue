<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';

definePageMeta({ layout: 'auth', middleware: 'guest' });

interface GoogleAuthPopupMessage {
  type: 'aktiv:google-auth-result';
  status: 'success' | 'error' | 'needs_profile';
  token?: string;
  pendingToken?: string;
  redirect: string;
  reason?: string;
}

const route = useRoute();
const toast = useToast();
const { finalizeGoogleLogin } = useAuth();

function sanitizeRedirect(value: unknown): string {
  if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//')) {
    return '/dashboard';
  }

  return value;
}

onMounted(async () => {
  const status = route.query.status;
  const redirect = sanitizeRedirect(route.query.redirect);
  const token = typeof route.query.token === 'string' ? route.query.token : null;
  const pendingToken = typeof route.query.pending_token === 'string' ? route.query.pending_token : null;

  if (import.meta.client && window.opener && !window.opener.closed) {
    const message: GoogleAuthPopupMessage =
      status === 'success' && token
        ? {
            type: 'aktiv:google-auth-result',
            status: 'success',
            token,
            redirect
          }
        : status === 'needs_profile' && pendingToken
          ? {
              type: 'aktiv:google-auth-result',
              status: 'needs_profile',
              pendingToken,
              redirect
            }
          : {
              type: 'aktiv:google-auth-result',
              status: 'error',
              redirect,
              reason: route.query.reason === 'oauth_failed' ? 'oauth_failed' : 'invalid_response'
            };

    window.opener.postMessage(message, window.location.origin);
    window.close();
    return;
  }

  if (status === 'needs_profile') {
    if (!pendingToken) {
      toast.add({ title: 'Invalid Google sign-in response.', color: 'error' });
      await navigateTo(`/auth/login?redirect=${encodeURIComponent(redirect)}`, { replace: true });
      return;
    }

    await navigateTo(
      `/auth/google/complete?pending_token=${encodeURIComponent(pendingToken)}&redirect=${encodeURIComponent(redirect)}`,
      { replace: true }
    );
    return;
  }

  if (status !== 'success') {
    const reason = route.query.reason === 'oauth_failed'
      ? 'Google sign-in failed. Please try again.'
      : 'Google sign-in could not be completed.';

    toast.add({ title: reason, color: 'error' });
    await navigateTo(`/auth/login?redirect=${encodeURIComponent(redirect)}`, { replace: true });
    return;
  }

  if (!token) {
    toast.add({ title: 'Invalid Google sign-in response.', color: 'error' });
    await navigateTo(`/auth/login?redirect=${encodeURIComponent(redirect)}`, { replace: true });
    return;
  }

  try {
    const user = await finalizeGoogleLogin(token);
    const destination = redirect === '/dashboard' && user.role === 'super_admin'
      ? '/panel'
      : redirect;

    toast.add({ title: 'Signed in with Google.', color: 'success' });
    await navigateTo(destination, { replace: true });
  } catch {
    toast.add({ title: 'Failed to finish Google sign-in.', color: 'error' });
    await navigateTo(`/auth/login?redirect=${encodeURIComponent(redirect)}`, { replace: true });
  }
});
</script>

<template>
  <UCard :ui="{ root: 'ring-1 ring-[#dbe4ef]' }">
    <template #header>
      <h1 class="text-xl font-bold text-[#0f1728]">Signing you in</h1>
      <p class="mt-1 text-sm text-[#64748b]">
        We&apos;re finishing your Google sign-in.
      </p>
    </template>

    <div class="flex items-center justify-center py-6">
      <UIcon name="i-lucide-loader-circle" class="size-6 animate-spin text-[#004e89]" />
    </div>
  </UCard>
</template>
