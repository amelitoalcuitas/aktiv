import { useAuthStore } from '~/stores/auth';
import type { User, PublicUser, SocialLinks, ProfilePrivacy } from '~/types/user';

export function useProfile() {
  const { apiFetch } = useApi();
  const authStore = useAuthStore();

  async function fetchOwnProfile(): Promise<User> {
    const res = await apiFetch<{ data: User }>('/profile');
    authStore.setUser(res.data);
    return res.data;
  }

  async function updateProfile(payload: {
    name?: string;
    phone?: string | null;
    bio?: string | null;
    social_links?: SocialLinks;
    profile_privacy?: Partial<ProfilePrivacy>;
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

  async function toggleHeart(userId: string): Promise<{ hearted: boolean; hearts_count: number }> {
    const res = await apiFetch<{ data: { hearted: boolean; hearts_count: number } }>(
      `/users/${userId}/heart`,
      { method: 'POST' }
    );
    return res.data;
  }

  return {
    fetchOwnProfile,
    updateProfile,
    uploadAvatar,
    uploadBanner,
    fetchPublicProfile,
    toggleHeart,
  };
}
