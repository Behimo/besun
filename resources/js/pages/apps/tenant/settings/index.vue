<script setup>
import { dragAndDrop } from '@formkit/drag-and-drop/vue'
import RolePermissionMatrix from '@/components/tenant/RolePermissionMatrix.vue'

definePage({
  meta: {
    action: 'manage',
    subject: 'TenantSettings',
  },
})

const route = useRoute()
const userData = useCookie('userData')
const { hasModule } = useAppShell()
const { formatDateTime } = useJalaliDate()

const tab = ref('general')
const loading = ref(true)
const saving = ref(false)
const stagesLoading = ref(false)

const tenant = ref(null)
const workspace = ref(null)
const name = ref('')

const stages = ref([])
const stageListRef = ref()
const stageDialog = ref(false)
const editingStage = ref(null)
const stageForm = ref({ name: '', color: '#4A0E17' })
const stageError = ref('')

const canBroadcast = computed(() => Boolean(userData.value?.tenant?.canBroadcast))

const broadcastForm = ref({
  title: '',
  body: '',
  kind: 'broadcast',
})
const broadcastSaving = ref(false)
const broadcastHistory = ref([])

const fetchBroadcastHistory = async () => {
  if (!canBroadcast.value)
    return

  try {
    const res = await $api('/notifications/broadcasts')
    broadcastHistory.value = res.broadcasts ?? []
  } catch (e) {
    console.error(e)
  }
}

const sendBroadcast = async () => {
  broadcastSaving.value = true
  try {
    await $api('/notifications/broadcast', {
      method: 'POST',
      body: broadcastForm.value,
    })
    broadcastForm.value = { title: '', body: '', kind: 'broadcast' }
    await fetchBroadcastHistory()
  } finally {
    broadcastSaving.value = false
  }
}

const broadcastKindItems = [
  { title: 'پیام مدیر (اعلان زرد)', value: 'broadcast' },
  { title: 'پیام سیستمی (اعلان آبی)', value: 'system' },
]

const accessLoading = ref(false)
const accessSaving = ref(false)
const accessError = ref('')
const accessCatalog = ref([])
const accessGroupLabels = ref({})
const accessDepartments = ref([])
const accessTeams = ref([])
const accessRoles = ref([])
const selectedRole = ref(null)
const selectedRolePermissions = ref([])

const fetchAccessData = async () => {
  accessLoading.value = true
  accessError.value = ''
  try {
    const [catalogRes, rolesRes] = await Promise.all([
      $api('/tenant/access/catalog'),
      $api('/tenant/access/roles'),
    ])
    accessCatalog.value = catalogRes.permissions ?? []
    accessGroupLabels.value = catalogRes.group_labels ?? {}
    accessDepartments.value = catalogRes.departments ?? []
    accessTeams.value = catalogRes.teams ?? []
    accessRoles.value = (rolesRes.roles ?? []).filter(r => r.name !== 'owner')
    if (!selectedRole.value && accessRoles.value.length)
      selectRole(accessRoles.value[0])
  } catch (e) {
    accessError.value = e?.data?.message || 'خطا در بارگذاری دسترسی‌ها'
  } finally {
    accessLoading.value = false
  }
}

const selectRole = role => {
  selectedRole.value = role
  selectedRolePermissions.value = [...(role.permissions ?? [])]
}

const roleDialog = ref(false)
const roleDialogSaving = ref(false)
const roleDialogError = ref('')
const roleForm = ref({ label: '', department: null, role_type: 'main', is_manager: true, parent_role: null })

const departmentManagerRole = dept => ({
  sales: 'sales_manager',
  marketing: 'marketing_manager',
  finance: 'finance_manager',
}[dept] ?? null)

const parentRoleOptions = computed(() => {
  const dept = roleForm.value.department

  return accessRoles.value.filter(role => {
    if (dept && role.department !== dept)
      return false

    return role.is_manager && !role.parent_role
  })
})

const permissionTemplateRole = computed(() => {
  if (roleForm.value.role_type === 'sub') {
    return accessRoles.value.find(r => r.name === roleForm.value.parent_role) ?? null
  }

  const managerName = departmentManagerRole(roleForm.value.department)
  if (managerName) {
    return accessRoles.value.find(r => r.name === managerName) ?? null
  }

  return accessRoles.value.find(r =>
    r.department === roleForm.value.department
    && r.is_manager
    && !r.parent_role,
  ) ?? null
})

