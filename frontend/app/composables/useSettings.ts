import type { EmailActionCooldown } from '~/composables/useCooldownTimer';

interface EmailActionCooldownResponse {
  cooldown: EmailActionCooldown;
}

export function useSettings() {
  const { apiFetch } = useApi();

  async function requestDeletion(payload: { current_password?: string; deletion_token?: string }) {
    await apiFetch('/profile/request-deletion', {
      method: 'POST',
      body: payload,
    });
  }

  async function cancelDeletion() {
    return apiFetch('/profile/cancel-deletion', { method: 'POST' });
  }

  async function requestPasswordChange(): Promise<EmailActionCooldownResponse> {
    return apiFetch<EmailActionCooldownResponse>('/profile/change-password', { method: 'POST' });
  }

  async function requestPasswordChangeStatus(): Promise<EmailActionCooldownResponse> {
    return apiFetch<EmailActionCooldownResponse>('/profile/change-password/status', {
      method: 'GET',
    });
  }

  return { requestDeletion, cancelDeletion, requestPasswordChange, requestPasswordChangeStatus };
}
