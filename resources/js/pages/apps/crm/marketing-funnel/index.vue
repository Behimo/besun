<script setup>
definePage({ meta: { action: 'read', subject: 'MarketingFunnel' } })

import CrmConvertLeadDialog from '@/components/crm/CrmConvertLeadDialog.vue'
import CrmEntityProductsDialog from '@/components/crm/CrmEntityProductsDialog.vue'
import CrmPipelineKanban from '@/components/crm/CrmPipelineKanban.vue'
import CrmHandoffDialog from '@/components/crm/CrmHandoffDialog.vue'
import CrmSmsSendDialog from '@/components/crm/CrmSmsSendDialog.vue'

const { hasModule, userData } = useAppShell()
const canSendSms = computed(() => hasModule('mod-sms') && (userData.value?.permissions?.includes('sms.send') || userData.value?.tenant?.isOwner))
const { canAssignLeads } = useCrmLeadPermissions()
const smsDialog = ref(false)
const smsFilters = ref({ audience: 'leads', pipeline_stage_ids: [] })
const smsStageName = ref('')

const openStageSms = stage => {
  smsFilters.value = { audience: 'leads', pipeline_stage_ids: [stage.id] }
  smsStageName.value = stage.name
  smsDialog.value = true
}

const stages = ref([])
const teamUsers = ref([])
const loading = ref(true)
const handoffDialog = ref(false)
const handoffEntity = ref(null)
const productsDialog = ref(false)
const productsEntity = ref(null)
const convertDialog = ref(false)
const convertingLead = ref(null)
const { onKanbanSelect } = useCrmKanbanNavigation()
const { onMove } = useCrmKanbanMove(stages, {
  collectionKey: 'leads',
  stageEndpoint: id => `/leads/${id}/stage`,
  stageBody: targetStageId => ({ marketing_stage_id: targetStageId }),
})

const leadsById = computed(() => {
  const map = {}

  for (const stage of stages.value) {
    for (const lead of stage.leads ?? [])
      map[lead.id] = lead
  }

  return map
})

const totalLeads = computed(() => Object.keys(leadsById.value).length)

const fetchKanban = async () => {
  loading.value = true
  try {
    const res = await $api('/leads?kanban=1')
    stages.value = res.stages ?? []
  } catch (error) {
    console.error('Failed to load marketing kanban', error)
    stages.value = []
  } finally {
    loading.value = false
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

const openHandoff = ({ item }) => {
  handoffEntity.value = item
  handoffDialog.value = true
}

const onHandoffSuccess = fetchKanban

const openProducts = ({ item }) => {
  productsEntity.value = item
  productsDialog.value = true
}

const openConvert = ({ item }) => {
  convertingLead.value = item
  convertDialog.value = true
}

const onProductsSaved = fetchKanban

const onConvertSuccess = fetchKanban

onMounted(async () => {
  await Promise.all([fetchKanban(), fetchTeamUsers()])
})

</script>

<template>
  <div class="marketing-funnel-page">
    <VCard
      class="marketing-funnel-page__hero mb-5 overflow-hidden"
      elevation="0"
    >
      <div class="marketing-funnel-page__hero-bg" />
      <VCardText class="position-relative pa-6">
        <VRow align="center">
          <VCol
            cols="12"
            md="7"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar
                color="info"
                variant="tonal"
                size="46"
                rounded
              >
                <VIcon
                  icon="tabler-layout-kanban"
                  size="24"
                />
              </VAvatar>
              <div>
                <h4 class="text-h4 mb-0">
                  قیف بازاریابی
                </h4>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  {{ totalLeads.toLocaleString('fa-IR') }} لید فعال — جابجایی بین مراحل
                </p>
              </div>
            </div>
          </VCol>
          <VCol
            cols="12"
            md="5"
            class="d-flex flex-wrap justify-md-end gap-2"
          >
            <VBtn
              variant="tonal"
              :to="{ name: 'apps-crm-leads' }"
              prepend-icon="tabler-list"
            >
              لیست لیدها
            </VBtn>
            <VBtn
              color="primary"
              :to="{ name: 'apps-crm-leads' }"
              prepend-icon="tabler-plus"
            >
              لید جدید
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <CrmPipelineKanban
      :stages="stages"
      :items-by-id="leadsById"
      :loading="loading"
      drag-group="marketing-pipeline"
      variant="marketing"
      :show-handoff-action="canAssignLeads"
      @move="onMove"
      @select-item="onKanbanSelect"
      @handoff="openHandoff"
      @products="openProducts"
      @convert="openConvert"
    />

    <CrmEntityProductsDialog
      v-model="productsDialog"
      entity-type="lead"
      :entity="productsEntity"
      @success="onProductsSaved"
    />

    <CrmConvertLeadDialog
      v-model="convertDialog"
      :lead="convertingLead"
      @success="onConvertSuccess"
    />

    <CrmHandoffDialog
      v-model="handoffDialog"
      entity-type="lead"
      :entity="handoffEntity"
      :stages="stages"
      :users="teamUsers"
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
      related-type="marketing_funnel"
    />
  </div>
</template>

<style scoped>
.marketing-funnel-page__hero {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.marketing-funnel-page__hero-bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 100% 0%, rgba(var(--v-theme-info), 0.14), transparent 42%),
    radial-gradient(circle at 0% 100%, rgba(var(--v-theme-warning), 0.08), transparent 40%);
  pointer-events: none;
}
</style>
