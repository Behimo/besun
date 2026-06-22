<script setup>
definePage({ meta: { action: 'read', subject: 'Deals' } })

import CrmEntityProductsDialog from '@/components/crm/CrmEntityProductsDialog.vue'
import CrmPipelineKanban from '@/components/crm/CrmPipelineKanban.vue'
import CrmHandoffDialog from '@/components/crm/CrmHandoffDialog.vue'
import CrmProductPicker from '@/components/crm/CrmProductPicker.vue'
import CrmSmsSendDialog from '@/components/crm/CrmSmsSendDialog.vue'

const { hasModule, userData } = useAppShell()
const canSendSms = computed(() => hasModule('mod-sms') && (userData.value?.permissions?.includes('sms.send') || userData.value?.tenant?.isOwner))
const canAssignDeals = computed(() =>
  Boolean(userData.value?.tenant?.isOwner || userData.value?.permissions?.includes('deals.update')),
)
const smsDialog = ref(false)
const smsFilters = ref({ audience: 'deals', pipeline_stage_ids: [] })
const smsStageName = ref('')

const openStageSms = stage => {
  smsFilters.value = { audience: 'deals', pipeline_stage_ids: [stage.id] }
  smsStageName.value = stage.name
  smsDialog.value = true
}

const stages = ref([])
const contacts = ref([])
const teamUsers = ref([])
const loading = ref(true)
const dialog = ref(false)
const handoffDialog = ref(false)
const handoffEntity = ref(null)
const handoffPreset = ref('assign')
const productsDialog = ref(false)
const productsEntity = ref(null)

const route = useRoute()

const form = ref({
  title: '',
  amount: 0,
  contact_id: null,
  pipeline_stage_id: null,
  next_follow_up_date: '',
  next_follow_up_time: '09:00',
  products: [],
})

const { mergeDatetime } = useFollowUpDatetime()
const { onKanbanSelect } = useCrmKanbanNavigation()
const { onMove } = useCrmKanbanMove(stages, {
  collectionKey: 'deals',
  stageEndpoint: id => `/deals/${id}/stage`,
  stageBody: targetStageId => ({ pipeline_stage_id: targetStageId }),
})

const dealsById = computed(() => {
  const map = {}

  for (const stage of stages.value) {
    for (const deal of stage.deals ?? [])
      map[deal.id] = deal
  }

  return map
})

const totalPipeline = computed(() =>
  Object.values(dealsById.value).reduce((sum, d) => sum + Number(d.amount ?? 0), 0))

const totalDeals = computed(() => Object.keys(dealsById.value).length)

const fetchKanban = async () => {
  loading.value = true
  try {
    const res = await $api('/deals?kanban=1')
    stages.value = res.stages ?? []
    if (stages.value.length && !form.value.pipeline_stage_id)
      form.value.pipeline_stage_id = stages.value[0].id
  } finally {
    loading.value = false
  }
}

const fetchContacts = async () => {
  try {
    const res = await $api('/contacts', { query: { per_page: 200 } })
    contacts.value = res.data ?? res
  } catch {
    contacts.value = []
  }
}

const fetchTeamUsers = async () => {
  try {
    const res = await $api('/users')
    teamUsers.value = res.users ?? []
  } catch {
    teamUsers.value = []
  }
}

const openHandoff = ({ item }, preset = 'assign') => {
  handoffEntity.value = item
  handoffPreset.value = preset
  handoffDialog.value = true
}

const onHandoffSuccess = async () => {
  await fetchKanban()
}

const openProducts = ({ item }) => {
  productsEntity.value = item
  productsDialog.value = true
}

const onProductsSaved = fetchKanban

const openCreate = stage => {
  form.value.pipeline_stage_id = stage?.id ?? stages.value[0]?.id
  dialog.value = true
}

const createDeal = async () => {
  const body = {
    ...form.value,
    next_follow_up_at: mergeDatetime(form.value.next_follow_up_date, form.value.next_follow_up_time),
  }

  delete body.next_follow_up_date
  delete body.next_follow_up_time

  const { products, ...dealBody } = form.value
  const res = await $api('/deals', { method: 'POST', body: dealBody })

  if (products?.length && res.deal?.id) {
    await $api(`/deals/${res.deal.id}/products`, {
      method: 'PUT',
      body: { products },
    })
  }

  dialog.value = false
  form.value = {
    title: '',
    amount: 0,
    contact_id: null,
    pipeline_stage_id: stages.value[0]?.id,
    next_follow_up_date: '',
    next_follow_up_time: '09:00',
    products: [],
  }
  await fetchKanban()
}

onMounted(async () => {
  await Promise.all([fetchKanban(), fetchContacts(), fetchTeamUsers()])
})

