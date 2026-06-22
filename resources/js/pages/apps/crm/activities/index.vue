<script setup>
definePage({ meta: { action: 'read', subject: 'Activities' } })

const headers = [
  { title: 'نوع', key: 'type' },
  { title: 'موضوع', key: 'subject' },
  { title: 'کاربر', key: 'user.name' },
  { title: 'مدت (دقیقه)', key: 'duration_minutes' },
  { title: 'زمان', key: 'scheduled_at' },
  { title: 'وضعیت', key: 'status_label' },
  { title: 'عملیات', key: 'actions', sortable: false },
]

const activities = ref([])
const loading = ref(true)
const dialog = ref(false)
const editDialog = ref(false)
const editingActivity = ref(null)
const scheduleFuture = ref(false)

const { mergeDatetime } = useFollowUpDatetime()

const form = ref({
  type: 'note',
  subject: '',
  body: '',
  duration_minutes: null,
  scheduled_date: '',
  scheduled_time: '09:00',
})

const typeLabel = type => ({
  call: 'تماس',
  meeting: 'جلسه',
  note: 'یادداشت',
  email: 'ایمیل',
  sms: 'پیامک',
}[type] ?? type)

const typeItems = [
  { title: 'تماس', value: 'call' },
  { title: 'جلسه', value: 'meeting' },
  { title: 'یادداشت', value: 'note' },
  { title: 'ایمیل', value: 'email' },
  { title: 'پیامک', value: 'sms' },
]

const fetchActivities = async () => {
  loading.value = true
  try {
    const res = await $api('/activities')
    const rows = res.data ?? res

    activities.value = rows.map(row => ({
      ...row,
      status_label: row.scheduled_at && !row.happened_at
        ? 'برنامه‌ریزی‌شده'
        : 'ثبت‌شده',
      scheduled_at: row.scheduled_at || row.happened_at,
    }))
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  form.value = {
    type: 'note',
    subject: '',
    body: '',
    duration_minutes: null,
    scheduled_date: '',
    scheduled_time: '09:00',
  }
  scheduleFuture.value = false
}

const saveActivity = async () => {
  const body = {
    type: form.value.type,
    subject: form.value.subject,
    body: form.value.body,
    duration_minutes: form.value.duration_minutes || null,
  }

  if (scheduleFuture.value && form.value.scheduled_date) {
    body.scheduled_at = mergeDatetime(form.value.scheduled_date, form.value.scheduled_time)
  }

  await $api('/activities', { method: 'POST', body })
  dialog.value = false
  resetForm()
  await fetchActivities()
}

const openEdit = item => {
  editingActivity.value = item
  form.value = {
    type: item.type ?? 'note',
    subject: item.subject ?? '',
    body: item.body ?? '',
    duration_minutes: item.duration_minutes ?? null,
    scheduled_date: '',
    scheduled_time: '09:00',
  }
  editDialog.value = true
}

const updateActivity = async () => {
  if (!editingActivity.value?.id)
    return

  await $api(`/activities/${editingActivity.value.id}`, {
    method: 'PATCH',
    body: {
      type: form.value.type,
      subject: form.value.subject,
      body: form.value.body,
      duration_minutes: form.value.duration_minutes || null,
    },
  })
  editDialog.value = false
  editingActivity.value = null
  resetForm()
  await fetchActivities()
}

const formatActivityTime = val => {
  if (!val)
    return '—'

  return useJalaliDate().formatDateTime(val)
}

onMounted(fetchActivities)
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4">
      <h5 class="text-h5">
        فعالیت‌ها
      </h5>
      <VBtn
        prepend-icon="tabler-plus"
        @click="dialog = true"
      >
        فعالیت جدید
      </VBtn>
    </VCardText>
    <VDataTable
      :headers="headers"
      :items="activities"
      :loading="loading"
    >
      <template #item.type="{ item }">
        {{ typeLabel(item.type) }}
      </template>
      <template #item.duration_minutes="{ item }">
        {{ item.duration_minutes ? Number(item.duration_minutes).toLocaleString('fa-IR') : '—' }}
      </template>
      <template #item.scheduled_at="{ item }">
        {{ formatActivityTime(item.scheduled_at) }}
      </template>
      <template #item.actions="{ item }">
        <IconBtn @click="openEdit(item)">
          <VIcon icon="tabler-edit" />
        </IconBtn>
      </template>
    </VDataTable>
  </VCard>

  <VDialog
    v-model="dialog"
    max-width="520"
  >
    <VCard title="فعالیت جدید">
      <VCardText>
        <AppSelect
          v-model="form.type"
          :items="typeItems"
          label="نوع"
          class="mb-4"
        />
        <AppTextField
          v-model="form.subject"
          label="موضوع"
          class="mb-4"
        />
        <AppTextarea
          v-model="form.body"
          label="متن"
          class="mb-4"
        />
        <AppTextField
          v-model.number="form.duration_minutes"
          label="مدت زمان (دقیقه)"
          type="number"
          min="0"
          class="mb-4"
        />
        <VSwitch
          v-model="scheduleFuture"
          label="زمان‌بندی برای آینده"
          color="primary"
          hide-details
          class="mb-2"
        />
        <AppJalaliDateTimePicker
          v-if="scheduleFuture"
          v-model="form.scheduled_date"
          v-model:time="form.scheduled_time"
          label="زمان برنامه‌ریزی"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="dialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          @click="saveActivity"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="editDialog"
    max-width="520"
  >
    <VCard title="ویرایش فعالیت">
      <VCardText>
        <AppSelect
          v-model="form.type"
          :items="typeItems"
          label="نوع"
          class="mb-4"
        />
        <AppTextField
          v-model="form.subject"
          label="موضوع"
          class="mb-4"
        />
        <AppTextarea
          v-model="form.body"
          label="متن"
          class="mb-4"
        />
        <AppTextField
          v-model.number="form.duration_minutes"
          label="مدت زمان (دقیقه)"
          type="number"
          min="0"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="editDialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          @click="updateActivity"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
