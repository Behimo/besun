<script setup>
import CrmModulePlaceholder from '@/components/CrmModulePlaceholder.vue'
import CrmAutomationRuleDialog from '@/components/crm/CrmAutomationRuleDialog.vue'
import { useCrmAutomation } from '@/composables/useCrmAutomation'

definePage({
  meta: {
    action: 'read',
    subject: 'Automation',
  },
})

const { hasModule, hasCoreModule, userData } = useAppShell()
const { formatDateTime } = useJalaliDate()
const {
  loading,
  error,
  fetchDashboard,
  fetchMeta,
  fetchRules,
  deleteRule,
  toggleRule,
  fetchRuns,
} = useCrmAutomation()

const hasAutomationModule = computed(() => hasModule('mod-automation'))
const canManage = computed(() =>
  userData.value?.permissions?.includes('automation.manage') || userData.value?.tenant?.isOwner,
)

const tab = ref('dashboard')
const dashboard = ref(null)
const meta = ref(null)
const rules = ref([])
const runs = ref({ data: [] })
const ruleDialog = ref(false)
const editingRule = ref(null)
const runStatusFilter = ref(null)
const metaLoading = ref(false)
const metaError = ref('')

const triggerLabel = event => meta.value?.triggers?.[event]?.label ?? event

const statusLabel = {
  success: 'موفق',
  skipped: 'رد شده',
  failed: 'ناموفق',
}

const statusColor = {
  success: 'success',
  skipped: 'warning',
  failed: 'error',
}

const loadDashboard = async () => {
  try {
    dashboard.value = await fetchDashboard()
  } catch {
    dashboard.value = null
  }
}

const loadMeta = async () => {
  metaLoading.value = true
  metaError.value = ''
  try {
    meta.value = await fetchMeta()
  } catch (e) {
    meta.value = null
    metaError.value = e?.data?.message || 'خطا در بارگذاری اطلاعات فرم'
  } finally {
    metaLoading.value = false
  }
}

const loadRules = async () => {
  rules.value = await fetchRules()
}

const loadRuns = async () => {
  runs.value = await fetchRuns(1, runStatusFilter.value)
}

const ensureMetaAndOpen = async (rule = null) => {
  if (!meta.value && !metaLoading.value)
    await loadMeta()

  if (!meta.value) {
    metaError.value = metaError.value || 'اطلاعات فرم بارگذاری نشد. دوباره تلاش کنید.'

    return
  }

  editingRule.value = rule
  ruleDialog.value = true
}

const openCreate = () => ensureMetaAndOpen()

const openEdit = rule => ensureMetaAndOpen(rule)

const onRuleSaved = async () => {
  await loadRules()
  await loadDashboard()
}

const onToggle = async rule => {
  await toggleRule(rule.id)
  await loadRules()
  await loadDashboard()
}

const onDelete = async rule => {
  if (!confirm(`قانون «${rule.name}» حذف شود؟`))
    return

  await deleteRule(rule.id)
  await loadRules()
  await loadDashboard()
}

watch(runStatusFilter, () => {
  loadRuns()
})

onMounted(async () => {
  if (!hasAutomationModule.value || !hasCoreModule.value)
    return

  await Promise.all([loadMeta(), loadDashboard(), loadRules(), loadRuns()])
})
</script>

