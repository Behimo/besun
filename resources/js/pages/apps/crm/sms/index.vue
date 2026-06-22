<script setup>
import CrmModulePlaceholder from '@/components/CrmModulePlaceholder.vue'
import { useCrmSms } from '@/composables/useCrmSms'

definePage({
  meta: {
    action: 'read',
    subject: 'Sms',
  },
})

const { hasModule, hasCoreModule, userData } = useAppShell()
const { formatDateTime } = useJalaliDate()
const {
  loading,
  error,
  fetchDashboard,
  fetchMessages,
  fetchTemplates,
  fetchCreditPackages,
  purchaseCredit,
  saveTemplate,
} = useCrmSms()

const hasSmsModule = computed(() => hasModule('mod-sms'))
const canSend = computed(() => userData.value?.permissions?.includes('sms.send'))
const canManage = computed(() => userData.value?.permissions?.includes('sms.manage'))
const canCredit = computed(() => userData.value?.permissions?.includes('sms.credit') || userData.value?.tenant?.isOwner)

const tab = ref('dashboard')
const dashboard = ref(null)
const messages = ref({ data: [] })
const templates = ref([])
const packages = ref([])
const templateDialog = ref(false)
const templateForm = ref({ title: '', body: '' })
const creditLoading = ref(false)

const statusLabel = {
  pending: 'در انتظار',
  queued: 'صف',
  sent: 'ارسال‌شده',
  failed: 'ناموفق',
  partial: 'بخشی',
}

const loadDashboard = async () => {
  try {
    dashboard.value = await fetchDashboard()
  } catch {
    dashboard.value = null
  }
}

const loadMessages = async () => {
  messages.value = await fetchMessages()
}

const loadTemplates = async () => {
  templates.value = await fetchTemplates()
}

const loadPackages = async () => {
  if (!canCredit.value)
    return

  try {
    packages.value = await fetchCreditPackages()
  } catch {
    packages.value = []
  }
}

const submitTemplate = async () => {
  await saveTemplate(templateForm.value)
  templateDialog.value = false
  templateForm.value = { title: '', body: '' }
  await loadTemplates()
}

const buyPackage = async pkg => {
  creditLoading.value = true
  try {
    await purchaseCredit(pkg.id)
    await loadDashboard()
    await loadPackages()
  } finally {
    creditLoading.value = false
  }
}

onMounted(async () => {
  if (!hasSmsModule.value || !hasCoreModule.value)
    return

  await loadDashboard()
  await loadMessages()
  await loadTemplates()
  await loadPackages()
})
</script>

