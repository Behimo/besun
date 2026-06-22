<script setup>
import CrmKanbanCard from '@/components/crm/CrmKanbanCard.vue'
import CrmKanbanColumn from '@/components/crm/CrmKanbanColumn.vue'

const props = defineProps({
  stages: {
    type: Array,
    default: () => [],
  },
  itemsById: {
    type: Object,
    default: () => ({}),
  },
  loading: {
    type: Boolean,
    default: false,
  },
  dragGroup: {
    type: String,
    default: 'crm-pipeline',
  },
  itemKey: {
    type: String,
    default: 'id',
  },
  variant: {
    type: String,
    default: 'sales',
  },
  showHandoffAction: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['move', 'add-item', 'refresh', 'select-item', 'handoff', 'products', 'convert'])

const columnState = ref({})
const itemStageMap = ref({})
const pendingMoves = ref(new Set())

const rebuildMaps = () => {
  const state = {}
  const stageMap = {}

  for (const stage of props.stages) {
    const items = stage.deals ?? stage.leads ?? []
    const ids = items.map(item => item[props.itemKey])
    state[stage.id] = ids
    ids.forEach(id => {
      stageMap[id] = stage.id
    })
  }

  columnState.value = state
  itemStageMap.value = stageMap
}

watch(() => props.stages, rebuildMaps, { immediate: true, deep: true })

const syncMove = async (itemId, targetStageId) => {
  if (pendingMoves.value.has(itemId))
    return

  if (itemStageMap.value[itemId] === targetStageId)
    return

  pendingMoves.value.add(itemId)
  try {
    emit('move', { itemId, targetStageId })
    itemStageMap.value[itemId] = targetStageId
  } finally {
    pendingMoves.value.delete(itemId)
  }
}

const onColumnUpdate = ({ stageId, itemIds }) => {
  columnState.value = {
    ...columnState.value,
    [stageId]: itemIds,
  }

  for (const [sid, ids] of Object.entries(columnState.value)) {
    for (const itemId of ids) {
      const numericStageId = Number(sid)
      if (itemStageMap.value[itemId] !== numericStageId)
        syncMove(itemId, numericStageId)
    }
  }
}

const stageStats = computed(() => props.stages.map(stage => {
  const items = stage.deals ?? stage.leads ?? []
  const total = items.reduce((sum, item) => sum + Number(item.amount ?? 0), 0)

  return {
    ...stage,
    total_amount: total,
  }
}))

const totalItems = computed(() => Object.keys(props.itemsById).length)

const totalAmount = computed(() =>
  Object.values(props.itemsById).reduce((sum, item) => sum + Number(item.amount ?? 0), 0))
</script>

<template>
  <div class="crm-pipeline-kanban">
    <div
      v-if="!loading && stages.length"
      class="crm-pipeline-kanban__legend d-flex flex-wrap gap-2 mb-4"
    >
      <VChip
        v-for="stage in stageStats"
        :key="stage.id"
        size="small"
        variant="tonal"
        :style="{
          backgroundColor: `${stage.color || '#4A0E17'}18`,
          color: stage.color || '#4A0E17',
        }"
      >
        <span
          class="crm-pipeline-kanban__legend-dot me-2"
          :style="{ backgroundColor: stage.color || '#4A0E17' }"
        />
        {{ stage.name }}
        <span class="ms-1 opacity-70">({{ columnState[stage.id]?.length ?? 0 }})</span>
      </VChip>
    </div>

    <VOverlay
      :model-value="loading"
      contained
      class="align-center justify-center"
      persistent
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="48"
      />
    </VOverlay>

    <div
      class="crm-kanban-wrapper kanban-main-wrapper d-flex gap-5 pb-3"
      :class="{ 'is-loading': loading }"
    >
      <CrmKanbanColumn
        v-for="stage in stageStats"
        :key="stage.id"
        :stage="stage"
        :item-ids="columnState[stage.id] ?? []"
        :drag-group="dragGroup"
        :variant="variant"
        @update="onColumnUpdate"
        @add-item="emit('add-item', $event)"
      >
        <template #items="{ itemIds, stageColor, stage }">
          <CrmKanbanCard
            v-for="itemId in itemIds"
            :key="itemId"
            :title="itemsById[itemId]?.title ?? itemsById[itemId]?.name ?? '—'"
            :subtitle="itemsById[itemId]?.contact?.name ?? itemsById[itemId]?.company ?? itemsById[itemId]?.email ?? ''"
            :amount="itemsById[itemId]?.amount"
            :badge="itemsById[itemId]?.campaign?.name ?? itemsById[itemId]?.source ?? ''"
            :badge-color="itemsById[itemId]?.campaign ? 'info' : 'secondary'"
            :score="itemsById[itemId]?.score"
            :meta="itemsById[itemId]?.city ?? ''"
            :stage-color="stageColor"
            :variant="variant"
            :assignee="itemsById[itemId]?.assignee?.name ?? ''"
            :follow-up-at="itemsById[itemId]?.next_follow_up_at"
            :products="itemsById[itemId]?.products ?? []"
            :quote-badge="Boolean(itemsById[itemId]?.active_quotes_count)"
            :show-convert-action="variant === 'marketing' && Boolean(stage?.is_ready_for_sales)"
            :show-handoff-action="showHandoffAction"
            :item-id="itemId"
            @click="emit('select-item', { itemId, item: itemsById[itemId], variant })"
            @handoff="emit('handoff', { itemId, item: itemsById[itemId], variant })"
            @products="emit('products', { itemId, item: itemsById[itemId], variant })"
            @convert="emit('convert', { itemId, item: itemsById[itemId], variant })"
          />
        </template>
      </CrmKanbanColumn>
    </div>

    <div
      v-if="!loading && !stages.length"
      class="text-center py-12"
    >
      <VAvatar
        color="primary"
        variant="tonal"
        size="64"
        rounded
        class="mb-4"
      >
        <VIcon
          icon="tabler-layout-kanban"
          size="32"
        />
      </VAvatar>
      <h5 class="text-h5 mb-2">
        مرحله‌ای تعریف نشده
      </h5>
      <p class="text-body-2 text-medium-emphasis">
        از تنظیمات مجموعه، مراحل قیف را پیکربندی کنید.
      </p>
    </div>
  </div>
</template>

<style lang="scss">
@use '@styles/variables/_vuetify.scss' as vuetify;

.crm-pipeline-kanban {
  position: relative;
  min-block-size: 460px;

  &__legend-dot {
    display: inline-block;
    inline-size: 8px;
    block-size: 8px;
    border-radius: 50%;
    vertical-align: middle;
  }
}

.crm-kanban-wrapper.kanban-main-wrapper {
  overflow: auto hidden;
  margin-inline: -0.25rem;
  padding-inline: 0.25rem 0.75rem;
  min-block-size: calc(100vh - 15rem);
  scroll-behavior: smooth;

  &.is-loading {
    opacity: 0.45;
    pointer-events: none;
  }

  &::-webkit-scrollbar {
    block-size: 8px;
  }

  &::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgba(var(--v-theme-on-surface), 0.18);
  }
}

// Drag ghost polish
.crm-kanban-wrapper .kanban-card[style*='z-index'] {
  rotate: 1deg;
  opacity: 0.95;
}
</style>
