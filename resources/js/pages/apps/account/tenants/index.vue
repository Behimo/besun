<script setup>
const route = useRoute()
const router = useRouter()
const { applyAuthPayload, enterTenantShell } = useAppShell()
const { formatDate } = useJalaliDate()

const tenants = ref([])
const loading = ref(true)
const createDialog = ref(false)
const name = ref('')
const creating = ref(false)
const generalError = ref('')

const moduleLabels = {
  'core-base': 'پایه',
  'mod-sms': 'پیامک',
  'mod-reports': 'گزارش',
  'mod-automation': 'اتوماسیون',
  'mod-sales-funnel': 'کاریز',
  'mod-projects': 'پروژه',
  'mod-web-forms': 'وب‌فرم',
  'mod-invoicing': 'فاکتور',
  'mod-ticketing': 'تیکت',
  'mod-integrations': 'یکپارچگی',
  'mod-bi': 'BI',
}

const statusColor = tenant => {
  if (!tenant.has_core_module)
    return 'warning'

  const exp = tenant.core_expires_at
  if (!exp)
    return 'success'

  const days = (new Date(exp) - Date.now()) / 86400000
  if (days < 0)
    return 'error'
  if (days < 14)
    return 'warning'

  return 'success'
}

const statusLabel = tenant => {
  if (!tenant.has_core_module)
    return 'نیاز به خرید'

  if (statusColor(tenant) === 'error')
    return 'منقضی'

  if (statusColor(tenant) === 'warning' && tenant.core_expires_at)
    return 'نزدیک انقضا'

  return 'فعال'
}

const needsPurchase = tenant =>
  !tenant.has_core_module
  || statusColor(tenant) === 'error'
  || (statusColor(tenant) === 'warning' && tenant.core_expires_at)

const fetchTenants = async () => {
  loading.value = true
  generalError.value = ''

  try {
    const res = await $api('/tenants')
    tenants.value = res.tenants ?? []
  } catch {
    generalError.value = 'خطا در بارگذاری مجموعه‌ها'
  } finally {
    loading.value = false
  }
}

const createTenant = async () => {
  if (!name.value.trim())
    return

  creating.value = true
  generalError.value = ''

  try {
    const res = await $api('/tenants', {
      method: 'POST',
      body: { name: name.value.trim() },
    })

    applyAuthPayload(res)
    createDialog.value = false
    name.value = ''
    await fetchTenants()

    const tenantId = res.open_purchase_for_tenant_id || res.tenant?.id
    if (tenantId) {
      await router.push({
        name: 'apps-account-modules',
        query: { tenant: tenantId, setup: '1' },
      })
    }
  } catch {
    generalError.value = 'خطا در ایجاد مجموعه'
  } finally {
    creating.value = false
  }
}

const goToModuleStore = tenant => {
  router.push({
    name: 'apps-account-modules',
    query: { tenant: tenant.id },
  })
}

const switchTenant = async tenant => {
  generalError.value = ''

  try {
    await enterTenantShell(tenant)
  } catch (e) {
    generalError.value = e?.data?.message || 'خطا در انتخاب مجموعه'
  }
}

onMounted(async () => {
  await fetchTenants()

  const openId = Number(route.query.open_purchase)
  if (openId) {
    const t = tenants.value.find(x => x.id === openId)
    if (t)
      goToModuleStore(t)
  }
})
</script>

