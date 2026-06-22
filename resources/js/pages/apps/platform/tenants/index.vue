<script setup>
import { formatRial, healthColor } from '@/composables/usePlatformAdmin'

definePage({
  meta: {
    action: 'manage',
    subject: 'PlatformAdmin',
  },
})

const { formatDateTime } = useJalaliDate()
const router = useRouter()

const loading = ref(false)
const tenants = ref([])
const page = ref(1)
const perPage = ref(15)
const total = ref(0)
const totalPages = ref(1)
const searchQuery = ref('')
const coreFilter = ref(null)

const coreItems = [
  { title: 'همه', value: null },
  { title: 'پایه فعال', value: 'active' },
  { title: 'بدون پایه', value: 'inactive' },
]

const headers = [
  { title: 'مجموعه', key: 'name' },
  { title: 'مالک', key: 'owner', sortable: false },
  { title: 'سلامت', key: 'health_score', sortable: false },
  { title: 'اعضا', key: 'members_count' },
  { title: 'ماژول پایه', key: 'has_core_module', sortable: false },
  { title: 'وضعیت', key: 'status', sortable: false },
  { title: 'آخرین تراکنش', key: 'last_transaction', sortable: false },
  { title: '', key: 'actions', sortable: false, width: 80 },
]

const fetchTenants = async () => {
  loading.value = true
  try {
    const res = await $api('/platform/tenants', {
      query: {
        q: searchQuery.value || undefined,
        core: coreFilter.value || undefined,
        page: page.value,
        per_page: perPage.value,
      },
    })

    tenants.value = res.tenants ?? []
    total.value = res.total ?? 0
    totalPages.value = res.total_pages ?? 1
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

watch([page, perPage, coreFilter], fetchTenants)

let searchTimer
watch(searchQuery, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    fetchTenants()
  }, 400)
})

onMounted(fetchTenants)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <div class="mb-4">
        <h4 class="text-h4 mb-1">
          مجموعه‌ها
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          مدیریت tenantها و سلامت اشتراک
        </p>
      </div>

      <VCard>
        <VCardText class="d-flex flex-wrap align-center gap-4">
          <AppTextField
            v-model="searchQuery"
            placeholder="جستجو..."
            prepend-inner-icon="tabler-search"
            style="max-inline-size: 260px;"
            clearable
            hide-details
            density="compact"
          />
          <AppSelect
            v-model="coreFilter"
            :items="coreItems"
            label="ماژول پایه"
            density="compact"
            hide-details
            style="max-inline-size: 180px;"
          />
        </VCardText>
        <VDivider />
        <VDataTable
          :headers="headers"
          :items="tenants"
          :loading="loading"
          hide-default-footer
          class="text-no-wrap"
        >
          <template #item.owner="{ item }">
            <div>{{ item.owner?.name ?? '—' }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ item.owner?.phone }}
            </div>
          </template>
          <template #item.health_score="{ item }">
            <VChip
              size="small"
              :color="healthColor(item.health_score)"
              variant="tonal"
            >
              {{ item.health_label }} ({{ item.health_score }})
            </VChip>
          </template>
          <template #item.has_core_module="{ item }">
            <VIcon
              :icon="item.has_core_module ? 'tabler-check' : 'tabler-x'"
              :color="item.has_core_module ? 'success' : 'error'"
            />
          </template>
          <template #item.status="{ item }">
            <VChip
              size="small"
              :color="item.status === 'active' ? 'success' : 'error'"
              variant="tonal"
            >
              {{ item.status === 'active' ? 'فعال' : 'معلق' }}
            </VChip>
          </template>
          <template #item.last_transaction="{ item }">
            <template v-if="item.last_transaction">
              {{ formatRial(item.last_transaction.amount) }}
              <div class="text-caption">
                {{ formatDateTime(item.last_transaction.created_at) }}
              </div>
            </template>
            <span v-else>—</span>
          </template>
          <template #item.actions="{ item }">
            <IconBtn @click="router.push({ name: 'apps-platform-tenants-id', params: { id: item.id } })">
              <VIcon icon="tabler-eye" />
            </IconBtn>
          </template>
        </VDataTable>
        <VDivider />
        <VCardText class="d-flex justify-end">
          <VPagination
            v-model="page"
            :length="Math.max(totalPages, 1)"
            :total-visible="5"
          />
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
