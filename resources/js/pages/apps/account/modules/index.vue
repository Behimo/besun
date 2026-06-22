<script setup>
import {
  addonPriceForTenant,
  formatRial,
  periodLabel,
  previewTenantSubscription,
  purchaseTenantSubscription,
  seatPriceForPeriod,
} from '@/composables/useTenantSubscription'

const route = useRoute()
const router = useRouter()
const { applyAuthPayload, enterTenantShell } = useAppShell()

const tenants = ref([])
const catalog = ref([])
const selectedTenantId = ref(null)
const loading = ref(true)
const purchasing = ref(false)
const previewLoading = ref(false)
const purchaseDialog = ref(false)
const purchaseTenant = ref(null)
const addonSelected = ref([])
const addonPreview = ref(null)
const error = ref('')
const corePurchasedSuccess = ref(false)
const coreModuleSection = ref(null)

const isSetupMode = computed(() => route.query.setup === '1')

const selectedTenant = computed(() => tenants.value.find(t => t.id === selectedTenantId.value))
const coreModules = computed(() => catalog.value.filter(m => m.is_core))
const addonModules = computed(() =>
  catalog.value
    .filter(m => !m.is_core)
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)))

const effectivePeriod = computed(() =>
  selectedTenant.value?.core_subscription_type || 'monthly')

const cartTotal = computed(() =>
  addonPreview.value?.total_amount ?? 0)

const fetchData = async () => {
  loading.value = true
  error.value = ''
  try {
    const [tenantsRes, catalogRes] = await Promise.all([
      $api('/tenants'),
      $api('/modules/catalog'),
    ])
    tenants.value = tenantsRes.tenants ?? []
    catalog.value = catalogRes.modules ?? []

    const queryTenant = Number(route.query.tenant)
    if (queryTenant && tenants.value.some(t => t.id === queryTenant))
      selectedTenantId.value = queryTenant
    else if (tenants.value.length)
      selectedTenantId.value = tenants.value[0].id
  } catch (e) {
    error.value = 'خطا در بارگذاری اطلاعات'
    console.error(e)
  } finally {
    loading.value = false
  }
}

const selectTenant = id => {
  selectedTenantId.value = id
  addonSelected.value = []
  addonPreview.value = null
  corePurchasedSuccess.value = false
}

const openCorePurchase = () => {
  if (!selectedTenant.value)
    return
  purchaseTenant.value = selectedTenant.value
  purchaseDialog.value = true
}

const toggleAddon = mod => {
  if (!selectedTenant.value?.has_core_module || isModuleActive(mod.slug))
    return

  const idx = addonSelected.value.indexOf(mod.id)
  if (idx === -1)
    addonSelected.value.push(mod.id)
  else
    addonSelected.value.splice(idx, 1)
}

const loadAddonPreview = async () => {
  if (!selectedTenantId.value || !addonSelected.value.length || !selectedTenant.value?.has_core_module) {
    addonPreview.value = null

    return
  }

  previewLoading.value = true
  try {
    const res = await previewTenantSubscription(selectedTenantId.value, {
      modules: addonSelected.value.map(id => ({
        module_id: id,
        period: effectivePeriod.value,
      })),
    })
    addonPreview.value = res.preview
    error.value = ''
  } catch (e) {
    addonPreview.value = null
    error.value = e?.data?.message || 'خطا در محاسبه مبلغ'
  } finally {
    previewLoading.value = false
  }
}

const displayAddonPrice = mod => addonPriceForTenant(mod, selectedTenant.value)

const purchaseAddons = async () => {
  if (!selectedTenantId.value || !addonSelected.value.length)
    return

  purchasing.value = true
  error.value = ''
  try {
    const res = await purchaseTenantSubscription(selectedTenantId.value, {
      modules: addonSelected.value.map(id => ({
        module_id: id,
        period: effectivePeriod.value,
      })),
    })
    applyAuthPayload(res)
    addonSelected.value = []
    addonPreview.value = null
    await fetchData()
  } catch (e) {
    error.value = e?.data?.message || 'خطا در خرید ماژول'
  } finally {
    purchasing.value = false
  }
}

