<script setup>
import { useDisplay } from 'vuetify'

const props = defineProps({
  jyear: { type: Number, required: true },
  jmonth: { type: Number, required: true },
})

const emit = defineEmits(['loaded'])

const { smAndDown } = useDisplay()

const loading = ref(true)
const savingId = ref(null)
const data = ref({
  rows: [],
  can_manage_manager_targets: false,
  can_manage_user_targets: false,
  empty_hint: null,
})
const editDialog = ref(false)
const editRow = ref(null)
const editForm = ref({
  revenue_target: 0,
  deals_target: null,
  notes: '',
})

const { moment } = useJalaliDate()

const monthLabel = computed(() =>
  moment(`${props.jyear}/${props.jmonth}/1`, 'jYYYY/jM/jD').format('jMMMM jYYYY'))

const helperText = computed(() => {
  if (data.value.can_manage_manager_targets)
    return 'تارگت مدیر فروش را برای این ماه تعیین کنید. فروش واقعی = جمع فروش تیم فروش.'

  if (data.value.can_manage_user_targets)
    return 'تارگت هر نیروی فروش را برای این ماه تعیین کنید.'

  return 'مقایسه تارگت با فروش واقعی (معاملات برنده).'
})

const canManageAny = computed(() =>
  data.value.can_manage_manager_targets || data.value.can_manage_user_targets)

const hasRows = computed(() => Boolean(data.value.rows?.length))

const emptyHintMessage = computed(() => {
  const hint = data.value.empty_hint

  if (hint === 'no_sales_manager')
    return 'هنوز کاربری با نقش «مدیر فروش» در مجموعه ندارید. ابتدا یک مدیر فروش تعریف کنید، سپس تارگت او را تنظیم کنید.'

  if (hint === 'no_sales_reps')
    return 'هیچ نیروی فروشی در تیم ثبت نشده است. ابتدا نیروهای فروش را به مجموعه اضافه کنید.'

  if (hint === 'no_accessible_rows')
    return 'ردیفی برای نمایش یا ویرایش تارگت در دسترس شما نیست.'

  return 'تارگتی برای این دوره ثبت نشده است.'
})

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const roleLabel = level => {
  if (level === 'manager')
    return 'مدیر فروش'

  return 'نیروی فروش'
}

const roleColor = level => {
  if (level === 'manager')
    return 'info'

  return 'primary'
}

const progressColor = progress => {
  if (progress == null)
    return 'secondary'

  if (progress >= 100)
    return 'success'

  if (progress >= 50)
    return 'primary'

  return 'warning'
}

const editButtonLabel = row =>
  row.revenue_target > 0 || row.deals_target ? 'ویرایش تارگت' : 'تعیین تارگت'

const fetchTargets = async () => {
  loading.value = true
  try {
    data.value = await $api('/sales-targets', {
      query: { jyear: props.jyear, jmonth: props.jmonth },
    })
    emit('loaded', data.value)
  } finally {
    loading.value = false
  }
}

const openEdit = row => {
  if (!row.can_edit)
    return

  editRow.value = row
  editForm.value = {
    revenue_target: row.revenue_target ?? 0,
    deals_target: row.deals_target ?? null,
    notes: row.notes ?? '',
  }
  editDialog.value = true
}

const saveTarget = async () => {
  if (!editRow.value)
    return

  savingId.value = editRow.value.user_id
  try {
    await $api('/sales-targets', {
      method: 'POST',
      body: {
        scope: 'user',
        user_id: editRow.value.user_id,
        jyear: props.jyear,
        jmonth: props.jmonth,
        revenue_target: Number(editForm.value.revenue_target) || 0,
        deals_target: editForm.value.deals_target ? Number(editForm.value.deals_target) : null,
        notes: editForm.value.notes?.trim() || null,
      },
    })
    editDialog.value = false
    await fetchTargets()
  } finally {
    savingId.value = null
  }
}

watch(() => [props.jyear, props.jmonth], fetchTargets, { immediate: true })
</script>