<template>
  <div>
    <VAlert
      v-if="generalError"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ generalError }}
    </VAlert>

    <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          مجموعه‌های من
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          ماژول پایه را از فروشگاه خریداری کنید، سپس وارد مجموعه شوید.
        </p>
      </div>
      <VBtn
        prepend-icon="tabler-plus"
        @click="createDialog = true"
      >
        مجموعه جدید
      </VBtn>
    </div>

    <div
      v-if="loading"
      class="d-flex justify-center pa-12"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="48"
      />
    </div>

    <VRow v-else-if="tenants.length">
      <VCol
        v-for="tenant in tenants"
        :key="tenant.id"
        cols="12"
        md="6"
        xl="4"
      >
        <VCard
          class="tenant-card h-100"
          elevation="2"
        >
          <div
            class="tenant-card__header"
            :class="`tenant-card__header--${statusColor(tenant)}`"
          >
            <div class="d-flex align-center justify-space-between">
              <VAvatar
                color="white"
                variant="flat"
                rounded
                size="52"
              >
                <VIcon
                  icon="tabler-building-skyscraper"
                  color="primary"
                  size="28"
                />
              </VAvatar>
              <VChip
                size="small"
                color="white"
                variant="flat"
                class="font-weight-medium"
              >
                {{ statusLabel(tenant) }}
              </VChip>
            </div>
            <h5 class="text-h5 text-white mt-4 mb-1">
              {{ tenant.name }}
            </h5>
            <p
              class="text-caption text-white text-opacity-80 mb-0"
              dir="ltr"
            >
              {{ tenant.slug }}
            </p>
          </div>

          <VCardText class="pa-5">
            <VRow dense>
              <VCol cols="6">
                <div class="tenant-stat">
                  <VIcon
                    icon="tabler-users"
                    size="20"
                    class="text-medium-emphasis mb-1"
                  />
                  <div class="text-caption text-medium-emphasis">
                    کارمندان
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ tenant.seat_limit ? `${tenant.seats_used ?? 0}/${tenant.seat_limit}` : '—' }}
                  </div>
                </div>
              </VCol>
              <VCol cols="6">
                <div class="tenant-stat">
                  <VIcon
                    icon="tabler-calendar-event"
                    size="20"
                    class="text-medium-emphasis mb-1"
                  />
                  <div class="text-caption text-medium-emphasis">
                    انقضای پایه
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ tenant.core_expires_at ? formatDate(tenant.core_expires_at) : '—' }}
                  </div>
                </div>
              </VCol>
            </VRow>

            <div
              v-if="tenant.active_modules?.length"
              class="mt-4"
            >
              <div class="text-caption text-medium-emphasis mb-2">
                ماژول‌های فعال
              </div>
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="slug in tenant.active_modules"
                  :key="slug"
                  size="small"
                  color="primary"
                  variant="tonal"
                >
                  {{ moduleLabels[slug] || slug }}
                </VChip>
              </div>
            </div>
            <div
              v-else
              class="mt-4 text-caption text-medium-emphasis"
            >
              هنوز ماژولی فعال نشده
            </div>
          </VCardText>

          <VDivider />

          <VCardActions class="pa-4 flex-wrap gap-2">
            <VBtn
              variant="tonal"
              color="primary"
              prepend-icon="tabler-shopping-cart"
              @click="goToModuleStore(tenant)"
            >
              {{ needsPurchase(tenant) ? (tenant.has_core_module ? 'تمدید / خرید' : 'خرید ماژول') : 'فروشگاه ماژول' }}
            </VBtn>
            <VSpacer />
            <VBtn
              color="primary"
              variant="elevated"
              prepend-icon="tabler-login"
              @click="switchTenant(tenant)"
            >
              ورود به مجموعه
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <VCard
      v-else
      variant="tonal"
      color="info"
    >
      <VCardText class="text-center pa-8">
        <VIcon
          icon="tabler-building-plus"
          size="48"
          class="mb-4 text-info"
        />
        <h6 class="text-h6 mb-2">
          هنوز مجموعه‌ای ندارید
        </h6>
        <p class="text-body-2 text-medium-emphasis mb-4">
          اولین مجموعه خود را بسازید و ماژول CRM را فعال کنید.
        </p>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="createDialog = true"
        >
          ایجاد مجموعه
        </VBtn>
      </VCardText>
    </VCard>

    <VDialog
      v-model="createDialog"
      max-width="440"
    >
      <VCard title="مجموعه جدید">
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-4">
            پس از ساخت، به فروشگاه ماژول هدایت می‌شوید تا ماژول پایه CRM را فعال کنید.
          </p>
          <AppTextField
            v-model="name"
            label="نام مجموعه"
            placeholder="مثلاً: شرکت رهبر"
            autofocus
            @keyup.enter="createTenant"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="createDialog = false">
            انصراف
          </VBtn>
          <VBtn
            color="primary"
            :loading="creating"
            :disabled="!name.trim()"
            @click="createTenant"
          >
            ایجاد
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.tenant-card {
  border-radius: 12px;
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.tenant-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 28px rgba(var(--v-theme-on-surface), 0.12) !important;
}

.tenant-card__header {
  padding: 1.25rem 1.5rem;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgba(var(--v-theme-primary), 0.85) 100%);
}

.tenant-card__header--success {
  background: linear-gradient(135deg, rgb(var(--v-theme-success)) 0%, rgba(var(--v-theme-success), 0.85) 100%);
}

.tenant-card__header--warning {
  background: linear-gradient(135deg, rgb(var(--v-theme-warning)) 0%, rgba(var(--v-theme-warning), 0.85) 100%);
}

.tenant-card__header--error {
  background: linear-gradient(135deg, rgb(var(--v-theme-error)) 0%, rgba(var(--v-theme-error), 0.85) 100%);
}

.tenant-stat {
  padding: 0.75rem;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