const teamDialog = ref(false)
const teamDialogSaving = ref(false)
const teamDialogError = ref('')
const editingTeam = ref(null)
const teamForm = ref({ name: '' })

const openTeamDialog = (team = null) => {
  editingTeam.value = team
  teamForm.value = { name: team?.name ?? '' }
  teamDialogError.value = ''
  teamDialog.value = true
}

const saveTeam = async () => {
  if (!teamForm.value.name.trim()) {
    teamDialogError.value = 'نام تیم را وارد کنید'

    return
  }

  teamDialogSaving.value = true
  teamDialogError.value = ''

  try {
    if (editingTeam.value) {
      await $api(`/tenant/teams/${editingTeam.value.id}`, {
        method: 'PATCH',
        body: { name: teamForm.value.name },
      })
    } else {
      await $api('/tenant/teams', {
        method: 'POST',
        body: { name: teamForm.value.name },
      })
    }

    teamDialog.value = false
    await fetchAccessData()
  } catch (e) {
    teamDialogError.value = e?.data?.message || 'خطا در ذخیره تیم'
  } finally {
    teamDialogSaving.value = false
  }
}

const deleteTeam = async team => {
  accessError.value = ''
  try {
    await $api(`/tenant/teams/${team.id}`, { method: 'DELETE' })
    await fetchAccessData()
  } catch (e) {
    accessError.value = e?.data?.message || 'خطا در حذف تیم'
  }
}

const openRoleDialogForTeam = team => {
  roleForm.value = {
    label: '',
    department: team.slug,
    role_type: 'main',
    is_manager: true,
    parent_role: null,
  }
  roleDialogError.value = ''
  roleDialog.value = true
}

const rolesForTeam = teamSlug => accessRoles.value.filter(r => r.department === teamSlug)

const roleSubtitle = role => {
  const parts = []

  if (role.department) {
    const deptLabel = accessDepartments.value.find(d => d.value === role.department)?.label ?? role.department
    parts.push(`تیم ${deptLabel}`)
  }

  if (role.parent_role_label)
    parts.push(`زیرمجموعه ${role.parent_role_label}`)

  return parts.join(' · ')
}

watch(() => roleForm.value.department, dept => {
  if (!roleForm.value.parent_role)
    return

  const parent = accessRoles.value.find(r => r.name === roleForm.value.parent_role)
  if (parent && dept && parent.department !== dept)
    roleForm.value.parent_role = null
})

watch(() => roleForm.value.role_type, type => {
  if (type === 'main') {
    roleForm.value.parent_role = null
    roleForm.value.is_manager = true
  } else {
    roleForm.value.is_manager = false
  }
})

const openRoleDialog = () => {
  roleForm.value = { label: '', department: null, role_type: 'main', is_manager: true, parent_role: null }
  roleDialogError.value = ''
  roleDialog.value = true
}

const createRole = async () => {
  if (!roleForm.value.label.trim()) {
    roleDialogError.value = 'نام نقش را وارد کنید'

    return
  }

  if (!roleForm.value.department) {
    roleDialogError.value = 'تیم نقش را انتخاب کنید'

    return
  }

  if (roleForm.value.role_type === 'sub' && !roleForm.value.parent_role) {
    roleDialogError.value = 'برای نقش زیرمجموعه، نقش اصلی را انتخاب کنید'

    return
  }

  roleDialogSaving.value = true
  roleDialogError.value = ''
  try {
    const template = permissionTemplateRole.value

    const res = await $api('/tenant/access/roles', {
      method: 'POST',
      body: {
        label: roleForm.value.label,
        department: roleForm.value.department,
        is_manager: roleForm.value.role_type === 'main' ? roleForm.value.is_manager : false,
        parent_role: roleForm.value.role_type === 'sub' ? roleForm.value.parent_role : null,
        permissions: template?.permissions ?? [],
      },
    })

    accessRoles.value.push(res.role)
    selectRole(res.role)
    roleDialog.value = false
  } catch (e) {
    roleDialogError.value = e?.data?.message || 'خطا در ایجاد نقش'
  } finally {
    roleDialogSaving.value = false
  }
}

const deleteCustomRole = async role => {
  accessError.value = ''
  try {
    await $api(`/tenant/access/roles/${role.name}`, { method: 'DELETE' })
    accessRoles.value = accessRoles.value.filter(r => r.name !== role.name)
    if (selectedRole.value?.name === role.name) {
      selectedRole.value = null
      if (accessRoles.value.length)
        selectRole(accessRoles.value[0])
    }
  } catch (e) {
    accessError.value = e?.data?.message || 'خطا در حذف نقش'
  }
}

