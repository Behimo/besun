<script setup>
import CrmProductChips from '@/components/crm/CrmProductChips.vue'

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  meta: { type: String, default: '' },
  amount: { type: [Number, String], default: null },
  badge: { type: String, default: '' },
  badgeColor: { type: String, default: 'primary' },
  score: { type: [Number, String], default: null },
  stageColor: { type: String, default: '#4A0E17' },
  variant: { type: String, default: 'sales' },
  assignee: { type: String, default: '' },
  followUpAt: { type: String, default: '' },
  itemId: { type: [Number, String], default: null },
  products: { type: Array, default: () => [] },
  quoteBadge: { type: Boolean, default: false },
  showProductsAction: { type: Boolean, default: true },
  showConvertAction: { type: Boolean, default: false },
  showHandoffAction: { type: Boolean, default: true },
})

const { followUpMeta } = useFollowUpDatetime()

const followUp = computed(() => followUpMeta(props.followUpAt))

const initials = computed(() => {
  const source = props.assignee || props.subtitle || props.title || '?'

  return source.trim().charAt(0).toUpperCase()
})

const avatarLabel = computed(() => props.assignee || props.subtitle || '')

const emit = defineEmits(['click', 'handoff', 'products', 'convert'])

const formattedAmount = computed(() => {
  if (props.amount == null || props.amount === '')
    return null

  return Number(props.amount).toLocaleString('fa-IR')
})

const hasMeta = computed(() =>
  props.meta
  || followUp.value
  || (props.score != null && props.score !== '')
  || props.quoteBadge)

const hasFooter = computed(() =>
  formattedAmount.value || props.subtitle || props.assignee)
</script>

