<script setup>
definePage({ meta: { action: 'manage', subject: 'Integrations' } })

const { moment, formatDate, formatDateTime, toApiDate } = useJalaliDate()

const connection = ref(null)
const campaigns = ref([])
const loading = ref(true)
const saving = ref(false)
const syncingProducts = ref(false)
const syncingOrders = ref(false)
const testing = ref(false)
const downloadingPlugin = ref(false)
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')
let syncPollTimer = null

const defaultFromDate = () => toApiDate(moment().subtract(30, 'days'))

const form = ref({
  store_url: '',
  is_active: true,
  order_sync_enabled: true,
  order_sync_from_date: defaultFromDate(),
  campaign_id: null,
})

const crmBaseUrl = computed(() => (typeof window !== 'undefined' ? window.location.origin : ''))

const isConnected = computed(() => Boolean(connection.value?.plugin_connected))
const hasConnection = computed(() => Boolean(connection.value?.bridge_token))
const isSyncPending = computed(() =>
  connection.value?.pending_sync?.products || connection.value?.pending_sync?.orders,
)

const notify = (text, color = 'success') => {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

const stopSyncPolling = () => {
  if (syncPollTimer) {
    clearInterval(syncPollTimer)
    syncPollTimer = null
  }
}

const startSyncPolling = () => {
  stopSyncPolling()
  syncPollTimer = setInterval(async () => {
    await fetchConnection(true)

    if (!connection.value?.pending_sync?.products && !connection.value?.pending_sync?.orders) {
      stopSyncPolling()
      syncingProducts.value = false
      syncingOrders.value = false
    }
  }, 2500)
}

const fetchConnection = async (silent = false) => {
  if (!silent)
    loading.value = true

  try {
    const [connRes, campaignRes] = await Promise.all([
      $api('/integrations/woocommerce'),
      $api('/campaigns').catch(() => ({ data: [] })),
    ])

    connection.value = connRes.connection
    campaigns.value = campaignRes.data ?? campaignRes ?? []

    if (connRes.connection) {
      form.value.store_url = connRes.connection.store_url ?? ''
      form.value.is_active = connRes.connection.is_active ?? true
      form.value.order_sync_enabled = connRes.connection.order_sync_enabled ?? true
      form.value.order_sync_from_date = connRes.connection.order_sync_from_date ?? defaultFromDate()
      form.value.campaign_id = connRes.connection.campaign_id ?? null
    }

    if (connRes.connection?.pending_sync?.products || connRes.connection?.pending_sync?.orders) {
      startSyncPolling()
    }
  } finally {
    if (!silent)
      loading.value = false
  }
}

const saveConnection = async () => {
  saving.value = true
  try {
    const res = await $api('/integrations/woocommerce', {
      method: 'POST',
      body: {
        store_url: form.value.store_url,
        is_active: form.value.is_active,
        order_sync_enabled: form.value.order_sync_enabled,
        order_sync_from_date: toApiDate(form.value.order_sync_from_date),
        campaign_id: form.value.campaign_id,
      },
    })

    connection.value = res.connection
    notify('تنظیمات ذخیره شد.')
  } catch (error) {
    notify(error?.data?.message ?? 'خطا در ذخیره', 'error')
  } finally {
    saving.value = false
  }
}

const testConnection = async () => {
  testing.value = true
  try {
    const res = await $api('/integrations/woocommerce/test', { method: 'POST' })
    if (res.connection)
      connection.value = res.connection
    notify(res.message ?? 'اتصال برقرار است.')
  } catch (error) {
    notify(error?.data?.message ?? 'پلاگین هنوز وصل نشده', 'warning')
  } finally {
    testing.value = false
  }
}

const syncProducts = async () => {
  syncingProducts.value = true
  try {
    const res = await $api('/integrations/woocommerce/sync', { method: 'POST' })
    connection.value = res.connection ?? connection.value
    notify('همگام‌سازی محصولات در صف است — پلاگین وردپرس اجرا می‌کند.')
    startSyncPolling()
  } catch (error) {
    syncingProducts.value = false
    notify(error?.data?.message ?? 'خطا در همگام‌سازی', 'error')
  }
}

const syncOrders = async () => {
  syncingOrders.value = true
  try {
    const res = await $api('/integrations/woocommerce/sync-orders', {
      method: 'POST',
      body: { from_date: toApiDate(form.value.order_sync_from_date) },
    })

    connection.value = res.connection ?? connection.value
    notify('همگام‌سازی سفارش‌ها در صف است — پلاگین وردپرس اجرا می‌کند.')
    startSyncPolling()
  } catch (error) {
    syncingOrders.value = false
    notify(error?.data?.message ?? 'خطا در همگام‌سازی', 'error')
  }
}

const copyValue = async (value, label) => {
  if (!value)
    return

  try {
    await navigator.clipboard.writeText(value)
    notify(`${label} کپی شد.`)
  } catch {
    notify(`کپی ${label} ممکن نشد`, 'error')
  }
}

const downloadPlugin = async () => {
  downloadingPlugin.value = true
  try {
    const accessToken = useCookie('accessToken').value
    const userData = useCookie('userData').value
    const baseURL = import.meta.env.VITE_API_BASE_URL || '/api/v1'
    const headers = { Accept: 'application/zip' }

    if (accessToken)
      headers.Authorization = `Bearer ${accessToken}`
    if (userData?.tenant?.id)
      headers['X-Tenant-Id'] = String(userData.tenant.id)
    if (userData?.workspace?.id)
      headers['X-Workspace-Id'] = String(userData.workspace.id)

    const response = await fetch(`${baseURL}/integrations/woocommerce/plugin/download`, { headers })
    if (!response.ok)
      throw new Error('download failed')

    const blob = await response.blob()
    const version = connection.value?.plugin_package_version ?? '1.0.1'
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')

    link.href = url
    link.download = `rahbar-crm-connector-${version}.zip`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)

    notify('پلاگین دانلود شد.')
  } catch {
    notify('دانلود پلاگین ناموفق بود', 'error')
  } finally {
    downloadingPlugin.value = false
  }
}