const saveRolePermissions = async () => {
  if (!selectedRole.value)
    return

  accessSaving.value = true
  accessError.value = ''
  try {
    await $api(`/tenant/access/roles/${selectedRole.value.name}`, {
      method: 'PATCH',
      body: { permissions: selectedRolePermissions.value },
    })
    selectedRole.value.permissions = [...selectedRolePermissions.value]
    const idx = accessRoles.value.findIndex(r => r.name === selectedRole.value.name)
    if (idx >= 0)
      accessRoles.value[idx].permissions = [...selectedRolePermissions.value]
  } catch (e) {
    accessError.value = e?.data?.message || 'خطا در ذخیره دسترسی نقش'
  } finally {
    accessSaving.value = false
  }
}

const hasCore = computed(() => Boolean(userData.value?.hasCoreModule))
const hasSmsModule = computed(() => hasModule('mod-sms'))

const smsLoading = ref(false)
const smsSaving = ref(false)
const smsError = ref('')
const smsAccount = ref(null)
const smsRequest = ref(null)
const smsForm = ref({
  name_family: '',
  company: '',
  national_code: '',
  mobile_number: '',
  birth_date: '',
  notes: '',
})
const smsSettingsForm = ref({ default_from_number: '' })

const smsStatusLabel = {
  draft: 'ثبت‌نشده',
  pending: 'در انتظار تأیید',
  active: 'فعال',
  suspended: 'معلق',
  rejected: 'رد شده',
}

const fetchSmsSettings = async () => {
  if (!hasSmsModule.value)
    return

  smsLoading.value = true
  smsError.value = ''
  try {
    const res = await $api('/tenant/sms')
    smsAccount.value = res.account
    smsRequest.value = res.request
    smsSettingsForm.value.default_from_number = res.account?.default_from_number || ''
    if (res.request?.status === 'pending' || res.request?.status === 'rejected') {
      smsForm.value = {
        name_family: res.request.name_family || '',
        company: res.request.company || '',
        national_code: '',
        mobile_number: res.request.mobile_number || '',
        birth_date: res.request.birth_date || '',
        notes: res.request.notes || '',
      }
    }
  } catch (e) {
    smsError.value = e?.data?.message || 'خطا در بارگذاری تنظیمات پیامک'
  } finally {
    smsLoading.value = false
  }
}

const submitSmsRequest = async () => {
  smsSaving.value = true
  smsError.value = ''
  try {
    const res = await $api('/tenant/sms/request', { method: 'POST', body: smsForm.value })
    smsRequest.value = res.request
    smsAccount.value = res.account ?? { ...smsAccount.value, status: res.request ? 'pending' : smsAccount.value?.status }
  } catch (e) {
    smsError.value = e?.data?.message || 'خطا در ثبت درخواست'
  } finally {
    smsSaving.value = false
  }
}

const saveSmsSettings = async () => {
  smsSaving.value = true
  smsError.value = ''
  try {
    const res = await $api('/tenant/sms/settings', {
      method: 'PATCH',
      body: smsSettingsForm.value,
    })
    smsAccount.value = res.account
  } catch (e) {
    smsError.value = e?.data?.message || 'خطا در ذخیره تنظیمات'
  } finally {
    smsSaving.value = false
  }
}

const fetchSettings = async () => {
  loading.value = true
  try {
    const res = await $api('/tenant/settings')
    tenant.value = res.tenant
    workspace.value = res.workspace
    name.value = res.tenant?.name ?? ''
  } finally {
    loading.value = false
  }
}

const fetchStages = async () => {
  if (!hasCore.value)
    return

  stagesLoading.value = true
  try {
    const res = await $api('/pipeline-stages?type=sales')
    stages.value = res.stages ?? []
  } finally {
    stagesLoading.value = false
  }
}

const saveGeneral = async () => {
  saving.value = true
  try {
    const res = await $api('/tenant/settings', {
      method: 'PATCH',
      body: { name: name.value },
    })
    tenant.value = res.tenant
    if (userData.value?.tenant) {
      userData.value = {
        ...userData.value,
        tenant: { ...userData.value.tenant, name: res.tenant.name },
      }
    }
  } finally {
    saving.value = false
  }
}

