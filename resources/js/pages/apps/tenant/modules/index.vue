<script setup>
import { CATEGORY_LABELS, groupCatalogModules } from '@/config/crm-modules'

const { userData, exitTenantShell } = useAppShell()

const catalog = ref([])
const catalogCategories = ref(CATEGORY_LABELS)
const subscription = ref(null)
const loading = ref(true)

const storeLink = computed(() => ({
  name: 'apps-account-modules',
  query: userData.value?.tenant?.id ? { tenant: userData.value.tenant.id } : {},
}))

const groupedModules = computed(() =>
  groupCatalogModules(catalog.value, catalogCategories.value))

const fetchData = async () => {
  loading.value = true
  try {
    const [catalogRes, subRes] = await Promise.all([
      $api('/modules/catalog'),
      $api('/subscription').catch(() => null),
    ])
    catalog.value = catalogRes.modules ?? []
    if (catalogRes.categories)
      catalogCategories.value = catalogRes.categories
    subscription.value = subRes
  } finally {
    loading.value = false
  }
}

const isActive = slug => subscription.value?.active_modules?.includes(slug)

const moduleStatus = mod => {
  if (!userData.value?.hasCoreModule && mod.is_core)
    return { label: 'قفل', color: 'secondary' }

  if (isActive(mod.slug))
    return { label: 'فعال', color: 'success' }

  return { label: 'قفل', color: 'secondary' }
}

onMounted(fetchData)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard :loading="loading">
        <VCardText>
          <h4 class="text-h4 mb-2">
            وضعیت ماژول‌های مجموعه
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-4">
            خرید و تمدید ماژول‌ها فقط از
            <RouterLink :to="storeLink">
              فروشگاه ماژول در حساب کاربری
            </RouterLink>
            انجام می‌شود.
          </p>

          <VAlert
            v-if="!userData?.hasCoreModule"
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            ماژول پایه فعال نیست. همه آیتم‌های منو قفل هستند تا خرید انجام شود.
          </VAlert>

          <div
            v-for="group in groupedModules"
            :key="group.key"
            class="mb-6"
          >
            <h5 class="text-h5 mb-3">
              {{ group.label }}
            </h5>
            <VRow>
              <VCol
                v-for="mod in group.modules"
                :key="mod.id"
                cols="12"
                md="6"
              >
                <VCard
                  variant="outlined"
                  class="h-100"
                >
                  <VCardText>
                    <div class="d-flex align-center justify-space-between mb-2">
                      <div class="d-flex align-center gap-2">
                        <VIcon
                          v-if="mod.icon"
                          :icon="mod.icon"
                          size="22"
                        />
                        <h6 class="text-h6">
                          {{ mod.name }}
                        </h6>
                      </div>
                      <VChip
                        size="small"
                        :color="moduleStatus(mod).color"
                        variant="tonal"
                      >
                        {{ moduleStatus(mod).label }}
                      </VChip>
                    </div>
                    <p class="text-body-2 text-medium-emphasis mb-2">
                      {{ mod.description }}
                    </p>
                    <VList
                      v-if="mod.features?.length"
                      density="compact"
                      class="pa-0"
                    >
                      <VListItem
                        v-for="(f, i) in mod.features.slice(0, 3)"
                        :key="i"
                        :title="f"
                        prepend-icon="tabler-point"
                        class="px-0"
                      />
                    </VList>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </div>

          <div class="d-flex flex-wrap gap-3 mt-6">
            <VBtn
              color="primary"
              :to="storeLink"
            >
              رفتن به فروشگاه ماژول
            </VBtn>
            <VBtn
              variant="tonal"
              @click="exitTenantShell"
            >
              بازگشت به حساب کاربری
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
