<script setup lang="ts">
import { COUNTRY_OPTIONS } from '~/constants/countries';

definePageMeta({ layout: 'panel', middleware: ['auth', 'superadmin'] });

useHead({ title: 'Users · Admin Panel · Aktiv' });

const { apiFetch } = useApi();

interface PanelUser {
  id: string;
  name: string;
  first_name: string;
  last_name: string;
  username: string;
  email: string;
  contact_number: string | null;
  country: string;
  province: string;
  city: string;
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

// --- Edit user modal ---
const editModal = ref({
  open: false,
  user: null as PanelUser | null,
  saving: false,
  verifying: false,
  deleting: false
});

const editSubmitted = ref(false);
const editForm = reactive({
  first_name: '',
  last_name: '',
  email: '',
  country: '',
  province: '',
  city: '',
  contact_number: '',
  role: 'user'
});
const editErrors = reactive({
  first_name: '',
  last_name: '',
  email: '',
  country: '',
  province: '',
  city: '',
  contact_number: '',
  role: ''
});

function openEdit(user: PanelUser) {
  editModal.value = {
    open: true,
    user,
    saving: false,
    verifying: false,
    deleting: false
  };

  Object.assign(editForm, {
    first_name: user.first_name,
    last_name: user.last_name,
    email: user.email,
    country: user.country,
    province: user.province,
    city: user.city,
    contact_number: user.contact_number ?? '',
    role: user.role === 'admin' ? 'admin' : 'user'
  });

  Object.assign(editErrors, {
    first_name: '',
    last_name: '',
    email: '',
    country: '',
    province: '',
    city: '',
    contact_number: '',
    role: ''
  });

  editSubmitted.value = false;
}

function validateEditForm(): boolean {
  Object.assign(editErrors, {
    first_name: '',
    last_name: '',
    email: '',
    country: '',
    province: '',
    city: '',
    contact_number: '',
    role: ''
  });

  let valid = true;

  if (!editForm.first_name.trim()) {
    editErrors.first_name = 'First name is required.';
    valid = false;
  }
  if (!editForm.last_name.trim()) {
    editErrors.last_name = 'Last name is required.';
    valid = false;
  }
  if (!editForm.email.trim()) {
    editErrors.email = 'Email is required.';
    valid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(editForm.email.trim())) {
    editErrors.email = 'Please enter a valid email address.';
    valid = false;
  }
  if (!editForm.country.trim()) {
    editErrors.country = 'Country is required.';
    valid = false;
  }
  if (!editForm.province.trim()) {
    editErrors.province = 'Province is required.';
    valid = false;
  }
  if (!editForm.city.trim()) {
    editErrors.city = 'City is required.';
    valid = false;
  }

  return valid;
}

async function submitEdit() {
  if (!editModal.value.user) return;

  editSubmitted.value = true;
  if (!validateEditForm()) return;

  editConfirmModal.value.open = true;
}

async function confirmEdit() {
  if (!editModal.value.user) return;

  editModal.value.saving = true;
  try {
    const emailChanged = editForm.email.trim() !== editModal.value.user.email;

    const updated = await apiFetch<PanelUser>(
      `/panel/users/${editModal.value.user.id}`,
      {
        method: 'PUT',
        body: {
          first_name: editForm.first_name.trim(),
          last_name: editForm.last_name.trim(),
          email: editForm.email.trim(),
          contact_number: editForm.contact_number.trim() || null,
          country: editForm.country.trim(),
          province: editForm.province.trim(),
          city: editForm.city.trim(),
          role: editForm.role
        }
      }
    );

    patchUser(updated);
    editModal.value.user = updated;
    editModal.value.open = false;
    editConfirmModal.value.open = false;
    toast.add({
      title: emailChanged
        ? 'User updated. Verification email sent to the new address.'
        : 'User updated.',
      color: 'success'
    });
  } catch (err: any) {
    const errors = err?.data?.errors ?? {};
    if (errors.first_name) editErrors.first_name = errors.first_name[0];
    if (errors.last_name) editErrors.last_name = errors.last_name[0];
    if (errors.email) editErrors.email = errors.email[0];
    if (errors.country) editErrors.country = errors.country[0];
    if (errors.province) editErrors.province = errors.province[0];
    if (errors.city) editErrors.city = errors.city[0];
    if (errors.contact_number)
      editErrors.contact_number = errors.contact_number[0];
    if (errors.role) editErrors.role = errors.role[0];

    if (!Object.values(errors).length) {
      toast.add({ title: 'Failed to update user.', color: 'error' });
    }
  } finally {
    editModal.value.saving = false;
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

async function submitVerifyEmail() {
  if (!editModal.value.user) return;

  verifyConfirmModal.value.open = true;
}

async function confirmVerifyEmail() {
  if (!editModal.value.user) return;

  editModal.value.verifying = true;
  try {
    const updated = await apiFetch<PanelUser>(
      `/panel/users/${editModal.value.user.id}/verify-email`,
      { method: 'PATCH' }
    );
    patchUser(updated);
    editModal.value.user = updated;
    verifyConfirmModal.value.open = false;
    toast.add({ title: 'Email verified.', color: 'success' });
  } catch {
    toast.add({ title: 'Failed to verify email.', color: 'error' });
  } finally {
    editModal.value.verifying = false;
  }
}

async function submitDelete() {
  if (!editModal.value.user) return;

  deleteConfirmModal.value.open = true;
}

async function confirmDelete() {
  if (!editModal.value.user) return;

  editModal.value.deleting = true;
  try {
    await apiFetch(`/panel/users/${editModal.value.user.id}`, {
      method: 'DELETE'
    });
    if (result.value) {
      result.value.data = result.value.data.filter(
        (u) => u.id !== editModal.value.user!.id
      );
      result.value.total -= 1;
    }
    editModal.value.open = false;
    deleteConfirmModal.value.open = false;
    toast.add({ title: 'User deleted.', color: 'success' });
  } catch {
    toast.add({ title: 'Failed to delete user.', color: 'error' });
  } finally {
    editModal.value.deleting = false;
  }
}

// --- Create user modal ---
const isCreateOpen = ref(false);
const createLoading = ref(false);
const createSubmitted = ref(false);
const createForm = reactive({
  first_name: '',
  last_name: '',
  email: '',
  country: '',
  province: '',
  city: '',
  contact_number: '',
  role: 'user'
});
const createErrors = reactive({
  first_name: '',
  last_name: '',
  email: '',
  country: '',
  province: '',
  city: '',
  contact_number: '',
  role: ''
});
const toast = useToast();

function openCreate() {
  Object.assign(createForm, {
    first_name: '',
    last_name: '',
    email: '',
    country: '',
    province: '',
    city: '',
    contact_number: '',
    role: 'user'
  });
  Object.assign(createErrors, {
    first_name: '',
    last_name: '',
    email: '',
    country: '',
    province: '',
    city: '',
    contact_number: '',
    role: ''
  });
  createSubmitted.value = false;
  isCreateOpen.value = true;
}

function validateCreateForm(): boolean {
  Object.assign(createErrors, {
    first_name: '',
    last_name: '',
    email: '',
    country: '',
    province: '',
    city: '',
    contact_number: '',
    role: ''
  });
  let valid = true;

  if (!createForm.first_name.trim()) {
    createErrors.first_name = 'First name is required.';
    valid = false;
  }
  if (!createForm.last_name.trim()) {
    createErrors.last_name = 'Last name is required.';
    valid = false;
  }
  if (!createForm.email.trim()) {
    createErrors.email = 'Email is required.';
    valid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(createForm.email.trim())) {
    createErrors.email = 'Please enter a valid email address.';
    valid = false;
  }
  if (!createForm.country.trim()) {
    createErrors.country = 'Country is required.';
    valid = false;
  }
  if (!createForm.province.trim()) {
    createErrors.province = 'Province is required.';
    valid = false;
  }
  if (!createForm.city.trim()) {
    createErrors.city = 'City is required.';
    valid = false;
  }

  return valid;
}

async function submitCreate() {
  createSubmitted.value = true;
  if (!validateCreateForm()) return;

  createConfirmModal.value.open = true;
}

async function confirmCreate() {
  createLoading.value = true;
  try {
    const body: Record<string, string> = {
      first_name: createForm.first_name.trim(),
      last_name: createForm.last_name.trim(),
      email: createForm.email.trim(),
      country: createForm.country.trim(),
      province: createForm.province.trim(),
      city: createForm.city.trim(),
      role: createForm.role
    };
    if (createForm.contact_number.trim()) {
      body.contact_number = createForm.contact_number.trim();
    }

    await apiFetch('/panel/users', { method: 'POST', body });
    isCreateOpen.value = false;
    createConfirmModal.value.open = false;
    toast.add({
      title: 'User created. Password setup email sent.',
      color: 'success'
    });
    page.value = 1;
    await fetchUsers();
  } catch (err: any) {
    const errors = err?.data?.errors ?? {};
    if (errors.first_name) createErrors.first_name = errors.first_name[0];
    if (errors.last_name) createErrors.last_name = errors.last_name[0];
    if (errors.email) createErrors.email = errors.email[0];
    if (errors.country) createErrors.country = errors.country[0];
    if (errors.province) createErrors.province = errors.province[0];
    if (errors.city) createErrors.city = errors.city[0];
    if (errors.contact_number)
      createErrors.contact_number = errors.contact_number[0];
    if (errors.role) createErrors.role = errors.role[0];
    if (!Object.values(errors).length) {
      toast.add({ title: 'Failed to create user.', color: 'error' });
    }
  } finally {
    createLoading.value = false;
  }
}

const createConfirmModal = ref({
  open: false
});

const editConfirmModal = ref({
  open: false
});

const verifyConfirmModal = ref({
  open: false
});

const deleteConfirmModal = ref({
  open: false
});
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
      <div class="flex items-center gap-3">
        <UInput
          v-model="search"
          icon="i-heroicons-magnifying-glass"
          placeholder="Search name, username, email…"
          class="w-72"
        />
        <UButton
          icon="i-heroicons-plus"
          class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
          @click="openCreate"
        >
          Create User
        </UButton>
      </div>
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
                  <UTooltip text="Edit user">
                    <UButton
                      color="neutral"
                      variant="ghost"
                      size="xs"
                      icon="i-heroicons-pencil-square"
                      @click="openEdit(user)"
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

    <AppModal
      v-model:open="isCreateOpen"
      title="Create User"
      :ui="{ content: 'max-w-md' }"
      confirm="Create User"
      :confirm-loading="createLoading"
      @confirm="submitCreate"
    >
      <template #body>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <UFormField
              label="First Name"
              required
              :error="
                createSubmitted && createErrors.first_name
                  ? createErrors.first_name
                  : undefined
              "
            >
              <UInput
                v-model="createForm.first_name"
                placeholder="Enter first name"
                class="w-full"
                maxlength="100"
              />
            </UFormField>
            <UFormField
              label="Last Name"
              required
              :error="
                createSubmitted && createErrors.last_name
                  ? createErrors.last_name
                  : undefined
              "
            >
              <UInput
                v-model="createForm.last_name"
                placeholder="Enter last name"
                class="w-full"
                maxlength="100"
              />
            </UFormField>
          </div>
          <UFormField
            label="Email"
            required
            :error="
              createSubmitted && createErrors.email
                ? createErrors.email
                : undefined
            "
          >
            <UInput
              v-model="createForm.email"
              type="email"
              placeholder="juan@example.com"
              class="w-full"
            />
          </UFormField>
          <UFormField
            label="Contact Number"
            :error="
              createSubmitted && createErrors.contact_number
                ? createErrors.contact_number
                : undefined
            "
          >
            <UInput
              v-model="createForm.contact_number"
              placeholder="+63 912 345 6789"
              class="w-full"
              maxlength="20"
            />
          </UFormField>
          <UFormField
            label="Country"
            required
            :error="
              createSubmitted && createErrors.country
                ? createErrors.country
                : undefined
            "
          >
            <USelectMenu
              v-model="createForm.country"
              :items="COUNTRY_OPTIONS"
              value-key="value"
              label-key="label"
              placeholder="Select country"
              class="w-full"
            />
          </UFormField>
          <div class="grid grid-cols-2 gap-3">
            <UFormField
              label="Province"
              required
              :error="
                createSubmitted && createErrors.province
                  ? createErrors.province
                  : undefined
              "
            >
              <UInput
                v-model="createForm.province"
                placeholder="Enter province"
                class="w-full"
                maxlength="255"
              />
            </UFormField>
            <UFormField
              label="City"
              required
              :error="
                createSubmitted && createErrors.city
                  ? createErrors.city
                  : undefined
              "
            >
              <UInput
                v-model="createForm.city"
                placeholder="Enter city"
                class="w-full"
                maxlength="255"
              />
            </UFormField>
          </div>
          <UFormField
            label="Role"
            :error="
              createSubmitted && createErrors.role
                ? createErrors.role
                : undefined
            "
          >
            <USelect
              v-model="createForm.role"
              :items="roleOptions"
              value-key="value"
              label-key="label"
              class="w-full"
            />
          </UFormField>
          <p class="text-xs text-[#64748b]">
            A password setup email will be sent to the user after creation.
          </p>
        </div>
      </template>
    </AppModal>

    <AppModal
      v-model:open="createConfirmModal.open"
      title="Create User"
      :ui="{ content: 'max-w-sm' }"
      confirm="Create"
      confirm-color="primary"
      :confirm-loading="createLoading"
      @confirm="confirmCreate"
    >
      <template #body>
        <p class="text-sm leading-7 text-[#0f1728]">
          Create this user and send the password setup email?
        </p>
      </template>
    </AppModal>

    <AppModal
      v-model:open="editModal.open"
      title="Edit User"
      :ui="{ content: 'max-w-md' }"
      @confirm="submitEdit"
    >
      <template #body>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <UFormField
              label="First Name"
              required
              :error="
                editSubmitted && editErrors.first_name
                  ? editErrors.first_name
                  : undefined
              "
            >
              <UInput
                v-model="editForm.first_name"
                placeholder="Enter first name"
                class="w-full"
                maxlength="100"
              />
            </UFormField>
            <UFormField
              label="Last Name"
              required
              :error="
                editSubmitted && editErrors.last_name
                  ? editErrors.last_name
                  : undefined
              "
            >
              <UInput
                v-model="editForm.last_name"
                placeholder="Enter last name"
                class="w-full"
                maxlength="100"
              />
            </UFormField>
          </div>
          <UFormField
            label="Email"
            required
            :error="
              editSubmitted && editErrors.email ? editErrors.email : undefined
            "
          >
            <div class="flex items-start gap-2">
              <UInput
                v-model="editForm.email"
                type="email"
                placeholder="you@example.com"
                class="min-w-0 flex-1"
              />
              <UButton
                color="success"
                variant="soft"
                :loading="editModal.verifying"
                :disabled="!editModal.user || editModal.user.email_verified"
                class="shrink-0"
                @click="submitVerifyEmail"
              >
                {{ editModal.user?.email_verified ? 'Verified' : 'Verify' }}
              </UButton>
            </div>
          </UFormField>
          <UFormField
            label="Contact Number"
            :error="
              editSubmitted && editErrors.contact_number
                ? editErrors.contact_number
                : undefined
            "
          >
            <UInput
              v-model="editForm.contact_number"
              placeholder="+63 912 345 6789"
              class="w-full"
              maxlength="20"
            />
          </UFormField>
          <UFormField
            label="Country"
            required
            :error="
              editSubmitted && editErrors.country
                ? editErrors.country
                : undefined
            "
          >
            <USelectMenu
              v-model="editForm.country"
              :items="COUNTRY_OPTIONS"
              value-key="value"
              label-key="label"
              placeholder="Select country"
              class="w-full"
            />
          </UFormField>
          <div class="grid grid-cols-2 gap-3">
            <UFormField
              label="Province"
              required
              :error="
                editSubmitted && editErrors.province
                  ? editErrors.province
                  : undefined
              "
            >
              <UInput
                v-model="editForm.province"
                placeholder="Enter province"
                class="w-full"
                maxlength="255"
              />
            </UFormField>
            <UFormField
              label="City"
              required
              :error="
                editSubmitted && editErrors.city ? editErrors.city : undefined
              "
            >
              <UInput
                v-model="editForm.city"
                placeholder="Enter city"
                class="w-full"
                maxlength="255"
              />
            </UFormField>
          </div>
          <UFormField
            label="Role"
            :error="
              editSubmitted && editErrors.role ? editErrors.role : undefined
            "
          >
            <USelect
              v-model="editForm.role"
              :items="roleOptions"
              value-key="value"
              label-key="label"
              class="w-full"
            />
          </UFormField>
        </div>
      </template>
      <template #footer>
        <div class="flex w-full items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <UButton
              color="error"
              variant="soft"
              :loading="editModal.deleting"
              @click="submitDelete"
            >
              Delete User
            </UButton>
          </div>
          <div class="flex items-center gap-2">
            <UButton
              color="neutral"
              variant="ghost"
              @click="editModal.open = false"
            >
              Cancel
            </UButton>
            <UButton
              color="primary"
              :loading="editModal.saving"
              @click="submitEdit"
            >
              Save
            </UButton>
          </div>
        </div>
      </template>
    </AppModal>

    <AppModal
      v-model:open="editConfirmModal.open"
      title="Save Changes"
      :ui="{ content: 'max-w-sm' }"
      confirm="Save"
      confirm-color="primary"
      :confirm-loading="editModal.saving"
      @confirm="confirmEdit"
    >
      <template #body>
        <p class="text-sm leading-7 text-[#0f1728]">
          Save the changes for
          <strong>{{ editModal.user?.name }}</strong
          >?
        </p>
      </template>
    </AppModal>

    <AppModal
      v-model:open="verifyConfirmModal.open"
      title="Verify Email"
      :ui="{ content: 'max-w-sm' }"
      confirm="Verify"
      confirm-color="primary"
      :confirm-loading="editModal.verifying"
      @confirm="confirmVerifyEmail"
    >
      <template #body>
        <p class="text-sm leading-7 text-[#0f1728]">
          Mark
          <strong>{{ editModal.user?.email }}</strong>
          as verified?
        </p>
      </template>
    </AppModal>

    <AppModal
      v-model:open="deleteConfirmModal.open"
      title="Delete User"
      :ui="{ content: 'max-w-sm' }"
      confirm="Delete"
      confirm-color="error"
      :confirm-loading="editModal.deleting"
      @confirm="confirmDelete"
    >
      <template #body>
        <p class="text-sm leading-7 text-[#0f1728]">
          Delete
          <strong>{{ editModal.user?.name }}</strong
          >? This will delete the account and prevent login.
        </p>
      </template>
    </AppModal>
  </div>
</template>
