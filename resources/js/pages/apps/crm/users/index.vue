<script setup>
definePage({ meta: { action: 'manage', subject: 'Users' } })

import MemberAccessDialog from '@/components/dialogs/MemberAccessDialog.vue'

const router = useRouter()
const userData = useCookie('userData')

const users = ref([])
const pendingInvites = ref([])
const loading = ref(true)
const tab = ref('members')

const searchPhone = ref('')
const searchLoading = ref(false)
const searchResult = ref(null)
const searchError = ref('')

const inviteDialog = ref(false)
const inviteForm = ref({ phone: '', role: 'sales_employee', department: 'sales' })
const accessDialog = ref(false)
const editingUserId = ref(null)

const isOwner = computed(() => Boolean(userData.value?.tenant?.isOwner))

const defaultRoleOptions = [
  { title: 'کارمند فروش', value: 'sales_employee', department: 'sales' },
  { title: 'مدیر فروش', value: 'sales_manager', department: 'sales' },
  { title: 'کارمند بازاریابی', value: 'marketing_employee', department: 'marketing' },
  { title: 'مدیر بازاریابی', value: 'marketing_manager', department: 'marketing' },
  { title: 'کارمند مالی', value: 'finance_employee', department: 'finance' },
  { title: 'مدیر مالی', value: 'finance_manager', department: 'finance' },
]

const roleOptions = ref([...defaultRoleOptions])
const teamOptions = ref([
  { title: 'فروش', value: 'sales' },
  { title: 'بازاریابی', value: 'marketing' },
  { title: 'مالی', value: 'finance' },
])

// Owners also see the custom roles defined in tenant settings.
const fetchRoleOptions = async () => {
  if (!isOwner.value)
    return

  try {
    const res = await $api('/tenant/access/catalog')

    const roles = (res.roles ?? [])
      .filter(r => r.value !== 'owner')
      .map(r => ({ title: r.label, value: r.value, department: r.department }))

    if (roles.length)
      roleOptions.value = roles

    const teams = (res.departments ?? []).map(d => ({ title: d.label, value: d.value }))
    if (teams.length)
      teamOptions.value = teams
  } catch {
    // keep defaults
  }
}

const inviteRoleOptions = computed(() => {
  if (isOwner.value)
    return roleOptions.value

  return roleOptions.value.filter(r => r.value.endsWith('_employee'))
})
const inviteError = ref('')
const inviteLoading = ref(false)

const tenantId = computed(() => userData.value?.tenant?.id)

const normalizePhoneInput = phone => {
  const digits = (phone || '').replace(/\D/g, '')

  if (digits.length === 12 && digits.startsWith('98')) {
    return `0${digits.slice(2)}`
  }

  if (digits.length === 10 && digits.startsWith('9')) {
    return `0${digits}`
  }

  if (digits.length === 11 && digits.startsWith('09')) {
    return digits
  }

  return null
}

const isValidPhone = computed(() => normalizePhoneInput(searchPhone.value) !== null)

const fetchUsers = async () => {
  loading.value = true
  try {
    const res = await $api('/users')
    users.value = res.users ?? []
  } finally {
    loading.value = false
  }
}

const fetchPendingInvites = async () => {
  if (! tenantId.value) {
    return
  }

  try {
    const res = await $api(`/tenants/${tenantId.value}/invitations`)
    pendingInvites.value = res.invitations ?? []
  } catch {
    pendingInvites.value = []
  }
}

const searchUser = async () => {
  searchError.value = ''
  searchResult.value = null

  const normalized = normalizePhoneInput(searchPhone.value)
  if (! normalized) {
    searchError.value = 'شماره موبایل باید کامل و ۱۱ رقمی باشد (مثال: ۰۹۱۲۳۴۵۶۷۸۹)'

    return
  }

  searchLoading.value = true
  try {
    const res = await $api(`/platform/users/search?phone=${encodeURIComponent(normalized)}`)
    searchResult.value = res
  } catch (e) {
    searchError.value = e?.data?.message || 'خطا در جستجو'
  } finally {
    searchLoading.value = false
  }
}

const openProfile = userId => {
  router.push({ name: 'apps-profile-id', params: { id: userId } })
}

const openInviteFromSearch = () => {
  inviteForm.value = {
    phone: searchResult.value?.phone || normalizePhoneInput(searchPhone.value) || '',
    role: 'sales_employee',
    department: 'sales',
  }
  inviteError.value = ''
  inviteDialog.value = true
}

const sendInvite = async () => {
  inviteError.value = ''

  if (! tenantId.value) {
    inviteError.value = 'مجموعه‌ای انتخاب نشده است.'

    return
  }

  const normalized = normalizePhoneInput(inviteForm.value.phone)
  if (! normalized) {
    inviteError.value = 'شماره موبایل باید کامل و ۱۱ رقمی باشد'

    return
  }

  inviteLoading.value = true
  try {
    await $api(`/tenants/${tenantId.value}/invitations`, {
      method: 'POST',
      body: {
        phone: normalized,
        role: inviteForm.value.role,
        department: inviteForm.value.department,
      },
    })
    inviteDialog.value = false
    inviteForm.value = { phone: '', role: 'sales_employee', department: 'sales' }
    await fetchPendingInvites()
    if (searchResult.value) {
      await searchUser()
    }
  } catch (e) {
    inviteError.value = e?.data?.message || 'خطا در ارسال دعوت'
  } finally {
    inviteLoading.value = false
  }
}

