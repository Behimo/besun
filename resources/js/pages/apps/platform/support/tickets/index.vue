<script setup>
import { ticketPriorityLabel, ticketStatusLabel } from '@/composables/usePlatformAdmin'

definePage({
  meta: {
    action: 'read',
    subject: 'PlatformSupport',
  },
})

const { formatDateTime } = useJalaliDate()

const loading = ref(false)
const tickets = ref([])
const page = ref(1)
const totalPages = ref(1)
const statusFilter = ref(null)
const createDialog = ref(false)
const detailDialog = ref(false)
const selectedTicket = ref(null)
const messages = ref([])
const newMessage = ref('')
const createForm = ref({
  subject: '',
  description: '',
  priority: 'medium',
  tenant_id: null,
})

const statusItems = [
  { title: 'همه', value: null },
  { title: 'باز', value: 'open' },
  { title: 'در حال بررسی', value: 'in_progress' },
  { title: 'حل‌شده', value: 'resolved' },
  { title: 'بسته', value: 'closed' },
]

const priorityItems = [
  { title: 'کم', value: 'low' },
  { title: 'متوسط', value: 'medium' },
  { title: 'بالا', value: 'high' },
  { title: 'فوری', value: 'urgent' },
]

const headers = [
  { title: '#', key: 'id', width: 70 },
  { title: 'موضوع', key: 'subject' },
  { title: 'مجموعه', key: 'tenant_name' },
  { title: 'اولویت', key: 'priority' },
  { title: 'وضعیت', key: 'status' },
  { title: 'پیام', key: 'messages_count' },
  { title: 'به‌روزرسانی', key: 'updated_at' },
  { title: '', key: 'actions', sortable: false, width: 80 },
]

const fetchTickets = async () => {
  loading.value = true
  try {
    const res = await $api('/platform/support/tickets', {
      query: {
        status: statusFilter.value || undefined,
        page: page.value,
      },
    })

    tickets.value = res.tickets ?? []
    totalPages.value = res.total_pages ?? 1
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const openTicket = async ticket => {
  selectedTicket.value = ticket
  detailDialog.value = true
  try {
    const res = await $api(`/platform/support/tickets/${ticket.id}`)
    selectedTicket.value = res.ticket
    messages.value = res.messages ?? []
  } catch (e) {
    console.error(e)
  }
}

const createTicket = async () => {
  if (!createForm.value.subject.trim())
    return

  try {
    await $api('/platform/support/tickets', {
      method: 'POST',
      body: createForm.value,
    })
    createDialog.value = false
    createForm.value = { subject: '', description: '', priority: 'medium', tenant_id: null }
    await fetchTickets()
  } catch (e) {
    console.error(e)
  }
}

const sendMessage = async () => {
  if (!newMessage.value.trim() || !selectedTicket.value)
    return

  try {
    await $api(`/platform/support/tickets/${selectedTicket.value.id}/messages`, {
      method: 'POST',
      body: { body: newMessage.value },
    })
    newMessage.value = ''
    await openTicket(selectedTicket.value)
    await fetchTickets()
  } catch (e) {
    console.error(e)
  }
}

const updateStatus = async status => {
  if (!selectedTicket.value)
    return

  try {
    await $api(`/platform/support/tickets/${selectedTicket.value.id}`, {
      method: 'PATCH',
      body: { status },
    })
    await openTicket(selectedTicket.value)
    await fetchTickets()
  } catch (e) {
    console.error(e)
  }
}

watch([page, statusFilter], fetchTickets)
onMounted(fetchTickets)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-4">
        <div>
          <h4 class="text-h4 mb-1">
            تیکت‌های پشتیبانی پلتفرم
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            پیگیری درخواست‌های مجموعه‌ها و مشتریان
          </p>
        </div>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="createDialog = true"
        >
          تیکت جدید
        </VBtn>
      </div>

      <VCard>
        <VCardText>
          <AppSelect
            v-model="statusFilter"
            :items="statusItems"
            label="وضعیت"
            density="compact"
            hide-details
            style="max-inline-size: 180px;"
          />
        </VCardText>
        <VDivider />
        <VDataTable
          :headers="headers"
          :items="tickets"
          :loading="loading"
          hide-default-footer
          class="text-no-wrap"
        >
          <template #item.priority="{ item }">
            {{ ticketPriorityLabel(item.priority) }}
          </template>
          <template #item.status="{ item }">
            <VChip
              size="small"
              variant="tonal"
            >
              {{ ticketStatusLabel(item.status) }}
            </VChip>
          </template>
          <template #item.updated_at="{ item }">
            {{ formatDateTime(item.updated_at) }}
          </template>
          <template #item.actions="{ item }">
            <IconBtn @click="openTicket(item)">
              <VIcon icon="tabler-eye" />
            </IconBtn>
          </template>
        </VDataTable>
        <VDivider />
        <VCardText class="d-flex justify-end">
          <VPagination
            v-model="page"
            :length="Math.max(totalPages, 1)"
          />
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <VDialog
    v-model="createDialog"
    max-width="520"
  >
    <VCard title="تیکت جدید">
      <VCardText class="d-flex flex-column gap-4">
        <AppTextField
          v-model="createForm.subject"
          label="موضوع"
        />
        <AppTextarea
          v-model="createForm.description"
          label="توضیحات"
          rows="3"
        />
        <AppSelect
          v-model="createForm.priority"
          :items="priorityItems"
          label="اولویت"
        />
        <AppTextField
          v-model.number="createForm.tenant_id"
          label="شناسه مجموعه (اختیاری)"
          type="number"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="text"
          @click="createDialog = false"
        >
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          @click="createTicket"
        >
          ثبت
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="detailDialog"
    max-width="640"
  >
    <VCard v-if="selectedTicket">
      <VCardTitle>{{ selectedTicket.subject }}</VCardTitle>
      <VCardSubtitle>
        {{ selectedTicket.tenant_name ?? 'بدون مجموعه' }} · {{ ticketStatusLabel(selectedTicket.status) }}
      </VCardSubtitle>
      <VCardText>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <VBtn
            size="small"
            variant="tonal"
            @click="updateStatus('in_progress')"
          >
            در حال بررسی
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            color="success"
            @click="updateStatus('resolved')"
          >
            حل شد
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            @click="updateStatus('closed')"
          >
            بستن
          </VBtn>
        </div>

        <div
          v-for="msg in messages"
          :key="msg.id"
          class="mb-3 pa-3 rounded bg-surface-variant"
        >
          <div class="text-caption mb-1">
            {{ msg.user?.name }} · {{ formatDateTime(msg.created_at) }}
          </div>
          <div>{{ msg.body }}</div>
        </div>

        <AppTextarea
          v-model="newMessage"
          label="پاسخ"
          rows="2"
          class="mt-4"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="primary"
          @click="sendMessage"
        >
          ارسال
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
