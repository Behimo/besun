<script setup>
import {
  formatRial,
  previewTenantSubscription,
  purchaseTenantSubscription,
  seatPriceForPeriod,
  SUBSCRIPTION_PERIODS,
} from '@/composables/useTenantSubscription'

const props = defineProps({
  tenant: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['purchased', 'close'])

const model = defineModel({ type: Boolean, default: false })

const { applyAuthPayload } = useAppShell()

const modules = ref([])
const period = ref('monthly')
const seatCount = ref(5)
const selected = ref([])
const loading = ref(false)
const purchasing = ref(false)
const preview = ref(null)
const error = ref('')

const coreModule = computed(() => modules.value.find(m => m.is_core))

const estimatedLine = computed(() => {
  const mod = coreModule.value
  if (! mod)
    return ''

  const perSeat = seatPriceForPeriod(mod, period.value)

  return `${seatCount.value} × ${formatRial(perSeat)}`
})

const fetchCatalog = async () => {
  loading.value = true
  error.value = ''

  try {
    const res = await $api('/modules/catalog')
    modules.value = res.modules ?? []
    const core = modules.value.find(m => m.is_core)
    if (core)
      selected.value = [core.id]
  } catch (e) {
    error.value = 'خطا در بارگذاری کاتالوگ ماژول‌ها'
    console.error(e)
  } finally {
    loading.value = false
  }
}

const loadPreview = async () => {
  if (! props.tenant?.id || ! selected.value.length)
    return

  try {
    const res = await previewTenantSubscription(props.tenant.id, {
      modules: selected.value.map(id => ({ module_id: id, period: period.value })),
      seat_count: seatCount.value,
    })
    preview.value = res.preview
    error.value = ''
  } catch (e) {
    preview.value = null
    error.value = e?.data?.message || 'خطا در محاسبه مبلغ'
  }
}

watch([model, () => props.tenant?.id], ([open]) => {
  if (open) {
    seatCount.value = Math.max(props.tenant?.seats_used || 1, 1)
    fetchCatalog()
  }
})

watch([selected, period, seatCount], loadPreview, { deep: true })

const purchase = async () => {
  if (! props.tenant?.id)
    return

  purchasing.value = true
  error.value = ''

  try {
    const res = await purchaseTenantSubscription(props.tenant.id, {
      modules: selected.value.map(id => ({ module_id: id, period: period.value })),
      seat_count: seatCount.value,
    })

    applyAuthPayload(res)
    emit('purchased', res)
    model.value = false
  } catch (e) {
    error.value = e?.data?.message || 'خطا در پرداخت'
  } finally {
    purchasing.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="model"
    max-width="640"
    scrollable
    @after-leave="emit('close')"
  >
    <VCard :loading="loading">
      <VCardTitle class="d-flex align-center gap-2 pa-6 pb-4">
        <VAvatar
          color="primary"
          variant="tonal"
          rounded
        >
          <VIcon icon="tabler-building-store" />
        </VAvatar>
        <div>
          <div class="text-h5">
            فعال‌سازی مجموعه
          </div>
          <div
            v-if="tenant"
            class="text-body-2 text-medium-emphasis"
          >
            {{ tenant.name }}
          </div>
        </div>
        <VSpacer />
        <IconBtn @click="model = false">
          <VIcon icon="tabler-x" />
        </IconBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ error }}
        </VAlert>

        <p
          v-if="coreModule"
          class="text-body-2 text-medium-emphasis mb-4"
        >
          {{ coreModule.description }}
        </p>

        <VRow>
          <VCol cols="12">
            <div class="text-subtitle-2 mb-2">
              دوره اشتراک
            </div>
            <VBtnToggle
              v-model="period"
              mandatory
              divided
              color="primary"
              class="w-100 flex-wrap"
            >
              <VBtn
                v-for="p in SUBSCRIPTION_PERIODS"
                :key="p.value"
                :value="p.value"
                size="small"
              >
                {{ p.title }}
              </VBtn>
            </VBtnToggle>
          </VCol>

          <VCol cols="12">
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-subtitle-2">تعداد کارمند (صندلی)</span>
              <VChip
                size="small"
                color="primary"
                variant="tonal"
              >
                {{ seatCount }} نفر
              </VChip>
            </div>
            <VSlider
              v-model="seatCount"
              :min="1"
              :max="100"
              :step="1"
              thumb-label
              color="primary"
            />
            <p class="text-caption text-medium-emphasis mb-0">
              {{ estimatedLine }}
            </p>
          </VCol>

          <VCol
            v-if="coreModule?.features?.length"
            cols="12"
          >
            <div class="text-subtitle-2 mb-2">
              امکانات CRM پایه
            </div>
            <VList density="compact">
              <VListItem
                v-for="(feature, idx) in coreModule.features"
                :key="idx"
                :title="feature"
                prepend-icon="tabler-check"
              />
            </VList>
          </VCol>
        </VRow>

        <VCard
          v-if="preview"
          variant="tonal"
          color="primary"
          class="mt-4"
        >
          <VCardText>
            <div class="d-flex justify-space-between align-center flex-wrap gap-2">
              <span>مبلغ قابل پرداخت</span>
              <span class="text-h5 font-weight-bold">
                {{ formatRial(preview.total_amount) }}
              </span>
            </div>
            <div
              v-if="preview.seat_count"
              class="text-caption mt-2"
            >
              {{ preview.seat_count }} کارمند × {{ formatRial(preview.seat_price_per_unit) }}
            </div>
          </VCardText>
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="model = false"
        >
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="purchasing"
          :disabled="!selected.length || !preview"
          prepend-icon="tabler-credit-card"
          @click="purchase"
        >
          پرداخت و فعال‌سازی
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