const cancelInvite = async invitationId => {
  try {
    await $api(`/invitations/${invitationId}`, { method: 'DELETE' })
    await fetchPendingInvites()
    if (searchResult.value) {
      await searchUser()
    }
  } catch (e) {
    searchError.value = e?.data?.message || 'خطا در لغو دعوت'
  }
}

const roleLabel = role => {
  const match = roleOptions.value.find(r => r.value === role)
  if (match)
    return match.title

  const labels = {
    owner: 'مالک مجموعه',
    admin: 'مدیر (قدیمی)',
    employee: 'کارمند (قدیمی)',
  }

  return labels[role] ?? role
}

const departmentLabel = dept => {
  const match = teamOptions.value.find(t => t.value === dept)

  return match?.title ?? dept ?? '—'
}

const openAccessDialog = userId => {
  editingUserId.value = userId
  accessDialog.value = true
}

const onAccessSaved = async () => {
  await fetchUsers()
}

watch(() => inviteForm.value.role, role => {
  const match = roleOptions.value.find(r => r.value === role)
  if (match?.department)
    inviteForm.value.department = match.department
})

onMounted(async () => {
  await Promise.all([fetchUsers(), fetchPendingInvites(), fetchRoleOptions()])
})
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4 pb-0">
      <div>
        <h5 class="text-h5 mb-1">
          کاربران و دعوت
        </h5>
        <p class="text-body-2 text-medium-emphasis mb-0">
          جستجو در کل سیستم با شماره موبایل کامل، مشاهده سابقه همکاری و ارسال درخواست عضویت
        </p>
      </div>
    </VCardText>

    <VTabs
      v-model="tab"
      class="px-4 mt-2"
    >
      <VTab value="members">
        اعضای مجموعه
      </VTab>
      <VTab value="search">
        جستجو و دعوت
      </VTab>
      <VTab value="pending">
        دعوت‌های در انتظار
        <VBadge
          v-if="pendingInvites.length"
          :content="pendingInvites.length"
          color="warning"
          inline
          class="ms-2"
        />
      </VTab>
    </VTabs>

    <VWindow v-model="tab">
      <VWindowItem value="members">
        <VCardText>
          <VDataTable
            :headers="[
              { title: 'نام', key: 'name' },
              { title: 'موبایل', key: 'phone' },
              { title: 'تیم', key: 'department' },
              { title: 'نقش', key: 'role' },
              { title: 'عملیات', key: 'actions', sortable: false },
            ]"
            :items="users"
            :loading="loading"
          >
            <template #item.phone="{ item }">
              <span dir="ltr">{{ item.phone }}</span>
            </template>
            <template #item.department="{ item }">
              {{ item.department_label || departmentLabel(item.department) }}
            </template>
            <template #item.role="{ item }">
              {{ item.role_label || roleLabel(item.role) }}
            </template>
            <template #item.actions="{ item }">
              <VBtn
                size="small"
                variant="tonal"
                prepend-icon="tabler-user-search"
                @click="openProfile(item.id)"
              >
                پروفایل
              </VBtn>
              <VBtn
                v-if="isOwner && item.role !== 'owner'"
                size="small"
                variant="tonal"
                color="primary"
                class="ms-1"
                prepend-icon="tabler-shield"
                @click="openAccessDialog(item.id)"
              >
                دسترسی
              </VBtn>
            </template>
          </VDataTable>
        </VCardText>
      </VWindowItem>

      <VWindowItem value="search">
        <VCardText>
          <VAlert
            type="info"
            variant="tonal"
            class="mb-4"
          >
            برای جستجو در کل سیستم، شماره موبایل را کامل وارد کنید (۱۱ رقم، مثال: ۰۹۱۲۳۴۵۶۷۸۹)
          </VAlert>

          <div class="d-flex flex-wrap gap-3 align-end mb-4">
            <AppTextField
              v-model="searchPhone"
              label="شماره موبایل"
              placeholder="۰۹۱۲۳۴۵۶۷۸۹"
              dir="ltr"
              class="flex-grow-1"
              style="max-width: 320px;"
              :error-messages="searchError && !searchLoading ? [searchError] : []"
              @keyup.enter="searchUser"
            />
            <VBtn
              color="primary"
              :loading="searchLoading"
              :disabled="!isValidPhone"
              prepend-icon="tabler-search"
              @click="searchUser"
            >
              جستجو
            </VBtn>
          </div>

          <VCard
            v-if="searchResult?.found"
            variant="outlined"
            class="mb-4"
          >
            <VCardText>
              <div class="d-flex align-center gap-4 flex-wrap">
                <VAvatar
                  size="56"
                  color="primary"
                  variant="tonal"
                >
                  <VImg
                    v-if="searchResult.user.avatar"
                    :src="searchResult.user.avatar"
                  />
                  <span v-else>{{ searchResult.user.name?.charAt(0) }}</span>
                </VAvatar>
                <div class="flex-grow-1">
                  <h6 class="text-h6 mb-1">
                    {{ searchResult.user.name }}
                  </h6>
                  <div
                    class="text-body-2 text-medium-emphasis"
                    dir="ltr"
                  >
                    {{ searchResult.user.phone }}
                  </div>
                  <div
                    v-if="searchResult.user.profile?.hidden"
                    class="text-body-2 mt-1 text-medium-emphasis"
                  >
                    پروفایل خصوصی
                  </div>
                  <div
                    v-else-if="searchResult.user.profile?.job_title"
                    class="text-body-2 mt-1"
                  >
                    {{ searchResult.user.profile.job_title }}
                    <span v-if="searchResult.user.profile.city"> — {{ searchResult.user.profile.city }}</span>
                  </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <VChip
                    size="small"
                    prepend-icon="tabler-building"
                  >
                    {{ searchResult.user.tenant_count }} مجموعه
                  </VChip>
                  <VChip
                    v-if="searchResult.user.average_rating"
                    size="small"
                    color="warning"
                    prepend-icon="tabler-star-filled"
                  >
                    {{ searchResult.user.average_rating }}
                  </VChip>
                  <VChip
                    v-if="searchResult.user.is_member"
                    size="small"
                    color="success"
                  >
                    عضو این مجموعه
                  </VChip>
                  <VChip
                    v-else-if="searchResult.user.has_pending_invite"
                    size="small"
                    color="warning"
                  >
                    دعوت در انتظار
                  </VChip>
                </div>
              </div>

              <div class="d-flex gap-2 mt-4 flex-wrap">
                <VBtn
                  variant="tonal"
                  prepend-icon="tabler-id"
                  @click="openProfile(searchResult.user.id)"
                >
                  مشاهده پروفایل کامل
                </VBtn>
                <VBtn
                  v-if="!searchResult.user.is_member && !searchResult.user.has_pending_invite"
                  color="primary"
                  prepend-icon="tabler-user-plus"
                  @click="openInviteFromSearch"
                >
                  ارسال درخواست عضویت
                </VBtn>
              </div>
            </VCardText>
          </VCard>

          <VAlert
            v-else-if="searchResult && !searchResult.found"
            type="warning"
            variant="tonal"
          >
            کاربری با این شماره در سیستم یافت نشد. می‌توانید دعوت‌نامه ارسال کنید تا پس از ثبت‌نام، دعوت را بپذیرد.
            <div class="mt-3">
              <VBtn
                size="small"
                color="primary"
                prepend-icon="tabler-user-plus"
                @click="openInviteFromSearch"
              >
                ارسال دعوت به {{ searchResult.phone_display }}
              </VBtn>
            </div>
          </VAlert>
        </VCardText>
      </VWindowItem>

      <VWindowItem value="pending">
        <VCardText>
          <VAlert
            v-if="!pendingInvites.length"
            type="info"
            variant="tonal"
          >
            دعوت‌نامه در انتظاری وجود ندارد.
          </VAlert>

          <VList v-else>
            <VListItem
              v-for="invite in pendingInvites"
              :key="invite.id"
            >
              <template #prepend>
                <VAvatar
                  color="primary"
                  variant="tonal"
                >
                  <VIcon icon="tabler-mail" />
                </VAvatar>
              </template>
              <VListItemTitle dir="ltr">
                {{ invite.invited_user?.name || invite.invited_phone }}
              </VListItemTitle>
              <VListItemSubtitle>
                نقش: {{ roleLabel(invite.role) }} — انقضا: {{ invite.expires_at_jalali }}
              </VListItemSubtitle>
              <template #append>
                <VBtn
                  size="small"
                  color="error"
                  variant="text"
                  @click="cancelInvite(invite.id)"
                >
                  لغو
                </VBtn>
              </template>
            </VListItem>
          </VList>
        </VCardText>
      </VWindowItem>
    </VWindow>
  </VCard>

  <VDialog
    v-model="inviteDialog"
    max-width="440"
  >
    <VCard title="ارسال درخواست عضویت">
      <VCardText>
        <VAlert
          v-if="inviteError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ inviteError }}
        </VAlert>
        <AppTextField
          v-model="inviteForm.phone"
          label="شماره موبایل"
          placeholder="۰۹۱۲۳۴۵۶۷۸۹"
          dir="ltr"
          class="mb-4 text-start"
          hint="شماره باید کامل ۱۱ رقمی باشد"
          persistent-hint
        />
        <AppSelect
          v-model="inviteForm.role"
          :items="inviteRoleOptions"
          item-title="title"
          item-value="value"
          label="نقش"
          class="mb-4"
        />
        <AppSelect
          v-model="inviteForm.department"
          :items="teamOptions"
          item-title="title"
          item-value="value"
          label="تیم"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="inviteDialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="inviteLoading"
          @click="sendInvite"
        >
          ارسال درخواست
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <MemberAccessDialog
    v-model:is-dialog-visible="accessDialog"
    :user-id="editingUserId"
    @saved="onAccessSaved"
  />
</template>