<template>
  <div>
    <CrmModulePlaceholder
      v-if="!hasAutomationModule"
      title="اتوماسیون"
      icon="tabler-robot"
      description="گردش‌کار خودکار: رویداد، شرط، اقدام."
      module-slug="mod-automation"
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
            اتوماسیون
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            قوانین خودکار: رویداد → شرط → اقدام
          </p>
        </div>
        <VBtn
          v-if="canManage"
          color="primary"
          prepend-icon="tabler-plus"
          :loading="metaLoading"
          @click="openCreate"
        >
          قانون جدید
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
        v-if="metaError"
        type="warning"
        variant="tonal"
        class="mb-4"
      >
        {{ metaError }}
        <template #append>
          <VBtn
            size="small"
            variant="text"
            @click="loadMeta"
          >
            تلاش مجدد
          </VBtn>
        </template>
      </VAlert>

      <VAlert
        v-if="meta && !meta.has_sms_module"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        برای اقدام «ارسال پیامک خودکار»، ماژول
        <RouterLink :to="{ name: 'apps-tenant-modules' }">
          پنل پیامک
        </RouterLink>
        را از فروشگاه ماژول فعال کنید.
      </VAlert>

      <VCard :loading="loading">
        <VTabs v-model="tab">
          <VTab value="dashboard">
            داشبورد
          </VTab>
          <VTab value="rules">
            قوانین
          </VTab>
          <VTab value="runs">
            لاگ اجرا
          </VTab>
        </VTabs>

        <VCardText>
          <VWindow v-model="tab">
            <VWindowItem value="dashboard">
              <VRow v-if="dashboard">
                <VCol cols="12" md="3">
                  <VCard variant="tonal">
                    <VCardText>
                      <div class="text-body-2 text-medium-emphasis">
                        قوانین فعال
                      </div>
                      <div class="text-h5">
                        {{ dashboard.active_rules }} / {{ dashboard.total_rules }}
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="3">
                  <VCard variant="tonal">
                    <VCardText>
                      <div class="text-body-2 text-medium-emphasis">
                        اجرا (۲۴ ساعت)
                      </div>
                      <div class="text-h5">
                        {{ dashboard.runs_last_24h }}
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="3">
                  <VCard variant="tonal" color="success">
                    <VCardText>
                      <div class="text-body-2 text-medium-emphasis">
                        موفق
                      </div>
                      <div class="text-h5">
                        {{ dashboard.success_last_24h }}
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="3">
                  <VCard variant="tonal" color="error">
                    <VCardText>
                      <div class="text-body-2 text-medium-emphasis">
                        ناموفق
                      </div>
                      <div class="text-h5">
                        {{ dashboard.failed_last_24h }}
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>

              <div
                v-if="dashboard?.recent_errors?.length"
                class="mt-4"
              >
                <h6 class="text-h6 mb-2">
                  آخرین خطاها
                </h6>
                <VList density="compact">
                  <VListItem
                    v-for="run in dashboard.recent_errors"
                    :key="run.id"
                    :subtitle="run.error_message"
                  >
                    <template #title>
                      {{ run.rule?.name ?? 'قانون' }} — {{ formatDateTime(run.executed_at) }}
                    </template>
                  </VListItem>
                </VList>
              </div>
            </VWindowItem>

            <VWindowItem value="rules">
              <VDataTable
                :items="rules"
                :headers="[
                  { title: 'نام', key: 'name' },
                  { title: 'رویداد', key: 'trigger_event' },
                  { title: 'اولویت', key: 'priority' },
                  { title: 'اجرا', key: 'run_count' },
                  { title: 'فعال', key: 'is_active' },
                  { title: 'عملیات', key: 'actions', sortable: false },
                ]"
                :items-per-page="15"
                no-data-text="قانونی ثبت نشده است."
              >
                <template #item.trigger_event="{ item }">
                  {{ triggerLabel(item.trigger_event) }}
                </template>
                <template #item.is_active="{ item }">
                  <VSwitch
                    :model-value="item.is_active"
                    color="primary"
                    density="compact"
                    hide-details
                    :disabled="!canManage"
                    @update:model-value="onToggle(item)"
                  />
                </template>
                <template #item.actions="{ item }">
                  <VBtn
                    v-if="canManage"
                    icon="tabler-edit"
                    variant="text"
                    size="small"
                    @click="openEdit(item)"
                  />
                  <VBtn
                    v-if="canManage"
                    icon="tabler-trash"
                    variant="text"
                    size="small"
                    color="error"
                    @click="onDelete(item)"
                  />
                </template>
              </VDataTable>
            </VWindowItem>

            <VWindowItem value="runs">
              <div class="d-flex gap-2 mb-4">
                <VSelect
                  v-model="runStatusFilter"
                  :items="[
                    { value: null, title: 'همه' },
                    { value: 'success', title: 'موفق' },
                    { value: 'skipped', title: 'رد شده' },
                    { value: 'failed', title: 'ناموفق' },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="وضعیت"
                  density="compact"
                  style="max-width: 200px"
                  clearable
                />
              </div>

              <VDataTable
                :items="runs.data ?? []"
                :headers="[
                  { title: 'قانون', key: 'rule' },
                  { title: 'رویداد', key: 'trigger_event' },
                  { title: 'موجودیت', key: 'entity' },
                  { title: 'وضعیت', key: 'status' },
                  { title: 'زمان', key: 'executed_at' },
                ]"
                :items-per-page="20"
                no-data-text="اجرایی ثبت نشده است."
              >
                <template #item.rule="{ item }">
                  {{ item.rule?.name ?? '—' }}
                </template>
                <template #item.trigger_event="{ item }">
                  {{ triggerLabel(item.trigger_event) }}
                </template>
                <template #item.entity="{ item }">
                  {{ item.entity_type }} #{{ item.entity_id }}
                </template>
                <template #item.status="{ item }">
                  <VChip
                    :color="statusColor[item.status] ?? 'default'"
                    size="small"
                    variant="tonal"
                  >
                    {{ statusLabel[item.status] ?? item.status }}
                  </VChip>
                </template>
                <template #item.executed_at="{ item }">
                  {{ formatDateTime(item.executed_at) }}
                </template>
              </VDataTable>
            </VWindowItem>
          </VWindow>
        </VCardText>
      </VCard>

      <CrmAutomationRuleDialog
        v-model="ruleDialog"
        :rule="editingRule"
        :meta="meta"
        @saved="onRuleSaved"
      />
    </div>
  </div>
</template>
