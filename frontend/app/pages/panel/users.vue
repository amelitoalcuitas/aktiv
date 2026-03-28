<script setup lang="ts">
definePageMeta({ layout: 'panel', middleware: ['auth', 'superadmin'] });

useHead({ title: 'Users · Admin Panel · Aktiv' });

const { apiFetch } = useApi();

interface PanelUser {
  id: string;
  name: string;
  username: string;
  email: string;
  role: string;
  email_verified: boolean;
  is_disabled: boolean;
  hubs_count: number;
  created_at: string;
}

interface PaginatedUsers {
  data: PanelUser[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

const search = ref('');
const page = ref(1);
const loading = ref(false);
const result = ref<PaginatedUsers | null>(null);

async function fetchUsers() {
  loading.value = true;
  try {
    const params = new URLSearchParams({ page: String(page.value) });
    if (search.value.trim()) params.set('search', search.value.trim());
    result.value = await apiFetch<PaginatedUsers>(`/panel/users?${params}`);
  } finally {
    loading.value = false;
  }
}

onMounted(fetchUsers);

let searchTimer: ReturnType<typeof setTimeout>;
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    page.value = 1;
    fetchUsers();
  }, 350);
});

watch(page, fetchUsers);

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

const roleConfig: Record<string, { label: string; color: string }> = {
  super_admin: { label: 'Super Admin', color: 'bg-purple-100 text-purple-700' },
  admin: { label: 'Admin', color: 'bg-[#e8f0f8] text-[#004e89]' },
  user: { label: 'User', color: 'bg-[#f0f4f8] text-[#64748b]' }
};

// --- Role modal ---
const roleModal = ref({
  open: false,
  user: null as PanelUser | null,
  role: 'user' as 'user' | 'admin',
  loading: false
});

function openRoleModal(user: PanelUser) {
  roleModal.value = {
    open: true,
    user,
    role: user.role === 'admin' ? 'admin' : 'user',
    loading: false
  };
}

async function submitRoleChange() {
  if (!roleModal.value.user) return;
  roleModal.value.loading = true;
  try {
    const updated = await apiFetch<PanelUser>(
      `/panel/users/${roleModal.value.user.id}/role`,
      {
        method: 'PATCH',
        body: { role: roleModal.value.role }
      }
    );
    patchUser(updated);
    roleModal.value.open = false;
  } finally {
    roleModal.value.loading = false;
  }
}

// --- Delete modal ---
const deleteModal = ref({
  open: false,
  user: null as PanelUser | null,
  loading: false
});

function openDeleteModal(user: PanelUser) {
  deleteModal.value = { open: true, user, loading: false };
}

async function submitDelete() {
  if (!deleteModal.value.user) return;
  deleteModal.value.loading = true;
  try {
    await apiFetch(`/panel/users/${deleteModal.value.user.id}`, {
      method: 'DELETE'
    });
    if (result.value) {
      result.value.data = result.value.data.filter(
        (u) => u.id !== deleteModal.value.user!.id
      );
      result.value.total -= 1;
    }
    deleteModal.value.open = false;
  } finally {
    deleteModal.value.loading = false;
  }
}

// --- Verify email ---
const verifyingId = ref<string | null>(null);

async function verifyEmail(user: PanelUser) {
  verifyingId.value = user.id;
  try {
    const updated = await apiFetch<PanelUser>(
      `/panel/users/${user.id}/verify-email`,
      { method: 'PATCH' }
    );
    patchUser(updated);
  } finally {
    verifyingId.value = null;
  }
}

function patchUser(updated: PanelUser) {
  if (!result.value) return;
  const idx = result.value.data.findIndex((u) => u.id === updated.id);
  if (idx !== -1) result.value.data[idx] = updated;
}