<template>
  <VCard class="sales-targets-panel">
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="primary"
          variant="tonal"
          size="40"
          rounded
        >
          <VIcon
            icon="tabler-table"
            size="20"
          />
        </VAvatar>
      </template>
      <VCardTitle>جدول تارگت‌ها</VCardTitle>
      <VCardSubtitle>
        {{ monthLabel }} — {{ helperText }}
      </VCardSubtitle>
    </VCardItem>

    <VCardText class="sales-targets-panel__body">
      <VProgressLinear
        v-if="loading"
        indeterminate
        class="mb-4"
      />

      <template v-else>
        <VAlert
          v-if="!hasRows"
          :type="canManageAny ? 'info' : 'warning'"
          variant="tonal"
          class="mb-0"
        >
          {{ emptyHintMessage }}
          <div
            v-if="data.empty_hint === 'no_sales_manager' || data.empty_hint === 'no_sales_reps'"
            class="mt-3"
          >
            <VBtn
              size="small"
              color="primary"
              variant="tonal"
              :to="{ name: 'apps-crm-users' }"
            >
              مدیریت کاربران و دعوت
            </VBtn>
          </div>
        </VAlert>

        <!-- Mobile card list -->
        <div
          v-else-if="smAndDown"
          class="sales-targets-mobile"
        >
          <VCard
            v-for="row in data.rows"
            :key="`${row.scope}-${row.user_id ?? 'team'}`"
            variant="outlined"
            class="sales-targets-mobile-card mb-3"
          >
            <VCardText>
              <div class="d-flex align-start justify-space-between gap-2 mb-3">
                <div class="min-w-0">
                  <div class="font-weight-medium text-body-1 text-truncate">
                    {{ row.label }}
                  </div>
                  <VChip
                    size="small"
                    :color="roleColor(row.target_level)"
                    variant="tonal"
                    class="mt-1"
                  >
                    {{ roleLabel(row.target_level) }}
                  </VChip>
                </div>
                <VChip
                  v-if="row.revenue_progress != null"
                  size="small"
                  :color="progressColor(row.revenue_progress)"
                  variant="tonal"
                >
                  {{ row.revenue_progress.toLocaleString('fa-IR') }}٪
                </VChip>
              </div>

              <div class="sales-targets-mobile-stats mb-3">
                <div class="sales-targets-mobile-stat">
                  <span class="text-caption text-medium-emphasis">تارگت درآمد</span>
                  <span class="font-weight-medium">{{ formatMoney(row.revenue_target) }}</span>
                </div>
                <div class="sales-targets-mobile-stat">
                  <span class="text-caption text-medium-emphasis">فروش واقعی</span>
                  <span class="font-weight-medium">{{ formatMoney(row.actual_revenue) }}</span>
                </div>
                <div class="sales-targets-mobile-stat">
                  <span class="text-caption text-medium-emphasis">تارگت تعداد</span>
                  <span class="font-weight-medium">{{ row.deals_target?.toLocaleString('fa-IR') ?? '—' }}</span>
                </div>
                <div class="sales-targets-mobile-stat">
                  <span class="text-caption text-medium-emphasis">تحقق</span>
                  <span class="font-weight-medium">{{ row.actual_deals?.toLocaleString('fa-IR') ?? 0 }}</span>
                </div>
              </div>

              <VProgressLinear
                v-if="row.revenue_progress != null"
                :model-value="row.revenue_progress"
                :color="progressColor(row.revenue_progress)"
                height="8"
                rounded
                class="mb-3"
              />

              <div v-if="canManageAny">
                <VBtn
                  v-if="row.can_edit"
                  block
                  variant="tonal"
                  color="primary"
                  :loading="savingId === row.user_id"
                  prepend-icon="tabler-edit"
                  @click="openEdit(row)"
                >
                  {{ editButtonLabel(row) }}
                </VBtn>
                <div
                  v-else
                  class="text-caption text-medium-emphasis text-center py-2"
                >
                  فقط مشاهده
                </div>
              </div>
            </VCardText>
          </VCard>
        </div>

        <!-- Desktop table -->
        <div
          v-else
          class="sales-targets-table-wrap"
        >
          <VTable
            density="comfortable"
            class="sales-targets-table"
          >
            <thead>
              <tr>
                <th>عنوان</th>
                <th>نقش</th>
                <th>تارگت درآمد</th>
                <th>فروش واقعی</th>
                <th>پیشرفت</th>
                <th>تارگت تعداد</th>
                <th>تحقق</th>
                <th v-if="canManageAny">
                  عملیات
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in data.rows"
                :key="`${row.scope}-${row.user_id ?? 'team'}`"
              >
                <td class="font-weight-medium">
                  {{ row.label }}
                </td>
                <td>
                  <VChip
                    size="small"
                    :color="roleColor(row.target_level)"
                    variant="tonal"
                  >
                    {{ roleLabel(row.target_level) }}
                  </VChip>
                </td>
                <td>{{ formatMoney(row.revenue_target) }}</td>
                <td>{{ formatMoney(row.actual_revenue) }}</td>
                <td class="sales-targets-progress-cell">
                  <div class="d-flex align-center gap-2">
                    <VProgressLinear
                      v-if="row.revenue_progress != null"
                      :model-value="row.revenue_progress"
                      :color="progressColor(row.revenue_progress)"
                      height="8"
                      rounded
                      class="flex-grow-1"
                    />
                    <span
                      v-if="row.revenue_progress != null"
                      class="text-caption text-no-wrap"
                    >
                      {{ row.revenue_progress.toLocaleString('fa-IR') }}٪
                    </span>
                    <span
                      v-else
                      class="text-medium-emphasis"
                    >—</span>
                  </div>
                </td>
                <td>{{ row.deals_target?.toLocaleString('fa-IR') ?? '—' }}</td>
                <td>{{ row.actual_deals?.toLocaleString('fa-IR') ?? 0 }}</td>
                <td v-if="canManageAny">
                  <VBtn
                    v-if="row.can_edit"
                    size="small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="tabler-edit"
                    :loading="savingId === row.user_id"
                    @click="openEdit(row)"
                  >
                    {{ editButtonLabel(row) }}
                  </VBtn>
                  <span
                    v-else
                    class="text-caption text-medium-emphasis"
                  >فقط مشاهده</span>
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>
      </template>
    </VCardText>
  </VCard>

  <VDialog
    v-model="editDialog"
    :fullscreen="smAndDown"
    :max-width="smAndDown ? undefined : 520"
    scrollable
  >
    <VCard :title="`تارگت — ${editRow?.label ?? ''}`">
      <VDivider />
      <VCardText class="sales-targets-dialog-body">
        <AppTextField
          v-model.number="editForm.revenue_target"
          label="تارگت درآمد (ریال)"
          type="number"
          min="0"
          class="mb-4"
        />
        <AppTextField
          v-model.number="editForm.deals_target"
          label="تارگت تعداد معامله (اختیاری)"
          type="number"
          min="0"
          class="mb-4"
        />
        <AppTextarea
          v-model="editForm.notes"
          label="یادداشت"
          rows="3"
        />
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4">
        <VSpacer v-if="!smAndDown" />
        <VBtn
          variant="tonal"
          :block="smAndDown"
          @click="editDialog = false"
        >
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :block="smAndDown"
          :loading="Boolean(savingId)"
          @click="saveTarget"
        >
          ذخیره تارگت
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.sales-targets-panel {
  max-inline-size: 100%;
  overflow: hidden;
}

.sales-targets-panel__body {
  max-inline-size: 100%;
}

.sales-targets-mobile {
  max-inline-size: 100%;
  touch-action: pan-y;
  -webkit-overflow-scrolling: touch;
}

.sales-targets-mobile-card:last-child {
  margin-block-end: 0 !important;
}

.sales-targets-mobile-stats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.sales-targets-mobile-stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.sales-targets-table-wrap {
  max-inline-size: 100%;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  -webkit-overflow-scrolling: touch;
  touch-action: pan-x pan-y;
  border-radius: 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.sales-targets-table {
  min-inline-size: 720px;
}

.sales-targets-table :deep(th) {
  white-space: nowrap;
  font-weight: 600;
}

.sales-targets-progress-cell {
  min-inline-size: 160px;
}

.sales-targets-dialog-body {
  padding-block: 20px !important;
}
</style>
