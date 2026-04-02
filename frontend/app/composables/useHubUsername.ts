export function normalizeHubUsernameDraft(value: string | null | undefined): string {
  return String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/-{2,}/g, '-')
    .slice(0, 30);
}

export function hubPublicIdentifier(hub?: { id?: string | null; username?: string | null } | null): string {
  return hub?.username || hub?.id || '';
}

export function hubPublicPath(
  hub?: { id?: string | null; username?: string | null } | null,
  suffix = ''
): string {
  const identifier = hubPublicIdentifier(hub);
  return identifier ? `/hubs/${identifier}${suffix}` : '/hubs';
}

export function canChangeHubUsername(hub?: { username_changed_at?: string | null } | null): boolean {
  if (!hub?.username_changed_at) return true;
  return new Date(hub.username_changed_at) <= new Date(Date.now() - 30 * 24 * 60 * 60 * 1000);
}

export function nextHubUsernameChangeDate(
  hub?: { username_changed_at?: string | null } | null
): Date | null {
  if (!hub?.username_changed_at) return null;
  const d = new Date(hub.username_changed_at);
  d.setMonth(d.getMonth() + 1);
  return d;
}
