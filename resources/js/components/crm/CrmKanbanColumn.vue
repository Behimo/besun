<script setup>
import {
  animations,
  handleEnd,
  performTransfer,
} from '@formkit/drag-and-drop'
import { dragAndDrop } from '@formkit/drag-and-drop/vue'

const props = defineProps({
  stage: {
    type: Object,
    required: true,
  },
  itemIds: {
    type: Array,
    default: () => [],
  },
  dragGroup: {
    type: String,
    default: 'crm-pipeline',
  },
  variant: {
    type: String,
    default: 'sales',
  },
})

const emit = defineEmits(['update', 'add-item'])

const listRef = ref()
const localIds = ref([])

const stageColor = computed(() => props.stage.color || '#4A0E17')

const stageTotal = computed(() => {
  if (props.stage.total_amount != null && Number(props.stage.total_amount) > 0)
    return Number(props.stage.total_amount).toLocaleString('fa-IR')

  return null
})

watch(
  () => props.itemIds,
  ids => {
    localIds.value = [...ids]
  },
  { immediate: true, deep: true },
)

const notify = () => {
  emit('update', {
    stageId: props.stage.id,
    itemIds: [...localIds.value],
  })
}

dragAndDrop({
  parent: listRef,
  values: localIds,
  group: props.dragGroup,
  draggable: child => child.classList.contains('crm-kanban-card'),
  dragHandle: '.crm-kanban-card__grip',
  plugins: [animations()],
  performTransfer: (state, data) => {
    performTransfer(state, data)
    notify()
  },
  handleEnd: data => {
    handleEnd(data)
    notify()
  },
})
</script>

<template>
  <div
    class="crm-kanban-board kanban-board"
    :style="{ '--stage-color': stageColor }"
  >
    <div class="crm-kanban-board__shell">
      <div class="crm-kanban-board__accent" />

      <div class="crm-kanban-board-header pb-3 px-3 pt-3">
        <div class="d-flex align-center justify-space-between gap-2">
          <div class="d-flex align-center gap-2 overflow-hidden flex-grow-1">
            <div
              class="crm-kanban-board__dot"
              :style="{ backgroundColor: stageColor }"
            />
            <div class="overflow-hidden">
              <h4 class="text-base font-weight-semibold text-truncate mb-0">
                {{ stage.name }}
              </h4>
              <div
                v-if="stageTotal"
                class="text-caption text-medium-emphasis"
              >
                {{ stageTotal }} ریال
              </div>
            </div>
          </div>
          <VChip
            size="x-small"
            variant="flat"
            class="crm-kanban-board__count"
            :style="{ backgroundColor: `${stageColor}22`, color: stageColor }"
          >
            {{ localIds.length }}
          </VChip>
        </div>
      </div>

      <div
        ref="listRef"
        class="kanban-board-drop-zone crm-kanban-board-drop-zone d-flex flex-column gap-3 px-3 pb-2"
        :class="{ 'is-empty': !localIds.length }"
      >
        <slot
          name="items"
          :item-ids="localIds"
          :stage-color="stageColor"
          :stage="stage"
        />

        <div
          v-if="!localIds.length"
          class="crm-kanban-empty text-center py-6 px-2"
          data-no-dnd
        >
          <VAvatar
            color="secondary"
            variant="tonal"
            size="40"
            rounded
            class="mb-2"
          >
            <VIcon
              icon="tabler-inbox"
              size="20"
            />
          </VAvatar>
          <p class="text-caption text-medium-emphasis mb-0">
            آیتمی در این مرحله نیست
          </p>
        </div>
      </div>

      <div class="crm-kanban-add px-3 pb-3">
        <button
          type="button"
          class="crm-kanban-add__btn"
          @click="emit('add-item', stage)"
        >
          <VIcon
            icon="tabler-plus"
            size="16"
            class="me-1"
          />
          افزودن
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.crm-kanban-board {
  inline-size: 17.5rem;
  min-inline-size: 17.5rem;
  flex-shrink: 0;

  &__shell {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    background: rgba(var(--v-theme-on-surface), 0.03);
    border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1.2));
    min-block-size: 420px;
    max-block-size: calc(100vh - 13rem);
    display: flex;
    flex-direction: column;
  }

  &__accent {
    block-size: 4px;
    background: var(--stage-color, rgb(var(--v-theme-primary)));
  }

  &__dot {
    inline-size: 10px;
    block-size: 10px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  &__count {
    font-weight: 600;
    min-inline-size: 28px;
    justify-content: center;
  }
}

.crm-kanban-board-drop-zone {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  min-block-size: 140px;
  scrollbar-width: thin;
  align-content: flex-start;

  :deep(.crm-kanban-card) {
    flex: 0 0 auto !important;
    block-size: auto !important;
    min-block-size: 0 !important;
    align-self: stretch;
  }

  &.is-empty {
    justify-content: center;
  }
}

.crm-kanban-add__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  inline-size: 100%;
  padding: 0.55rem 0.75rem;
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    color: rgb(var(--v-theme-primary));
    border-color: rgba(var(--v-theme-primary), 0.45);
    background: rgba(var(--v-theme-primary), 0.06);
  }
}
</style>