const openStageDialog = stage => {
  editingStage.value = stage
  stageForm.value = stage
    ? { name: stage.name, color: stage.color ?? '#4A0E17' }
    : { name: '', color: '#4A0E17' }
  stageError.value = ''
  stageDialog.value = true
}

const saveStage = async () => {
  stageError.value = ''
  try {
    if (editingStage.value) {
      const res = await $api(`/pipeline-stages/${editingStage.value.id}`, {
        method: 'PATCH',
        body: stageForm.value,
      })
      const idx = stages.value.findIndex(s => s.id === editingStage.value.id)
      if (idx >= 0)
        stages.value[idx] = res.stage
    } else {
      const res = await $api('/pipeline-stages', {
        method: 'POST',
        body: stageForm.value,
      })
      stages.value.push(res.stage)
    }
    stageDialog.value = false
  } catch (e) {
    stageError.value = e?.data?.message ?? 'خطا در ذخیره مرحله'
  }
}

const deleteStage = async stage => {
  stageError.value = ''
  try {
    await $api(`/pipeline-stages/${stage.id}`, { method: 'DELETE' })
    stages.value = stages.value.filter(s => s.id !== stage.id)
  } catch (e) {
    stageError.value = e?.data?.message ?? 'خطا در حذف مرحله'
  }
}

const persistStageOrder = async () => {
  const payload = stages.value.map((s, i) => ({
    id: s.id,
    sort_order: i + 1,
  }))

  const res = await $api('/pipeline-stages/reorder', {
    method: 'PATCH',
    body: { stages: payload },
  })
  stages.value = res.stages ?? stages.value
}

const initStageDrag = () => {
  if (!stageListRef.value)
    return

  dragAndDrop({
    parent: stageListRef,
    values: stages,
    draggable: el => el.classList.contains('stage-row'),
    handleEnd: async () => {
      await persistStageOrder()
    },
  })
}

watch(tab, value => {
  if (value === 'access' && !accessRoles.value.length)
    fetchAccessData()
  if (value === 'sms' && !smsAccount.value)
    fetchSmsSettings()
})