const syncStatusLabel = status => ({
  success: 'موفق',
  running: 'در حال اجرا',
  failed: 'ناموفق',
}[status] ?? status)

onMounted(fetchConnection)
onBeforeUnmount(stopSyncPolling)
</script>

<template>
  <div class="woo-integration">
    <!-- Hero -->
    <VCard
      class="woo-integration__hero mb-6"
      elevation="0"
    >
      <div class="woo-integration__hero-bg" />
      <VCardText class="position-relative pa-6">
        <VRow align="center">
          <VCol
            cols="12"
            md="8"
          >
            <div class="d-flex align-center gap-4">
              <VAvatar
                color="primary"
                variant="tonal"
                size="56"
                rounded
              >
                <VIcon
                  icon="tabler-brand-wordpress"
                  size="28"
                />
              </VAvatar>
              <div>
                <h4 class="text-h4 mb-1">
                  اتصال ووکامرس
                </h4>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  همگام‌سازی محصولات و سفارش‌ها از طریق پلاگین وردپرس
                </p>
              </div>
            </div>
          </VCol>
          <VCol
            cols="12"
            md="4"
            class="d-flex justify-md-end"
          >
            <VChip
              :color="isConnected ? 'success' : 'warning'"
              variant="tonal"
              size="large"
              prepend-icon="tabler-plug-connected"
            >
              {{ isConnected ? 'پلاگین متصل' : 'در انتظار اتصال' }}
            </VChip>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VProgressLinear
      v-if="loading"
      indeterminate
      color="primary"
      class="mb-4"
    />

    <VRow v-if="!loading">
      <!-- Settings -->
      <VCol
        cols="12"
        lg="5"
      >
        <VCard class="h-100">
          <VCardItem>
            <VCardTitle>تنظیمات فروشگاه</VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <AppTextField
              v-model="form.store_url"
              label="آدرس فروشگاه"
              placeholder="https://example.com"
              class="mb-4"
            />
            <VSwitch
              v-model="form.is_active"
              label="اتصال فعال"
              color="primary"
              class="mb-2"
            />
            <VSwitch
              v-model="form.order_sync_enabled"
              label="همگام‌سازی سفارش‌ها"
              color="primary"
              class="mb-4"
            />
            <AppSelect
              v-model="form.campaign_id"
              :items="campaigns"
              item-title="name"
              item-value="id"
              label="کمپین پیش‌فرض لیدها"
              clearable
              placeholder="بدون کمپین"
              class="mb-6"
            />
            <VBtn
              color="primary"
              block
              :loading="saving"
              @click="saveConnection"
            >
              ذخیره تنظیمات
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Plugin -->
      <VCol
        cols="12"
        lg="7"
      >
        <VCard class="h-100">
          <VCardItem>
            <template #append>
              <VBtn
                variant="tonal"
                prepend-icon="tabler-download"
                :loading="downloadingPlugin"
                @click="downloadPlugin"
              >
                دانلود پلاگین
              </VBtn>
            </template>
            <VCardTitle>Rahbar CRM Connector</VCardTitle>
            <VCardSubtitle>
              نسخه {{ connection?.plugin_package_version ?? '1.0.1' }}
            </VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div class="woo-integration__steps mb-6">
              <div
                v-for="(step, i) in [
                  'پلاگین را دانلود و در وردپرس نصب کنید',
                  'تنظیمات را ذخیره کنید',
                  'Token و Secret را در پلاگین وارد کنید',
                  'در وردپرس «تست اتصال» بزنید',
                ]"
                :key="i"
                class="woo-integration__step"
              >
                <span class="woo-integration__step-num">{{ i + 1 }}</span>
                <span>{{ step }}</span>
              </div>
            </div>

            <template v-if="hasConnection">
              <VRow dense>
                <VCol cols="12">
                  <div class="woo-integration__credential">
                    <span class="text-caption text-medium-emphasis">آدرس CRM</span>
                    <div class="d-flex align-center gap-2 mt-1">
                      <code class="flex-grow-1">{{ crmBaseUrl }}</code>
                      <IconBtn
                        size="small"
                        @click="copyValue(crmBaseUrl, 'آدرس CRM')"
                      >
                        <VIcon icon="tabler-copy" />
                      </IconBtn>
                    </div>
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <div class="woo-integration__credential">
                    <span class="text-caption text-medium-emphasis">Bridge Token</span>
                    <div class="d-flex align-center gap-2 mt-1">
                      <code class="flex-grow-1 text-truncate">{{ connection.bridge_token }}</code>
                      <IconBtn
                        size="small"
                        @click="copyValue(connection.bridge_token, 'Token')"
                      >
                        <VIcon icon="tabler-copy" />
                      </IconBtn>
                    </div>
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <div class="woo-integration__credential">
                    <span class="text-caption text-medium-emphasis">Bridge Secret</span>
                    <div class="d-flex align-center gap-2 mt-1">
                      <code class="flex-grow-1">••••••••</code>
                      <IconBtn
                        size="small"
                        @click="copyValue(connection.bridge_secret, 'Secret')"
                      >
                        <VIcon icon="tabler-copy" />
                      </IconBtn>
                    </div>
                  </div>
                </VCol>
              </VRow>

              <div class="d-flex gap-2 mt-4">
                <VBtn
                  variant="tonal"
                  :loading="testing"
                  @click="testConnection"
                >
                  بررسی اتصال
                </VBtn>
                <span
                  v-if="connection.plugin_last_ping_at"
                  class="text-caption text-medium-emphasis align-self-center"
                >
                  آخرین ارتباط: {{ formatDateTime(connection.plugin_last_ping_at) }}
                </span>
              </div>
            </template>
            <p
              v-else
              class="text-body-2 text-medium-emphasis mb-0"
            >
              ابتدا تنظیمات فروشگاه را ذخیره کنید.
            </p>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Sync -->
      <VCol cols="12">
        <VCard>
          <VCardItem>
            <VCardTitle>همگام‌سازی</VCardTitle>
            <VCardSubtitle>
              از CRM درخواست بدهید — پلاگین وردپرس اجرا می‌کند
            </VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <div class="woo-integration__sync-tile">
                  <VIcon
                    icon="tabler-package"
                    size="32"
                    color="primary"
                    class="mb-3"
                  />
                  <h6 class="text-h6 mb-1">
                    محصولات
                  </h6>
                  <p
                    v-if="connection?.last_sync_at"
                    class="text-caption text-medium-emphasis mb-3"
                  >
                    {{ formatDateTime(connection.last_sync_at) }}
                    — {{ syncStatusLabel(connection.last_sync_status) }}
                  </p>
                  <p
                    v-else
                    class="text-caption text-medium-emphasis mb-3"
                  >
                    هنوز همگام نشده
                  </p>
                  <VBtn
                    color="primary"
                    variant="tonal"
                    :loading="syncingProducts || connection?.pending_sync?.products"
                    :disabled="!hasConnection"
                    prepend-icon="tabler-refresh"
                    @click="syncProducts"
                  >
                    همگام‌سازی
                  </VBtn>
                </div>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <div class="woo-integration__sync-tile">
                  <VIcon
                    icon="tabler-shopping-cart"
                    size="32"
                    color="info"
                    class="mb-3"
                  />
                  <h6 class="text-h6 mb-1">
                    سفارش‌ها
                  </h6>
                  <AppJalaliDatePicker
                    v-model="form.order_sync_from_date"
                    label="از تاریخ"
                    density="compact"
                    class="mb-3"
                    hide-details
                  />
                  <p
                    v-if="connection?.last_order_sync_at"
                    class="text-caption text-medium-emphasis mb-3"
                  >
                    {{ formatDateTime(connection.last_order_sync_at) }}
                    — {{ syncStatusLabel(connection.last_order_sync_status) }}
                  </p>
                  <VBtn
                    color="info"
                    variant="tonal"
                    :loading="syncingOrders || connection?.pending_sync?.orders"
                    :disabled="!hasConnection || !form.order_sync_enabled"
                    prepend-icon="tabler-refresh"
                    @click="syncOrders"
                  >
                    همگام‌سازی
                  </VBtn>
                </div>
              </VCol>
            </VRow>
          </VCardText>
          <VDivider />
          <VCardActions class="px-4 py-3">
            <VBtn
              variant="text"
              :to="{ name: 'apps-crm-products' }"
              prepend-icon="tabler-arrow-left"
            >
              مشاهده کاتالوگ محصولات
            </VBtn>
            <VSpacer />
            <VChip
              v-if="isSyncPending"
              size="small"
              color="primary"
              variant="tonal"
            >
              در انتظار پلاگین…
            </VChip>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <VSnackbar
      v-model="snackbar"
      :color="snackbarColor"
      :timeout="3500"
      location="bottom end"
    >
      {{ snackbarText }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.woo-integration__hero {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  overflow: hidden;
}

.woo-integration__hero-bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 100% 0%, rgba(var(--v-theme-primary), 0.1), transparent 45%),
    radial-gradient(circle at 0% 100%, rgba(var(--v-theme-info), 0.08), transparent 40%);
  pointer-events: none;
}

.woo-integration__steps {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.woo-integration__step {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.875rem;
}

.woo-integration__step-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  inline-size: 1.5rem;
  block-size: 1.5rem;
  border-radius: 50%;
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
  font-size: 0.75rem;
  font-weight: 600;
  flex-shrink: 0;
}

.woo-integration__credential {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.6));
}

.woo-integration__credential code {
  font-size: 0.8rem;
  word-break: break-all;
}

.woo-integration__sync-tile {
  padding: 1.25rem;
  border-radius: 12px;
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.8));
  block-size: 100%;
}
</style>
