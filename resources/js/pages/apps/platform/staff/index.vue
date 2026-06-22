<script setup>
definePage({
  meta: {
    action: 'manage',
    subject: 'PlatformSuperAdmin',
  },
})

const loading = ref(false)
const staff = ref([])
const dialog = ref(false)
const editItem = ref(null)
const form = ref({
  name: '',
  email: '',
  password: '',
  role: 'support',
  is_active: true,
})

const roleItems = [
  { title: 'کاربر اداری', value: 'admin' },
  { title: 'پشتیبان', value: 'support' },
]

const fetchStaff = async () => {
  loading.value = true
  try {
    const res = await $api('/platform/staff')
    staff.value = res.staff ?? []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  editItem.value = null
  form.value = { name: '', email: '', password: '', role: 'support', is_active: true }
  dialog.value = true
}

const openEdit = item => {
  editItem.value = item
  form.value = {
    name: item.name,
    email: item.email,
    password: '',
    role: item.role,
    is_active: item.is_active,
  }
  dialog.value = true
}

const save = async () => {
  try {
    if (editItem.value) {
      await $api(`/platform/staff/${editItem.value.id}`, {
        method: 'PATCH',
        body: {
          name: form.value.name,
          email: form.value.email,
          role: form.value.role,
          is_active: form.value.is_active,
          password: form.value.password || undefined,
        },
      })
    } else {
      await $api('/platform/staff', {
        method: 'POST',
        body: form.value,
      })
    }

    dialog.value = false
    await fetchStaff()
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchStaff)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-4">
        <div>
          <h4 class="text-h4 mb-1">
            کاربران پلتفرم
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            ایجاد پشتیبان و کاربر اداری — جدا از کاربران CRM
          </p>
        </div>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          کاربر جدید
        </VBtn>
      </div>

      <VCard>
        <VDataTable
          :headers="[
            { title: 'نام', key: 'name' },
            { title: 'ایمیل', key: 'email' },
            { title: 'نقش', key: 'role_label' },
            { title: 'وضعیت', key: 'is_active' },
            { title: '', key: 'actions', sortable: false },
          ]"
          :items="staff"
          :loading="loading"
          hide-default-footer
        >
          <template #item.is_active="{ item }">
            <VChip
              size="small"
              :color="item.is_active ? 'success' : 'error'"
              variant="tonal"
            >
              {{ item.is_active ? 'فعال' : 'غیرفعال' }}
            </VChip>
          </template>
          <template #item.actions="{ item }">
            <IconBtn @click="openEdit(item)">
              <VIcon icon="tabler-edit" />
            </IconBtn>
          </template>
        </VDataTable>
      </VCard>
    </VCol>
  </VRow>

  <VDialog
    v-model="dialog"
    max-width="520"
  >
    <VCard :title="editItem ? 'ویرایش کاربر' : 'کاربر جدید'">
      <VCardText class="d-flex flex-column gap-4">
        <AppTextField
          v-model="form.name"
          label="نام"
        />
        <AppTextField
          v-model="form.email"
          label="ایمیل"
          type="email"
        />
        <AppTextField
          v-model="form.password"
          :label="editItem ? 'رمز جدید (اختیاری)' : 'رمز عبور'"
          type="password"
        />
        <AppSelect
          v-model="form.role"
          :items="roleItems"
          label="نقش"
        />
        <VSwitch
          v-model="form.is_active"
          label="فعال"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="text"
          @click="dialog = false"
        >
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          @click="save"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