<template>
  <div>
    <CrmModulePlaceholder
      v-if="!hasSmsModule"
      title="پنل پیامک"
      icon="tabler-message"
      description="ارسال پیامک تکی و گروهی به مشتریان و لیدها."
      module-slug="mod-sms"
    />

    <div v-else-if="!hasCoreModule">
      <VCard>
        <VCardText class="text-center pa-8">
          <h4 class="text-h4 mb-2">
            ماژول پایه فعال نیست
          </h4>
          <VBtn
            color="primary"
            :to="{ name: 'apps-tenant-modules' }"
          >
            وضعیت ماژول‌ها
          </VBtn>
        </VCardText>
      </VCard>
    </div>

    <div v-else>
      <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-4">
        <div>
          <h4 class="text-h4 mb-1">
            پنل پیامک
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            ارسال پیامک، تاریخچه و شارژ پنل
          </p>
        </div>
        <VBtn
          v-if="dashboard?.default_from_number"
          variant="tonal"
          color="secondary"
          :to="{ name: 'apps-tenant-settings', query: { tab: 'sms' } }"
        >
          تنظیمات پنل
        </VBtn>
      </div>

      <VAlert
        v-if="error"
        type="error"
        variant="tonal"
        class="mb-4"
      >
        {{ error }}
      </VAlert>

      <VAlert
        v-if="!dashboard && !loading"
        type="warning"
        variant="tonal"
        class="mb-4"
      >
        پنل پیامک فعال نیست. از
        <RouterLink :to="{ name: 'apps-tenant-settings', query: { tab: 'sms' } }">
          تنظیمات مجموعه
        </RouterLink>
        درخواست فعال‌سازی دهید.
      </VAlert>

      <VCard :loading="loading">
        <VTabs v-model="tab">
          <VTab value="dashboard">
            داشبورد
          </VTab>
          <VTab value="history">
            تاریخچه
          </VTab>
          <VTab
            v-if="canManage"
            value="templates"
          >
            قالب‌ها
          </VTab>
          <VTab
            v-if="canCredit"
            value="credit"
          >
            شارژ
          </VTab>
        </VTabs>

        <VCardText>
          <VWindow v-model="tab">
            <VWindowItem value="dashboard">
              <VRow v-if="dashboard">
                <VCol
                  cols="12"
                  md="4"
                >
                  <VCard variant="tonal">
                    <VCardText>
                      <div class="text-body-2 text-medium-emphasis">
                        موجودی
                      </div>
                      <div class="text-h5">
                        {{ Number(dashboard.credit || 0).toLocaleString('fa-IR') }}
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VCard variant="tonal">
                    <VCardText>
                      <div class="text-body-2 text-medium-emphasis">
                        ارسال امروز
                      </div>
                      <div class="text-h5">
                        {{ dashboard.sent_today }}
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VCard variant="tonal">
                    <VCardText>
                      <div class="text-body-2 text-medium-emphasis">
                        ارسال این ماه
                      </div>
                      <div class="text-h5">
                        {{ dashboard.sent_month }}
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
            </VWindowItem>

            <VWindowItem value="history">
              <VTable>
                <thead>
                  <tr>
                    <th>متن</th>
                    <th>گیرندگان</th>
                    <th>وضعیت</th>
                    <th>زمان</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="msg in messages.data"
                    :key="msg.id"
                  >
                    <td>{{ (msg.body || '').slice(0, 60) }}</td>
                    <td>{{ msg.recipients_count }}</td>
                    <td>{{ statusLabel[msg.status] || msg.status }}</td>
                    <td>{{ formatDateTime(msg.created_at) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </VWindowItem>

            <VWindowItem value="templates">
              <div class="d-flex justify-end mb-4">
                <VBtn
                  color="primary"
                  @click="templateDialog = true"
                >
                  قالب جدید
                </VBtn>
              </div>
              <VList>
                <VListItem
                  v-for="tpl in templates"
                  :key="tpl.id"
                  :title="tpl.title"
                  :subtitle="tpl.body"
                />
              </VList>
            </VWindowItem>

            <VWindowItem value="credit">
              <VRow>
                <VCol
                  v-for="pkg in packages"
                  :key="pkg.id"
                  cols="12"
                  md="4"
                >
                  <VCard variant="outlined">
                    <VCardText>
                      <div class="text-h6 mb-2">
                        {{ pkg.name }}
                      </div>
                      <div class="text-body-1 mb-4">
                        {{ Number(pkg.price).toLocaleString('fa-IR') }} ریال
                      </div>
                      <VBtn
                        color="primary"
                        block
                        :loading="creditLoading"
                        @click="buyPackage(pkg)"
                      >
                        خرید شارژ
                      </VBtn>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
            </VWindowItem>
          </VWindow>
        </VCardText>
      </VCard>

      <VDialog
        v-model="templateDialog"
        max-width="480"
      >
        <VCard title="قالب پیامک">
          <VCardText>
            <VTextField
              v-model="templateForm.title"
              label="عنوان"
              class="mb-4"
            />
            <VTextarea
              v-model="templateForm.body"
              label="متن"
              rows="4"
            />
          </VCardText>
          <VCardActions>
            <VSpacer />
            <VBtn
              color="primary"
              @click="submitTemplate"
            >
              ذخیره
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </div>
  </div>
</template>