const roleOptions = [
  { label: 'User', value: 'user' },
  { label: 'Admin', value: 'admin' }
];
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#0f1728]">Users</h1>
        <p class="mt-1 text-sm text-[#64748b]">
          <template v-if="result">{{ result.total }} total users</template>
          <template v-else>Manage platform users.</template>
        </p>
      </div>
      <UInput
        v-model="search"
        icon="i-heroicons-magnifying-glass"
        placeholder="Search name, username, email…"
        class="w-72"
      />
    </div>

    <!-- Loading -->
    <div
      v-if="loading && !result"
      class="flex items-center gap-2 text-[#64748b]"
    >
      <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
      <span class="text-sm">Loading users…</span>
    </div>

    <template v-else-if="result">
      <!-- Empty state -->
      <div
        v-if="!result.data.length"
        class="rounded-2xl border border-dashed border-[#dbe4ef] bg-white p-12 text-center"
      >
        <UIcon
          name="i-heroicons-users"
          class="mx-auto h-12 w-12 text-[#c8d5e0]"
        />
        <h3 class="mt-4 text-base font-semibold text-[#0f1728]">
          No users found
        </h3>
        <p class="mt-1 text-sm text-[#64748b]">Try adjusting your search.</p>
      </div>

      <!-- Table -->
      <div
        v-else
        class="overflow-x-auto rounded-2xl border border-[#dbe4ef] bg-white"
      >
        <table class="w-full text-sm">
          <thead class="border-b border-[#dbe4ef] bg-[#f8fafc] text-[#64748b]">
            <tr>
              <th class="px-4 py-3 text-left font-medium">User</th>
              <th class="px-4 py-3 text-left font-medium">Email</th>
              <th class="px-4 py-3 text-left font-medium">Role</th>
              <th class="px-4 py-3 text-left font-medium">Status</th>
              <th class="px-4 py-3 text-center font-medium">Hubs</th>
              <th class="px-4 py-3 text-left font-medium">Joined</th>
              <th
                class="sticky right-0 bg-[#f8fafc] px-4 py-3 text-right font-medium"
              >
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#f0f4f8]">
            <tr
              v-for="user in result.data"
              :key="user.id"
              class="group hover:bg-[#fafcff]"
            >
              <!-- Name + username -->
              <td class="px-4 py-3">
                <p class="font-medium text-[#0f1728]">{{ user.name }}</p>
                <p class="text-xs text-[#94a3b8]">@{{ user.username }}</p>
              </td>
              <!-- Email -->
              <td class="px-4 py-3 text-[#64748b]">{{ user.email }}</td>
              <!-- Role -->
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="
                    roleConfig[user.role]?.color ??
                    'bg-[#f0f4f8] text-[#64748b]'
                  "
                >
                  {{ roleConfig[user.role]?.label ?? user.role }}
                </span>
              </td>
              <!-- Status badges -->
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                      user.email_verified
                        ? 'bg-[#daf7d0] text-[#1e6a0f]'
                        : 'bg-[#fef9c3] text-[#854d0e]'
                    "
                  >
                    {{ user.email_verified ? 'Verified' : 'Unverified' }}
                  </span>
                  <span
                    v-if="user.is_disabled"
                    class="rounded-full bg-[#fee2e2] px-2 py-0.5 text-xs font-medium text-[#9f1239]"
                  >
                    Disabled
                  </span>
                </div>
              </td>
              <!-- Hubs count -->
              <td class="px-4 py-3 text-center text-[#64748b]">
                {{ user.hubs_count }}
              </td>
              <!-- Joined -->
              <td class="px-4 py-3 text-[#64748b]">
                {{ formatDate(user.created_at) }}
              </td>
              <!-- Actions -->
              <td
                class="sticky right-0 bg-white px-4 py-3 text-right group-hover:bg-[#fafcff]"
              >
                <div class="flex items-center justify-end gap-1">
                  <UTooltip v-if="!user.email_verified" text="Verify email">
                    <UButton
                      color="success"
                      variant="ghost"
                      size="xs"
                      icon="i-heroicons-check-badge"
                      :loading="verifyingId === user.id"
                      @click="verifyEmail(user)"
                    />
                  </UTooltip>
                  <UTooltip text="Change role">
                    <UButton
                      color="neutral"
                      variant="ghost"
                      size="xs"
                      icon="i-heroicons-shield-check"
                      @click="openRoleModal(user)"
                    />
                  </UTooltip>
                  <UTooltip text="Delete user">
                    <UButton
                      color="error"
                      variant="ghost"
                      size="xs"
                      icon="i-heroicons-trash"
                      @click="openDeleteModal(user)"
                    />
                  </UTooltip>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="result.last_page > 1"
        class="mt-4 flex items-center justify-between text-sm text-[#64748b]"
      >
        <p>Page {{ result.current_page }} of {{ result.last_page }}</p>
        <div class="flex gap-2">
          <UButton
            color="neutral"
            variant="outline"
            size="sm"
            :disabled="page === 1"
            icon="i-heroicons-chevron-left"
            @click="page--"
          >
            Previous
          </UButton>
          <UButton
            color="neutral"
            variant="outline"
            size="sm"
            :disabled="page === result.last_page"
            trailing-icon="i-heroicons-chevron-right"
            @click="page++"
          >
            Next
          </UButton>
        </div>
      </div>
    </template>

    <!-- Change Role Modal -->
    <AppModal
      v-model:open="roleModal.open"
      title="Change Role"
      confirm="Save"
      :confirm-loading="roleModal.loading"
      @confirm="submitRoleChange"
    >
      <template #body>
        <div class="space-y-3 py-1">
          <p class="text-[#64748b] align-middle">
            Change role for
            <span class="font-medium text-[#0f1728]">{{
              roleModal.user?.name
            }}</span>
          </p>
          <USelect
            v-model="roleModal.role"
            :items="roleOptions"
            value-key="value"
            label-key="label"
          />
        </div>
      </template>
    </AppModal>

    <!-- Delete Confirm Modal -->
    <AppModal
      v-model:open="deleteModal.open"
      title="Delete User"
      :description="`This will soft-delete ${deleteModal.user?.name ?? 'this user'}. They won't be able to log in.`"
      confirm="Delete"
      confirm-color="error"
      :confirm-loading="deleteModal.loading"
      @confirm="submitDelete"
    />
  </div>
</template>