onMounted(async () => {
  await fetchSettings()
  await fetchStages()
  await fetchBroadcastHistory()
  if (route.query.tab)
    tab.value = route.query.tab
  if (tab.value === 'sms')
    await fetchSmsSettings()
  if (tab.value === 'access' && !accessRoles.value.length)
    await fetchAccessData()
  await nextTick()
  initStageDrag()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <div class="mb-4">
        <h4 class="text-h4 mb-1">
          تنظیمات مجموعه
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          مدیریت اطلاعات عمومی و مراحل قیف فروش (فقط مالک)
        </p>
      </div>

      <VCard :loading="loading">
        <VTabs v-model="tab">
          <VTab value="general">
            عمومی
          </VTab>
          <VTab value="funnel">
            قیف فروش
          </VTab>
          <VTab
            v-if="canBroadcast"
            value="messages"
          >
            پیام به تیم
          </VTab>
          <VTab value="access">
            تیم‌ها و دسترسی
          </VTab>
          <VTab
            v-if="hasSmsModule"
            value="sms"
          >
            پیامک
          </VTab>
        </VTabs>

        <VCardText>
          <VWindow v-model="tab">
            <VWindowItem value="general">
              <VRow>
                <VCol
                  cols="12"
                  md="8"
                >
                  <VTextField
                    v-model="name"
                    label="نام مجموعه"
                    class="mb-4"
                  />
                  <VTextField
                    :model-value="tenant?.slug"
                    label="شناسه (slug)"
                    readonly
                    class="mb-4"
                  />
                  <VBtn
                    color="primary"
                    :loading="saving"
                    @click="saveGeneral"
                  >
                    ذخیره
                  </VBtn>
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VCard variant="outlined">
                    <VCardText>
                      <div class="text-body-2 text-medium-emphasis mb-1">
                        فضای کاری پیش‌فرض
                      </div>
                      <div class="font-weight-medium mb-3">
                        {{ workspace?.name ?? '—' }}
                      </div>
                      <div class="text-body-2 text-medium-emphasis mb-1">
                        صندلی
                      </div>
                      <div class="font-weight-medium mb-3">
                        {{ tenant?.seats_used ?? 0 }} / {{ tenant?.seat_limit ?? '—' }}
                      </div>
                      <div class="text-body-2 text-medium-emphasis mb-1">
                        اشتراک
                      </div>
                      <div class="font-weight-medium mb-1">
                        {{ tenant?.plan ?? '—' }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        وضعیت: {{ tenant?.subscription_status ?? '—' }}
                        <span v-if="tenant?.core_expires_at">
                          — انقضا: {{ formatDateTime(tenant.core_expires_at) }}
                        </span>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
            </VWindowItem>

            <VWindowItem value="funnel">
              <VAlert
                v-if="!hasCore"
                type="warning"
                variant="tonal"
                class="mb-4"
              >
                برای مدیریت قیف فروش، ابتدا ماژول پایه را فعال کنید.
                <RouterLink
                  :to="{ name: 'apps-tenant-modules' }"
                  class="ms-1"
                >
                  وضعیت ماژول‌ها
                </RouterLink>
              </VAlert>

              <template v-else>
                <div class="d-flex justify-space-between align-center mb-4">
                  <p class="text-body-2 text-medium-emphasis mb-0">
                    مراحل را بکشید و رها کنید تا ترتیب تغییر کند. حداقل ۲ مرحله باید باقی بماند.
                  </p>
                  <VBtn
                    prepend-icon="tabler-plus"
                    @click="openStageDialog(null)"
                  >
                    مرحله جدید
                  </VBtn>
                </div>

                <VAlert
                  v-if="stageError"
                  type="error"
                  variant="tonal"
                  class="mb-4"
                  closable
                  @click:close="stageError = ''"
                >
                  {{ stageError }}
                </VAlert>

                <VProgressLinear
                  v-if="stagesLoading"
                  indeterminate
                  class="mb-4"
                />

                <div
                  ref="stageListRef"
                  class="d-flex flex-column gap-2"
                >
                  <VCard
                    v-for="stage in stages"
                    :key="stage.id"
                    variant="outlined"
                    class="stage-row"
                  >
                    <VCardText class="d-flex align-center gap-3 py-3">
                      <VIcon
                        icon="tabler-grip-vertical"
                        class="cursor-grab text-medium-emphasis"
                      />
                      <VAvatar
                        :color="stage.color"
                        size="16"
                        rounded="circle"
                      />
                      <div class="flex-grow-1">
                        <div class="font-weight-medium">
                          {{ stage.name }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ stage.deals_count ?? 0 }} معامله
                        </div>
                      </div>
                      <VBtn
                        icon
                        variant="text"
                        size="small"
                        @click="openStageDialog(stage)"
                      >
                        <VIcon icon="tabler-pencil" />
                      </VBtn>
                      <VBtn
                        icon
                        variant="text"
                        size="small"
                        color="error"
                        :disabled="(stage.deals_count ?? 0) > 0"
                        @click="deleteStage(stage)"
                      >
                        <VIcon icon="tabler-trash" />
                      </VBtn>
                    </VCardText>
                  </VCard>
                </div>
              </template>
            </VWindowItem>

            <VWindowItem value="access">
              <VAlert
                v-if="accessError"
                type="error"
                variant="tonal"
                class="mb-4"
                closable
                @click:close="accessError = ''"
              >
                {{ accessError }}
              </VAlert>

              <VProgressLinear
                v-if="accessLoading"
                indeterminate
                class="mb-4"
              />

              <template v-else>
                <div class="d-flex justify-space-between align-center flex-wrap gap-2 mb-4">
                  <p class="text-body-2 text-medium-emphasis mb-0">
                    تیم‌ها را بسازید، برای هر تیم نقش اصلی (مدیر) و زیرمجموعه تعریف کنید، سپس اعضا را در صفحه کاربران به تیم و نقش اختصاص دهید.
                  </p>
                  <div class="d-flex gap-2">
                    <VBtn
                      prepend-icon="tabler-users-group"
                      size="small"
                      variant="tonal"
                      @click="openTeamDialog()"
                    >
                      تیم جدید
                    </VBtn>
                    <VBtn
                      prepend-icon="tabler-plus"
                      size="small"
                      @click="openRoleDialog"
                    >
                      نقش جدید
                    </VBtn>
                  </div>
                </div>

                <VRow class="mb-4">
                  <VCol
                    v-for="team in accessTeams"
                    :key="team.id"
                    cols="12"
                    md="4"
                  >
                    <VCard variant="outlined">
                      <VCardText>
                        <div class="d-flex align-start justify-space-between gap-2 mb-2">
                          <div>
                            <div class="text-h6 mb-1">
                              {{ team.name }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                              {{ team.members_count }} عضو · {{ team.roles_count }} نقش
                            </div>
                          </div>
                          <VChip
                            v-if="team.is_system"
                            size="x-small"
                            variant="tonal"
                          >
                            پیش‌فرض
                          </VChip>
                        </div>

                        <div
                          v-if="rolesForTeam(team.slug).length"
                          class="d-flex flex-wrap gap-1 mb-3"
                        >
                          <VChip
                            v-for="role in rolesForTeam(team.slug)"
                            :key="role.name"
                            size="x-small"
                            :color="role.is_manager ? 'warning' : 'secondary'"
                            variant="tonal"
                          >
                            {{ role.label }}
                          </VChip>
                        </div>
                        <p
                          v-else
                          class="text-caption text-medium-emphasis mb-3"
                        >
                          هنوز نقشی برای این تیم تعریف نشده
                        </p>

                        <div class="d-flex flex-wrap gap-1">
                          <VBtn
                            size="x-small"
                            variant="tonal"
                            @click="openRoleDialogForTeam(team)"
                          >
                            نقش اصلی
                          </VBtn>
                          <VBtn
                            size="x-small"
                            variant="text"
                            @click="openTeamDialog(team)"
                          >
                            ویرایش
                          </VBtn>
                          <VBtn
                            v-if="!team.is_system"
                            size="x-small"
                            variant="text"
                            color="error"
                            :disabled="team.members_count > 0 || team.roles_count > 0"
                            @click="deleteTeam(team)"
                          >
                            حذف
                          </VBtn>
                        </div>
                      </VCardText>
                    </VCard>
                  </VCol>
                </VRow>

                <VRow>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VList
                      density="compact"
                      class="pa-0"
                    >
                      <VListItem
                        v-for="role in accessRoles"
                        :key="role.name"
                        :title="role.label"
                        :subtitle="roleSubtitle(role)"
                        :active="selectedRole?.name === role.name"
                        @click="selectRole(role)"
                      >
                        <template #append>
                          <VChip
                            v-if="role.is_custom"
                            size="x-small"
                            color="info"
                            variant="tonal"
                            class="me-1"
                          >
                            سفارشی
                          </VChip>
                          <VChip
                            v-if="role.is_manager"
                            size="x-small"
                            color="warning"
                            variant="tonal"
                          >
                            مدیر
                          </VChip>
                        </template>
                      </VListItem>
                    </VList>
                  </VCol>
                  <VCol
                    cols="12"
                    md="8"
                  >
                    <template v-if="selectedRole">
                      <div class="d-flex justify-space-between align-center mb-3">
                        <h6 class="text-h6">
                          دسترسی‌های {{ selectedRole.label }}
                        </h6>
                        <div class="d-flex gap-2">
                          <VBtn
                            v-if="selectedRole.is_custom"
                            color="error"
                            size="small"
                            variant="tonal"
                            :disabled="(selectedRole.members_count ?? 0) > 0"
                            @click="deleteCustomRole(selectedRole)"
                          >
                            حذف نقش
                          </VBtn>
                          <VBtn
                            color="primary"
                            size="small"
                            :loading="accessSaving"
                            @click="saveRolePermissions"
                          >
                            ذخیره پیش‌فرض نقش
                          </VBtn>
                        </div>
                      </div>
                      <p
                        v-if="selectedRole.is_custom && (selectedRole.members_count ?? 0) > 0"
                        class="text-caption text-medium-emphasis mb-2"
                      >
                        این نقش به {{ selectedRole.members_count }} عضو اختصاص دارد و تا زمانی که عضوی دارد قابل حذف نیست.
                      </p>
                      <RolePermissionMatrix
                        v-model="selectedRolePermissions"
                        :permissions="accessCatalog"
                        :group-labels="accessGroupLabels"
                      />
                    </template>
                  </VCol>
                </VRow>
              </template>
            </VWindowItem>

            <VWindowItem
              v-if="canBroadcast"
              value="messages"
            >
              <p class="text-body-2 text-medium-emphasis mb-4">
                پیام برای همه اعضای فعال مجموعه در اعلان هدر (زنگ) نمایش داده می‌شود.
              </p>

              <VRow>
                <VCol
                  cols="12"
                  md="7"
                >
                  <AppSelect
                    v-model="broadcastForm.kind"
                    :items="broadcastKindItems"
                    item-title="title"
                    item-value="value"
                    label="نوع پیام"
                    class="mb-4"
                  />
                  <AppTextField
                    v-model="broadcastForm.title"
                    label="عنوان پیام *"
                    class="mb-4"
                  />
                  <AppTextarea
                    v-model="broadcastForm.body"
                    label="متن پیام *"
                    rows="4"
                    class="mb-4"
                  />
                  <VBtn
                    color="primary"
                    prepend-icon="tabler-send"
                    :loading="broadcastSaving"
                    :disabled="!broadcastForm.title.trim() || !broadcastForm.body.trim()"
                    @click="sendBroadcast"
                  >
                    ارسال به همه کاربران
                  </VBtn>
                </VCol>
                <VCol
                  cols="12"
                  md="5"
                >
                  <h6 class="text-h6 mb-3">
                    پیام‌های اخیر
                  </h6>
                  <VList
                    v-if="broadcastHistory.length"
                    lines="two"
                    class="pa-0"
                  >
                    <VListItem
                      v-for="item in broadcastHistory"
                      :key="item.id"
                      :title="item.title"
                      :subtitle="item.body"
                    >
                      <template #append>
                        <VChip
                          size="x-small"
                          variant="tonal"
                        >
                          {{ item.recipients_count }} نفر
                        </VChip>
                      </template>
                    </VListItem>
                  </VList>
                  <p
                    v-else
                    class="text-body-2 text-medium-emphasis"
                  >
                    هنوز پیامی ارسال نشده است.
                  </p>
                </VCol>
              </VRow>
            </VWindowItem>

            <VWindowItem value="sms">
              <VAlert
                v-if="smsError"
                type="error"
                variant="tonal"
                class="mb-4"
              >
                {{ smsError }}
              </VAlert>

              <div
                v-if="smsLoading"
                class="text-center py-8"
              >
                در حال بارگذاری...
              </div>

              <template v-else>
                <VChip
                  class="mb-4"
                  :color="smsAccount?.is_active ? 'success' : 'warning'"
                  variant="tonal"
                >
                  وضعیت پنل: {{ smsStatusLabel[smsAccount?.status] || smsAccount?.status || '—' }}
                </VChip>

                <div
                  v-if="smsAccount?.is_active"
                  class="mb-6"
                >
                  <p class="text-body-2 mb-2">
                    نام کاربری IPPanel: {{ smsAccount.ippanel_username }}
                  </p>
                  <p class="text-body-2 mb-4">
                    موجودی: {{ Number(smsAccount.credit || 0).toLocaleString('fa-IR') }}
                  </p>
                  <VTextField
                    v-model="smsSettingsForm.default_from_number"
                    label="خط ارسال پیش‌فرض (E.164)"
                    class="mb-4"
                    placeholder="+983000..."
                  />
                  <VBtn
                    color="primary"
                    :loading="smsSaving"
                    class="me-2"
                    @click="saveSmsSettings"
                  >
                    ذخیره خط ارسال
                  </VBtn>
                  <VBtn
                    variant="tonal"
                    :to="{ name: 'apps-crm-sms' }"
                  >
                    ورود به پنل پیامک
                  </VBtn>
                </div>

                <template v-else>
                  <VAlert
                    v-if="smsRequest?.status === 'rejected'"
                    type="warning"
                    variant="tonal"
                    class="mb-4"
                  >
                    درخواست رد شد: {{ smsRequest.rejection_reason }}
                  </VAlert>

                  <VAlert
                    v-if="smsRequest?.status === 'pending'"
                    type="info"
                    variant="tonal"
                    class="mb-4"
                  >
                    درخواست شما در انتظار تأیید اپراتور است.
                  </VAlert>

                  <VRow v-if="smsRequest?.status !== 'pending'">
                    <VCol
                      cols="12"
                      md="8"
                    >
                      <VTextField
                        v-model="smsForm.name_family"
                        label="نام و نام خانوادگی"
                        class="mb-4"
                      />
                      <VTextField
                        v-model="smsForm.company"
                        label="شرکت"
                        class="mb-4"
                      />
                      <VTextField
                        v-model="smsForm.national_code"
                        label="کد ملی"
                        class="mb-4"
                      />
                      <VTextField
                        v-model="smsForm.mobile_number"
                        label="موبایل"
                        class="mb-4"
                      />
                      <AppJalaliDatePicker
                        v-model="smsForm.birth_date"
                        label="تاریخ تولد"
                        class="mb-4"
                      />
                      <VTextarea
                        v-model="smsForm.notes"
                        label="توضیحات"
                        rows="2"
                        class="mb-4"
                      />
                      <VBtn
                        color="primary"
                        :loading="smsSaving"
                        @click="submitSmsRequest"
                      >
                        ثبت درخواست پنل پیامک
                      </VBtn>
                    </VCol>
                  </VRow>
                </template>
              </template>
            </VWindowItem>
          </VWindow>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <VDialog
    v-model="teamDialog"
    max-width="420"
  >
    <VCard :title="editingTeam ? 'ویرایش تیم' : 'تیم جدید'">
      <VCardText>
        <VAlert
          v-if="teamDialogError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ teamDialogError }}
        </VAlert>

        <AppTextField
          v-model="teamForm.name"
          label="نام تیم *"
          placeholder="مثلاً: پشتیبانی، انبار، منابع انسانی"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="text"
          @click="teamDialog = false"
        >
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="teamDialogSaving"
          @click="saveTeam"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="roleDialog"
    max-width="480"
  >
    <VCard title="نقش جدید">
      <VCardText>
        <VAlert
          v-if="roleDialogError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ roleDialogError }}
        </VAlert>

        <AppTextField
          v-model="roleForm.label"
          label="نام نقش *"
          :placeholder="roleForm.role_type === 'main' ? 'مثلاً: مدیر ارشد فروش' : 'مثلاً: کارشناس فروش'"
          class="mb-4"
        />

        <AppSelect
          v-model="roleForm.department"
          :items="accessDepartments"
          item-title="label"
          item-value="value"
          label="تیم *"
          class="mb-4"
        />

        <div class="mb-4">
          <div class="text-body-2 font-weight-medium mb-2">
            نوع نقش *
          </div>
          <VBtnToggle
            v-model="roleForm.role_type"
            mandatory
            divided
            color="primary"
            class="w-100"
          >
            <VBtn
              value="main"
              class="flex-grow-1"
            >
              نقش اصلی
            </VBtn>
            <VBtn
              value="sub"
              class="flex-grow-1"
            >
              زیرمجموعه
            </VBtn>
          </VBtnToggle>
          <p class="text-caption text-medium-emphasis mt-2 mb-0">
            <template v-if="roleForm.role_type === 'main'">
              نقش اصلی سر تیم است (مثل مدیر فروش) و می‌تواند زیرمجموعه داشته باشد.
            </template>
            <template v-else>
              نقش زیرمجموعه زیر یک نقش اصلی قرار می‌گیرد (مثل کارمند زیر مدیر).
            </template>
          </p>
        </div>

        <AppSelect
          v-if="roleForm.role_type === 'sub'"
          v-model="roleForm.parent_role"
          :items="parentRoleOptions"
          item-title="label"
          item-value="name"
          label="نقش اصلی *"
          :hint="permissionTemplateRole ? `دسترسی‌های پیش‌فرض از «${permissionTemplateRole.label}» کپی می‌شود.` : 'ابتدا تیم را انتخاب کنید.'"
          persistent-hint
          class="mb-4"
        />

        <VSwitch
          v-if="roleForm.role_type === 'main'"
          v-model="roleForm.is_manager"
          label="نقش مدیریتی (دیدن گزارش‌های عملکرد تیم)"
          hide-details
          class="mb-2"
        />
        <p
          v-if="roleForm.role_type === 'main' && permissionTemplateRole"
          class="text-caption text-medium-emphasis mt-1 mb-0"
        >
          دسترسی‌های پیش‌فرض از «{{ permissionTemplateRole.label }}» کپی می‌شود؛ بعداً از ماتریس دسترسی قابل ویرایش است.
        </p>
        <p
          v-else-if="roleForm.role_type === 'sub'"
          class="text-caption text-medium-emphasis mt-1 mb-0"
        >
          دسترسی‌های ریز (مثل واگذاری تسک) را بعد از ایجاد نقش از ماتریس دسترسی تنظیم کنید.
        </p>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="text"
          @click="roleDialog = false"
        >
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="roleDialogSaving"
          @click="createRole"
        >
          ایجاد نقش
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="stageDialog"
    max-width="420"
  >
    <VCard :title="editingStage ? 'ویرایش مرحله' : 'مرحله جدید'">
      <VCardText>
        <VTextField
          v-model="stageForm.name"
          label="نام مرحله"
          class="mb-4"
        />
        <VTextField
          v-model="stageForm.color"
          label="رنگ"
          type="color"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="text"
          @click="stageDialog = false"
        >
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          @click="saveStage"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.cursor-grab {
  cursor: grab;
}
</style>
