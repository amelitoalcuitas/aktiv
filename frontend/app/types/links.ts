export const LINK_PLATFORMS = [
  'facebook',
  'instagram',
  'x',
  'youtube',
  'threads',
  'other'
] as const;

export type LinkPlatform = (typeof LINK_PLATFORMS)[number];

export interface LinkRow {
  platform: LinkPlatform;
  url: string;
}

export interface LinkOption {
  value: LinkPlatform;
  label: string;
  icon: string;
}

export const LINK_PLATFORM_OPTIONS: LinkOption[] = [
  { value: 'facebook', label: 'Facebook', icon: 'i-simple-icons-facebook' },
  { value: 'instagram', label: 'Instagram', icon: 'i-simple-icons-instagram' },
  { value: 'x', label: 'X (Twitter)', icon: 'i-simple-icons-x' },
  { value: 'youtube', label: 'YouTube', icon: 'i-simple-icons-youtube' },
  { value: 'threads', label: 'Threads', icon: 'i-simple-icons-threads' },
  { value: 'other', label: 'Other', icon: 'i-heroicons-globe-alt' }
];

export function createEmptyLinkRow(): LinkRow {
  return {
    platform: LINK_PLATFORM_OPTIONS[0]!.value,
    url: ''
  };
}

export function iconForLinkPlatform(platform: LinkPlatform): string {
  return (
    LINK_PLATFORM_OPTIONS.find((option) => option.value === platform)?.icon ??
    'i-heroicons-globe-alt'
  );
}

export function labelForLinkPlatform(platform: LinkPlatform): string {
  return (
    LINK_PLATFORM_OPTIONS.find((option) => option.value === platform)?.label ??
    'Link'
  );
}

export function isValidExternalUrl(value: string): boolean {
  if (!value.trim()) return true;
  try {
    const url = new URL(value.trim());
    return url.protocol === 'http:' || url.protocol === 'https:';
  } catch {
    return false;
  }
}