const isModuleActive = slug => selectedTenant.value?.active_modules?.includes(slug)

const moduleNameBySlug = slug => catalog.value.find(m => m.slug === slug)?.name ?? slug

const modulePrerequisiteStatus = dep => {
  if (dep.slug === 'core-base')
    return selectedTenant.value?.has_core_module ? 'active' : 'missing'

  return isModuleActive(dep.slug) ? 'active' : 'missing'
}

const moduleCardState = mod => {
  if (mod.is_core)
    return selectedTenant.value?.has_core_module ? 'active' : 'available'

  if (!selectedTenant.value?.has_core_module)
    return 'locked'

  return isModuleActive(mod.slug) ? 'active' : 'available'
}

const onCorePurchased = async () => {
  await fetchData()
  corePurchasedSuccess.value = true

  const query = { ...route.query }
  delete query.setup
  router.replace({ query })
}

const enterPurchasedTenant = async () => {
  const tenant = selectedTenant.value
  if (!tenant?.has_core_module)
    return

  try {
    await enterTenantShell(tenant)
  } catch (e) {
    error.value = e?.data?.message || 'خطا در ورود به مجموعه'
  }
}

const runSetupFlow = async () => {
  if (!isSetupMode.value || !selectedTenantId.value)
    return

  await nextTick()
  coreModuleSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })

  const tenant = selectedTenant.value
  if (tenant && !tenant.has_core_module) {
    purchaseTenant.value = tenant
    purchaseDialog.value = true
  }
}

watch(addonSelected, loadAddonPreview, { deep: true })

watch(selectedTenantId, id => {
  if (id)
    router.replace({ query: { ...route.query, tenant: id } })
})

onMounted(async () => {
  await fetchData()
  await runSetupFlow()
})
</script>