<template>
  <VCard
    :ripple="false"
    :link="false"
    elevation="0"
    class="kanban-card crm-kanban-card position-relative crm-kanban-card--clickable"
    :style="{ '--card-accent': stageColor }"
    :data-kanban-item-id="itemId"
    @click="emit('click')"
  >
    <div class="crm-kanban-card__accent" />

    <VCardText class="crm-kanban-card__body pa-3 ps-4">
      <div class="crm-kanban-card__header d-flex align-start gap-2">
        <div class="flex-grow-1 overflow-hidden min-w-0">
          <VChip
            v-if="badge"
            size="x-small"
            :color="badgeColor"
            variant="tonal"
            label
            class="font-weight-medium mb-2"
          >
            {{ badge }}
          </VChip>

          <p class="text-body-2 font-weight-semibold text-high-emphasis mb-1 line-clamp-2">
            {{ title }}
          </p>

          <div
            v-if="subtitle"
            class="d-flex align-center gap-1 text-caption text-medium-emphasis"
          >
            <VIcon
              icon="tabler-building"
              size="14"
            />
            <span class="text-truncate">{{ subtitle }}</span>
          </div>

          <div
            v-if="assignee"
            class="d-flex align-center gap-1 text-caption text-medium-emphasis mt-1"
          >
            <VIcon
              icon="tabler-user-check"
              size="14"
            />
            <span class="text-truncate">مسئول: {{ assignee }}</span>
          </div>
        </div>

        <VBtn
          icon="tabler-grip-vertical"
          size="x-small"
          variant="text"
          class="crm-kanban-card__grip flex-shrink-0"
          @click.stop
        />
      </div>

      <div
        class="crm-kanban-card__actions d-flex flex-wrap gap-1 mt-2"
        @click.stop
      >
        <VBtn
          v-if="showProductsAction"
          size="x-small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-package"
          class="crm-kanban-card__action-btn"
          @click.stop="emit('products')"
        >
          محصول
        </VBtn>
        <VBtn
          v-if="showConvertAction"
          size="x-small"
          variant="tonal"
          color="success"
          prepend-icon="tabler-arrow-forward"
          class="crm-kanban-card__action-btn"
          @click.stop="emit('convert')"
        >
          ارجاع
        </VBtn>
        <VBtn
          v-if="showHandoffAction"
          size="x-small"
          variant="tonal"
          color="info"
          prepend-icon="tabler-user-share"
          class="crm-kanban-card__action-btn"
          @click.stop="emit('handoff')"
        >
          واگذاری
        </VBtn>
      </div>

      <div
        v-if="hasMeta"
        class="d-flex align-center flex-wrap gap-1 mt-2"
      >
        <VChip
          v-if="meta"
          size="x-small"
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-map-pin"
          label
        >
          {{ meta }}
        </VChip>
        <VChip
          v-if="followUp"
          size="x-small"
          :color="followUp.overdue ? 'error' : 'info'"
          variant="tonal"
          prepend-icon="tabler-bell"
          label
        >
          {{ followUp.text }}
        </VChip>
        <VChip
          v-if="score != null && score !== ''"
          size="x-small"
          color="warning"
          variant="tonal"
          label
        >
          {{ score }}%
        </VChip>
        <VChip
          v-if="quoteBadge"
          size="x-small"
          color="success"
          variant="tonal"
          prepend-icon="tabler-file-invoice"
          label
        >
          پیش‌فاکتور
        </VChip>
      </div>

      <CrmProductChips
        v-if="products.length"
        :products="products"
        class="mt-2"
      />

      <div
        v-if="hasFooter"
        class="crm-kanban-card__footer d-flex align-center justify-space-between gap-2 mt-2 pt-2"
      >
        <div
          v-if="formattedAmount"
          class="d-flex align-center gap-1 text-body-2 font-weight-semibold text-primary"
        >
          <VIcon
            icon="tabler-currency-dollar"
            size="16"
          />
          {{ formattedAmount }}
          <span class="text-caption font-weight-regular">ریال</span>
        </div>
        <VSpacer v-if="!formattedAmount" />

        <VAvatar
          v-if="avatarLabel"
          size="26"
          :color="stageColor"
          variant="tonal"
        >
          <span class="text-caption font-weight-bold">{{ initials }}</span>
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ avatarLabel }}
          </VTooltip>
        </VAvatar>
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.crm-kanban-card {
  cursor: pointer;
  border-radius: 10px !important;
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.9));
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 2px 6px rgba(var(--v-shadow-key-umbra-opacity), 0.06);
  transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
  overflow: hidden;
  flex: 0 0 auto !important;
  block-size: auto !important;
  min-block-size: 0 !important;
  align-self: flex-start;
  inline-size: 100%;

  &:hover {
    box-shadow: 0 8px 24px rgba(var(--v-shadow-key-umbra-opacity), 0.12);
    border-color: rgba(var(--v-theme-primary), 0.2);
    transform: translateY(-1px);
  }

  &:active,
  &[style*='z-index'] {
    cursor: grabbing !important;
    box-shadow: 0 12px 28px rgba(var(--v-shadow-key-umbra-opacity), 0.18);
  }

  &__accent {
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    inline-size: 4px;
    background: var(--card-accent, rgb(var(--v-theme-primary)));
  }

  &__body {
    display: flex;
    flex-direction: column;
  }

  &__grip {
    cursor: grab;
    opacity: 0.5;

    &:active {
      cursor: grabbing;
    }
  }

  &:hover &__grip {
    opacity: 1;
  }

  &__actions {
    padding-block: 2px;
    border-block-end: 1px dashed rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.55));
    padding-block-end: 8px;
  }

  &__action-btn {
    font-size: 0.7rem !important;
    letter-spacing: 0;
    min-inline-size: 0;
    padding-inline: 8px !important;

    :deep(.v-btn__prepend) {
      margin-inline: 0 4px;
    }

    :deep(.v-icon) {
      font-size: 15px;
      opacity: 1;
    }
  }

  &__footer {
    border-block-start: 1px dashed rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.7));
  }

  &--focused {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