watch(() => route.query.focus, async focusId => {
  if (!focusId || loading.value)
    return

  await nextTick()
  const el = document.querySelector(`[data-kanban-item-id="${focusId}"]`)

  el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  el?.classList.add('crm-kanban-card--focused')
}, { immediate: true })
</script>

<template>
  <div class="deals-kanban-page">
    <VCard
      class="deals-kanban-page__hero mb-5 overflow-hidden"
      elevation="0"
    >
      <div class="deals-kanban-page__hero-bg" />
      <VCardText class="position-relative pa-6">
        <VRow align="center">
          <VCol
            cols="12"
            md="7"
          >
            <div class="d-flex align-center gap-3 mb-2">
              <VAvatar
                color="primary"
                variant="tonal"
                size="46"
                rounded
              >
                <VIcon
                  icon="tabler-chart-funnel"
                  size="24"
                />
              </VAvatar>
              <div>
                <h4 class="text-h4 mb-0">
                  قیف فروش
                </h4>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  مدیریت بصری معاملات — بکشید و رها کنید
                </p>
              </div>
            </div>
          </VCol>
          <VCol
            cols="12"
            md="5"
          >
            <div class="d-flex flex-wrap align-center justify-md-end gap-3">
              <div class="deals-kanban-page__stat">
                <span class="text-caption text-medium-emphasis d-block">معاملات</span>
                <span class="text-h5 font-weight-bold">{{ totalDeals.toLocaleString('fa-IR') }}</span>
              </div>
              <div class="deals-kanban-page__stat">
                <span class="text-caption text-medium-emphasis d-block">ارزش کل</span>
                <span class="text-h5 font-weight-bold text-primary">{{ totalPipeline.toLocaleString('fa-IR') }}</span>
              </div>
              <VBtn
                color="primary"
                prepend-icon="tabler-plus"
                @click="openCreate(null)"
              >
                معامله جدید
              </VBtn>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <CrmPipelineKanban
      :stages="stages"
      :items-by-id="dealsById"
      :loading="loading"
      drag-group="sales-pipeline"
      variant="sales"
      :show-handoff-action="canAssignDeals"
      @move="onMove"
      @add-item="openCreate"
      @select-item="onKanbanSelect"
      @handoff="openHandoff"
      @products="openProducts"
    />

    <CrmEntityProductsDialog
      v-model="productsDialog"
      entity-type="deal"
      :entity="productsEntity"
      @success="onProductsSaved"
    />

    <CrmHandoffDialog
      v-model="handoffDialog"
      entity-type="deal"
      :entity="handoffEntity"
      :stages="stages"
      :users="teamUsers"
      :preset="handoffPreset"
      @success="onHandoffSuccess"
    />

    <div
      v-if="canSendSms"
      class="d-flex flex-wrap gap-2 mt-4"
    >
      <VBtn
        v-for="stage in stages"
        :key="stage.id"
        size="small"
        variant="tonal"
        prepend-icon="tabler-message"
        @click="openStageSms(stage)"
      >
        پیامک — {{ stage.name }}
      </VBtn>
    </div>

    <CrmSmsSendDialog
      v-model="smsDialog"
      :title="`ارسال پیامک به مرحله ${smsStageName}`"
      :filters="smsFilters"
      related-type="sales_funnel"
    />

    <VDialog
      v-model="dialog"
      max-width="520"
    >
      <VCard title="معامله جدید">
        <VCardText>
          <AppTextField
            v-model="form.title"
            label="عنوان"
            class="mb-4"
          />
          <AppSelect
            v-model="form.contact_id"
            :items="contacts.map(c => ({ title: c.name, value: c.id }))"
            label="مخاطب"
            clearable
            class="mb-4"
          />
          <AppTextField
            v-model.number="form.amount"
            label="مبلغ"
            type="number"
            class="mb-4"
          />
          <AppSelect
            v-model="form.pipeline_stage_id"
            :items="stages.map(s => ({ title: s.name, value: s.id }))"
            label="مرحله"
            class="mb-4"
          />
          <AppJalaliDateTimePicker
            v-model="form.next_follow_up_date"
            v-model:time="form.next_follow_up_time"
            label="پیگیری بعدی"
            class="mb-4"
          />
          <CrmProductPicker v-model="form.products" />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="dialog = false">
            انصراف
          </VBtn>
          <VBtn
            color="primary"
            @click="createDeal"
          >
            ذخیره
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.deals-kanban-page__hero {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.deals-kanban-page__hero-bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 100% 0%, rgba(var(--v-theme-primary), 0.12), transparent 42%),
    radial-gradient(circle at 0% 100%, rgba(var(--v-theme-success), 0.08), transparent 40%);
  pointer-events: none;
}

.deals-kanban-page__stat {
  padding: 0.5rem 1rem;
  border-radius: 10px;
  background: rgba(var(--v-theme-surface), 0.72);
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.8));
  min-inline-size: 100px;
  text-align: center;
}
</style>