<template>
  <div class="module-store">
    <div class="module-store__hero mb-6">
      <VRow align="center">
        <VCol
          cols="12"
          md="8"
        >
          <h3 class="text-h3 text-white mb-2">
            فروشگاه ماژول
          </h3>
          <p class="text-body-1 text-white text-opacity-90 mb-0">
            ماژول پایه CRM را فعال کنید، سپس افزونه‌های موردنیاز را انتخاب و خریداری کنید.
          </p>
        </VCol>
        <VCol
          cols="12"
          md="4"
          class="d-none d-md-flex justify-end"
        >
          <VAvatar
            size="80"
            color="white"
            variant="flat"
            rounded
          >
            <VIcon
              icon="tabler-shopping-cart"
              size="40"
              color="primary"
            />
          </VAvatar>
        </VCol>
      </VRow>
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
      v-if="isSetupMode && !corePurchasedSuccess && !loading"
      type="warning"
      variant="tonal"
      prominent
      class="mb-4"
      title="مجموعه ساخته شد"
      text="برای استفاده از CRM، ماژول پایه را فعال کنید. دیالوگ خرید به‌زودی باز می‌شود."
    />

    <VAlert
      v-if="corePurchasedSuccess"
      type="success"
      variant="tonal"
      prominent
      class="mb-4"
    >
      <div class="d-flex flex-wrap align-center justify-space-between gap-3">
        <span>ماژول پایه با موفقیت فعال شد. اکنون می‌توانید وارد مجموعه شوید.</span>
        <VBtn
          color="success"
          variant="elevated"
          prepend-icon="tabler-login"
          @click="enterPurchasedTenant"
        >
          ورود به مجموعه
        </VBtn>
      </div>
    </VAlert>

    <VCard
      v-if="loading"
      class="mb-6"
    >
      <VCardText class="d-flex justify-center pa-12">
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </VCardText>
    </VCard>

    <template v-else-if="tenants.length">
      <div class="mb-6">
        <div class="text-subtitle-1 font-weight-medium mb-3">
          انتخاب مجموعه
        </div>
        <VRow>
          <VCol
            v-for="tenant in tenants"
            :key="tenant.id"
            cols="12"
            sm="6"
            md="4"
          >
            <VCard
              :class="['tenant-pick h-100', { 'tenant-pick--active': tenant.id === selectedTenantId }]"
              variant="outlined"
              @click="selectTenant(tenant.id)"
            >
              <VCardText class="d-flex align-center gap-3 pa-4">
                <VAvatar
                  :color="tenant.id === selectedTenantId ? 'primary' : 'secondary'"
                  variant="tonal"
                  rounded
                >
                  <VIcon icon="tabler-building" />
                </VAvatar>
                <div class="flex-grow-1">
                  <div class="font-weight-medium">
                    {{ tenant.name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ tenant.has_core_module ? `${tenant.seats_used}/${tenant.seat_limit} صندلی` : 'بدون ماژول پایه' }}
                  </div>
                </div>
                <VChip
                  :color="tenant.has_core_module ? 'success' : 'warning'"
                  size="x-small"
                  variant="tonal"
                >
                  {{ tenant.has_core_module ? 'فعال' : 'نیاز به خرید' }}
                </VChip>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </div>

      <template v-if="selectedTenant">
        <VCard class="mb-6">
          <VCardText class="pa-6">
            <div ref="coreModuleSection">
            <VCard
              v-for="mod in coreModules"
              :key="mod.id"
              class="core-module-card mb-8"
              variant="flat"
            >
              <VCardText class="pa-5">
                <VRow align="center">
                  <VCol
                    cols="12"
                    lg="8"
                  >
                    <div class="d-flex align-start gap-4">
                      <VAvatar
                        color="primary"
                        variant="tonal"
                        size="52"
                        rounded
                      >
                        <VIcon
                          :icon="mod.icon || 'tabler-building-store'"
                          size="26"
                        />
                      </VAvatar>
                      <div>
                        <div class="d-flex align-center flex-wrap gap-2 mb-1">
                          <h6 class="text-h6">
                            {{ mod.name }}
                          </h6>
                          <VChip
                            v-if="selectedTenant.has_core_module"
                            size="small"
                            color="success"
                            variant="tonal"
                          >
                            فعال
                          </VChip>
                        </div>
                        <p class="text-body-2 text-medium-emphasis mb-2">
                          {{ mod.description }}
                        </p>
                        <div class="text-subtitle-1 font-weight-bold text-primary">
                          از {{ formatRial(seatPriceForPeriod(mod, 'monthly')) }}
                          <span class="text-caption text-medium-emphasis font-weight-regular">/ کارمند / ماه</span>
                        </div>
                      </div>
                    </div>
                  </VCol>
                  <VCol
                    cols="12"
                    lg="4"
                    class="text-lg-end"
                  >
                    <VBtn
                      v-if="!selectedTenant.has_core_module"
                      color="primary"
                      block
                      prepend-icon="tabler-credit-card"
                      @click="openCorePurchase"
                    >
                      خرید و فعال‌سازی
                    </VBtn>
                    <VAlert
                      v-else
                      type="success"
                      variant="tonal"
                      density="compact"
                    >
                      {{ selectedTenant.seats_used }} از {{ selectedTenant.seat_limit }} صندلی
                    </VAlert>
                  </VCol>
                </VRow>
                <div
                  v-if="mod.features?.length"
                  class="d-flex flex-wrap gap-2 mt-4 pt-4 module-divider"
                >
                  <VChip
                    v-for="(f, i) in mod.features"
                    :key="i"
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-check"
                  >
                    {{ f }}
                  </VChip>
                </div>
              </VCardText>
            </VCard>
            </div>

            <VAlert
              v-if="selectedTenant.has_core_module"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-4"
            >
              دوره اشتراک پایه:
              <strong>{{ periodLabel(effectivePeriod) }}</strong>
              <template v-if="selectedTenant.core_remaining_days">
                — {{ selectedTenant.core_remaining_days.toLocaleString('fa-IR') }} روز باقیمانده.
                قیمت افزونه‌ها متناسب با همین مدت محاسبه می‌شود.
              </template>
            </VAlert>

            <VAlert
              v-else
              type="info"
              variant="tonal"
              density="compact"
              class="mb-4"
            >
              پس از فعال‌سازی ماژول پایه، افزونه‌ها با همان دوره اشتراک و بر اساس زمان باقیمانده قابل خرید هستند.
            </VAlert>

            <VRow>
              <VCol
                v-for="mod in addonModules"
                :key="mod.id"
                cols="12"
                md="4"
              >
                <VCard
                  :class="[
                    'module-card h-100',
                    { 'module-card--selected': addonSelected.includes(mod.id) },
                    { 'module-card--active': moduleCardState(mod) === 'active' },
                    { 'module-card--locked': moduleCardState(mod) === 'locked' },
                  ]"
                  variant="outlined"
                  @click="moduleCardState(mod) === 'available' && toggleAddon(mod)"
                >
                  <VCardText class="module-card__body pa-4">
                    <div class="d-flex align-start justify-space-between mb-3">
                      <VAvatar
                        :color="moduleCardState(mod) === 'active' ? 'success' : 'primary'"
                        variant="tonal"
                        size="40"
                        rounded
                      >
                        <VIcon
                          :icon="mod.icon || 'tabler-box'"
                          size="20"
                        />
                      </VAvatar>
                      <VChip
                        v-if="moduleCardState(mod) === 'active'"
                        size="x-small"
                        color="success"
                        variant="tonal"
                      >
                        فعال
                      </VChip>
                      <VChip
                        v-else-if="moduleCardState(mod) === 'locked'"
                        size="x-small"
                        color="secondary"
                        variant="tonal"
                      >
                        قفل
                      </VChip>
                      <VCheckbox
                        v-else
                        :model-value="addonSelected.includes(mod.id)"
                        hide-details
                        density="compact"
                        color="primary"
                        @click.stop
                        @update:model-value="toggleAddon(mod)"
                      />
                    </div>

                    <h6 class="text-subtitle-1 font-weight-medium mb-1 module-card__title">
                      {{ mod.name }}
                    </h6>
                    <p class="text-caption text-medium-emphasis mb-3 module-card__desc">
                      {{ mod.description }}
                    </p>

                    <ul
                      v-if="mod.features?.length"
                      class="module-card__features text-caption text-medium-emphasis mb-3"
                    >
                      <li
                        v-for="(f, i) in mod.features.slice(0, 3)"
                        :key="i"
                      >
                        {{ f }}
                      </li>
                    </ul>

                    <div
                      v-if="mod.requires_modules?.length || mod.optional_modules?.length"
                      class="mb-3"
                    >
                      <div
                        v-if="mod.requires_modules?.length"
                        class="text-caption mb-2"
                      >
                        <span class="text-medium-emphasis">پیش‌نیاز:</span>
                        <div
                          v-for="(dep, i) in mod.requires_modules"
                          :key="`req-${i}`"
                          class="d-flex align-center gap-1 mt-1"
                        >
                          <VIcon
                            :icon="modulePrerequisiteStatus(dep) === 'active' ? 'tabler-circle-check' : 'tabler-circle-dashed'"
                            :color="modulePrerequisiteStatus(dep) === 'active' ? 'success' : 'warning'"
                            size="14"
                          />
                          <span>{{ dep.label || moduleNameBySlug(dep.slug) }}</span>
                        </div>
                      </div>
                      <div
                        v-if="mod.optional_modules?.length"
                        class="text-caption"
                      >
                        <span class="text-medium-emphasis">افزونه پیشنهادی:</span>
                        <div
                          v-for="(dep, i) in mod.optional_modules"
                          :key="`opt-${i}`"
                          class="mt-1"
                        >
                          <div class="d-flex align-center gap-1">
                            <VIcon
                              :icon="modulePrerequisiteStatus(dep) === 'active' ? 'tabler-circle-check' : 'tabler-info-circle'"
                              :color="modulePrerequisiteStatus(dep) === 'active' ? 'success' : 'info'"
                              size="14"
                            />
                            <span>{{ dep.label || moduleNameBySlug(dep.slug) }}</span>
                          </div>
                          <div
                            v-if="dep.reason"
                            class="text-disabled ps-5"
                          >
                            {{ dep.reason }}
                          </div>
                        </div>
                      </div>
                    </div>

                      <div class="module-card__price text-subtitle-2 font-weight-bold text-primary mt-auto">
                        {{ formatRial(displayAddonPrice(mod)) }}
                        <span
                          v-if="selectedTenant.has_core_module && selectedTenant.core_remaining_days"
                          class="text-caption text-medium-emphasis font-weight-regular d-block"
                        >
                          برای {{ selectedTenant.core_remaining_days.toLocaleString('fa-IR') }} روز باقیمانده
                        </span>
                        <span
                          v-else
                          class="text-caption text-medium-emphasis font-weight-regular"
                        > / ماه</span>
                      </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VSlideYTransition>
          <VCard
            v-if="addonSelected.length"
            class="module-store__cart"
            elevation="8"
          >
            <VCardText class="d-flex flex-wrap align-center justify-space-between gap-4 pa-4">
              <div>
                <div class="text-subtitle-1 font-weight-medium">
                  {{ addonSelected.length }} ماژول انتخاب شده
                  <VProgressCircular
                    v-if="previewLoading"
                    indeterminate
                    size="16"
                    width="2"
                    class="ms-2"
                  />
                </div>
                <div class="text-h6 text-primary">
                  {{ formatRial(cartTotal) }}
                </div>
                <div
                  v-if="addonPreview?.remaining_days"
                  class="text-caption text-medium-emphasis"
                >
                  محاسبه برای {{ addonPreview.remaining_days.toLocaleString('fa-IR') }} روز باقیمانده
                </div>
              </div>
              <VBtn
                color="primary"
                size="large"
                :loading="purchasing"
                :disabled="!selectedTenant.has_core_module || previewLoading || !addonPreview"
                prepend-icon="tabler-shopping-cart-check"
                @click="purchaseAddons"
              >
                پرداخت و فعال‌سازی
              </VBtn>
            </VCardText>
          </VCard>
        </VSlideYTransition>
      </template>
    </template>

    <VCard
      v-else
      variant="tonal"
      color="info"
    >
      <VCardText class="text-center pa-8">
        <VIcon
          icon="tabler-building-plus"
          size="48"
          class="mb-4"
        />
        <h6 class="text-h6 mb-2">
          ابتدا یک مجموعه بسازید
        </h6>
        <VBtn
          color="primary"
          class="mt-2"
          :to="{ name: 'apps-account-tenants' }"
        >
          رفتن به مجموعه‌ها
        </VBtn>
      </VCardText>
    </VCard>
  </div>

  <TenantPurchaseDialog
    v-model="purchaseDialog"
    :tenant="purchaseTenant"
    @purchased="onCorePurchased"
  />
