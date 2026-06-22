<script setup>
import RolePermissionMatrix from '@/components/tenant/RolePermissionMatrix.vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  userId: { type: [Number, String], default: null },
})

const emit = defineEmits(['update:isDialogVisible', 'saved'])

const loading = ref(false)
const saving = ref(false)
const error = ref('')

const catalog = ref([])
const groupLabels = ref({})
const roleOptions = ref([])
const departmentOptions = ref([])

const form = ref({
  role: 'sales_employee',
  department: 'sales',
  permissions: [],
})

const rolePermissions = ref([])
const basePermissions = ref([])
const overrides = ref({ grant: [], revoke: [] })

const fetchAccess = async () => {
  if (!props.userId)
    return

  loading.value = true
  error.value = ''

  try {
    const [catalogRes, accessRes] = await Promise.all([
      $api('/tenant/access/catalog'),
      $api(`/users/${props.userId}/access`),
    ])

    catalog.value = catalogRes.permissions ?? []
    groupLabels.value = catalogRes.group_labels ?? {}
    roleOptions.value = (catalogRes.roles ?? []).filter(r => r.value !== 'owner')
    departmentOptions.value = catalogRes.departments ?? []

    const user = accessRes.user ?? {}
    form.value.role = user.role ?? 'sales_employee'
    form.value.department = user.department ?? 'sales'
    rolePermissions.value = accessRes.role_permissions ?? []
    basePermissions.value = accessRes.role_permissions ?? []
    form.value.permissions = accessRes.effective_permissions ?? []
    overrides.value = accessRes.overrides ?? { grant: [], revoke: [] }
  } catch (e) {
    error.value = e?.data?.message || 'خطا در بارگذاری دسترسی‌ها'
  } finally {
    loading.value = false
  }
}

watch(() => props.isDialogVisible, visible => {
  if (visible)
    fetchAccess()
})

watch(() => form.value.role, async newRole => {
  const role = roleOptions.value.find(r => r.value === newRole)
  if (role?.department)
    form.value.department = role.department

  try {
    const res = await $api('/tenant/access/roles')
    const match = (res.roles ?? []).find(r => r.name === newRole)
    if (match) {
      rolePermissions.value = match.permissions ?? []
      basePermissions.value = match.permissions ?? []
      form.value.permissions = [...match.permissions]
      overrides.value = { grant: [], revoke: [] }
    }
  } catch {
    // keep current
  }
})

const computeOverrides = () => {
  const base = new Set(basePermissions.value)
  const current = new Set(form.value.permissions)

  const grant = [...current].filter(p => !base.has(p))
  const revoke = [...base].filter(p => !current.has(p))

  return { grant, revoke }
}

const save = async () => {
  saving.value = true
  error.value = ''

  try {
    const permissionOverrides = computeOverrides()

    await $api(`/users/${props.userId}/access`, {
      method: 'PUT',
      body: {
        role: form.value.role,
        department: form.value.department,
        permission_overrides: permissionOverrides,
      },
    })
    emit('saved')
    emit('update:isDialogVisible', false)
  } catch (e) {
    error.value = e?.data?.message || 'خطا در ذخیره دسترسی'
  } finally {
    saving.value = false
  }
}

const close = () => emit('update:isDialogVisible', false)
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="720"
    scrollable
    @update:model-value="close"
  >
    <VCard title="ویرایش دسترسی عضو">
      <VCardText>
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ error }}
        </VAlert>

        <VProgressLinear
          v-if="loading"
          indeterminate
          class="mb-4"
        />

        <template v-else>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.role"
                :items="roleOptions"
                item-title="label"
                item-value="value"
                label="نقش"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.department"
                :items="departmentOptions"
                item-title="label"
                item-value="value"
                label="تیم"
              />
            </VCol>
          </VRow>

          <p class="text-body-2 text-medium-emphasis mb-2">
            دسترسی‌ها (تغییر نسبت به پیش‌فرض نقش به‌صورت override ذخیره می‌شود)
          </p>

          <RolePermissionMatrix
            v-model="form.permissions"
            :permissions="catalog"
            :group-labels="groupLabels"
          />
        </template>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="close">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          :disabled="loading"
          @click="save"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
