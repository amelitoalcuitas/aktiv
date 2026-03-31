import { useAuthStore } from '~/stores/auth';
import type { User, PublicUser, SocialLinks, ProfilePrivacy, OwnedHub } from '~/types/user';

export function useProfile() {
  const { apiFetch } = useApi();
  const authStore = useAuthStore();

  async function fetchOwnProfile(): Promise<User> {
    const res = await apiFetch<{ data: User }>('/profile');
    authStore.setUser(res.data);
    return res.data;
  }

  async function updateProfile(payload: {
    first_name?: string;
    last_name?: string;
    username?: string | null;
    contact_number?: string | null;
    country?: string | null;
    province?: string | null;
    city?: string | null;
    bio?: string | null;
    social_links?: SocialLinks;
    profile_privacy?: Partial<ProfilePrivacy>;
    hub_display_order?: string[];
  }): Promise<User> {
    const res = await apiFetch<{ data: User }>('/profile', {
      method: 'PUT',
      body: payload,
    });
    authStore.setUser(res.data);
    return res.data;
  }

  async function uploadAvatar(file: File): Promise<User> {
    const form = new FormData();
    form.append('avatar', file);
    const res = await apiFetch<{ data: User }>('/profile/avatar', {
      method: 'POST',
      body: form,
    });
    authStore.setUser(res.data);
    return res.data;
  }

  async function uploadBanner(file: File): Promise<User> {
    const form = new FormData();
    form.append('banner', file);
    const res = await apiFetch<{ data: User }>('/profile/banner', {
      method: 'POST',
      body: form,
    });
    authStore.setUser(res.data);
    return res.data;
  }

  async function fetchPublicProfile(userId: string): Promise<PublicUser> {
    const res = await apiFetch<{ data: PublicUser }>(`/users/${userId}`);
    return res.data;
  }

  async function resolveUsername(username: string): Promise<string> {
    const res = await apiFetch<{ data: { id: string } }>(`/users/resolve/${username}`);
    return res.data.id;
  }

  async function toggleHeart(userId: string): Promise<{ hearted: boolean; hearts_count: number }> {
    const res = await apiFetch<{ data: { hearted: boolean; hearts_count: number } }>(
      `/users/${userId}/heart`,
      { method: 'POST' }
    );
    return res.data;
  }

  function canChangeName(user: User): boolean {
    if (!user.name_changed_at) return true;
    return new Date(user.name_changed_at) <= new Date(Date.now() - 3 * 30 * 24 * 60 * 60 * 1000);
  }

  function nextNameChangeDate(user: User): Date | null {
    if (!user.name_changed_at) return null;
    const d = new Date(user.name_changed_at);
    d.setMonth(d.getMonth() + 3);
    return d;
  }

  function canChangeUsername(user: User): boolean {
    if (!user.username_changed_at) return true;
    return new Date(user.username_changed_at) <= new Date(Date.now() - 30 * 24 * 60 * 60 * 1000);
  }

  function nextUsernameChangeDate(user: User): Date | null {
    if (!user.username_changed_at) return null;
    const d = new Date(user.username_changed_at);
    d.setMonth(d.getMonth() + 1);
    return d;
  }

  async function updateHubShowOnProfile(hubId: string, showOnProfile: boolean): Promise<void> {
    await apiFetch(`/hubs/${hubId}/show-on-profile`, {
      method: 'PATCH',
      body: { show_on_profile: showOnProfile },
    });
  }

  return {
    fetchOwnProfile,
    updateProfile,
    uploadAvatar,
    uploadBanner,
    fetchPublicProfile,
    resolveUsername,
    toggleHeart,
    canChangeName,
    nextNameChangeDate,
    canChangeUsername,
    nextUsernameChangeDate,
    updateHubShowOnProfile,
  };
}