</template>

<style scoped>
.module-store__hero {
  padding: 2rem;
  border-radius: 12px;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgba(var(--v-theme-primary), 0.75) 100%);
}

.tenant-pick {
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.tenant-pick:hover {
  border-color: rgb(var(--v-theme-primary));
}

.tenant-pick--active {
  border-color: rgb(var(--v-theme-primary)) !important;
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}

.core-module-card {
  background: rgba(var(--v-theme-primary), 0.04);
  border: 1px solid rgba(var(--v-theme-primary), 0.15);
  border-radius: 12px;
}

.module-divider {
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.module-card {
  cursor: pointer;
  border-radius: 12px;
  transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
}

.module-card__body {
  display: flex;
  flex-direction: column;
  block-size: 100%;
  min-block-size: 220px;
}

.module-card:hover:not(.module-card--active):not(.module-card--locked) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--v-theme-on-surface), 0.08);
}

.module-card--selected {
  border-color: rgb(var(--v-theme-primary)) !important;
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}

.module-card--active {
  opacity: 0.85;
  cursor: default;
}

.module-card--locked {
  opacity: 0.55;
  cursor: not-allowed;
}

.module-card__title {
  line-height: 1.4;
}

.module-card__desc {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-block-size: 2.4em;
}

.module-card__features {
  padding-inline-start: 1rem;
  margin: 0;
  list-style: disc;
}

.module-card__features li {
  margin-block-end: 0.15rem;
}

.module-card__price {
  padding-block-start: 0.5rem;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.06);
}

.module-store__cart {
  position: sticky;
  bottom: 1rem;
  z-index: 4;
  border-radius: 12px;
  border: 1px solid rgba(var(--v-theme-primary), 0.2);
}
</style>
