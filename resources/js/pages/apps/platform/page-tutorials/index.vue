<script setup>
import { getAparatEmbedUrl } from '@/data/page-tutorials'

definePage({
  meta: {
    action: 'manage',
    subject: 'PlatformAdmin',
  },
})

const { invalidateCache } = usePageTutorialsApi()

const items = ref([])
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const search = ref('')
const editDialog = ref(false)
const editItem = ref(null)
const editForm = ref({
  title: '',
  description: '',
  video_url: '',
  poster_url: '',
  is_active: true,
})

const fetchItems = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await $api('/platform/page-tutorials')
    items.value = res.items ?? []
  } catch (e) {
    error.value = e?.data?.message || 'دسترسی مدیریت پلتفرم ندارید.'
  } finally {
    loading.value = false
  }
}

const filteredItems = computed(() => {
  const q = search.value.trim()
  if (!q)
    return items.value

  return items.value.filter(item =>
    item.title.includes(q)
    || item.route_name.includes(q)
    || item.group?.includes(q))
})

const groupedItems = computed(() => {
  const groups = new Map()

  for (const item of filteredItems.value) {
    const key = item.group || 'سایر'
    if (!groups.has(key))
      groups.set(key, [])

    groups.get(key).push(item)
  }

  return [...groups.entries()].map(([group, rows]) => ({ group, rows }))
})

const previewEmbed = computed(() =>
  editForm.value.video_url ? getAparatEmbedUrl(editForm.value.video_url) : null)

const openEdit = item => {
  editItem.value = item
  editForm.value = {
    title: item.title,
    description: item.description || '',
    video_url: item.video_url || '',
    poster_url: item.poster_url || '',
    is_active: item.is_active,
  }
  success.value = ''
  editDialog.value = true
}

const save = async () => {
  if (!editItem.value)
    return

  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await $api(`/platform/page-tutorials/${editItem.value.route_name}`, {
      method: 'PUT',
      body: editForm.value,
    })
    success.value = 'آموزش ذخیره شد.'
    editDialog.value = false
    invalidateCache()
    await fetchItems()
  } catch (e) {
    error.value = e?.data?.message || 'خطا در ذخیره'
  } finally {
    saving.value = false
  }
}

onMounted(fetchItems)
</script>

<template>
  <VCard :loading="loading">
    <VCardText>
      <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-4">
        <div>
          <h4 class="text-h4 mb-1">
            مدیریت ویدئوهای آموزشی
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            لینک ویدئو را از آپارات وارد کنید — مثال: https://www.aparat.com/v/xxxxx
          </p>
        </div>
        <VTextField
          v-model="search"
          prepend-inner-icon="tabler-search"
          placeholder="جستجو..."
          density="compact"
          hide-details
          style="max-inline-size: 16rem;"
        />
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
        v-if="success"
        type="success"
        variant="tonal"
        class="mb-4"
      >
        {{ success }}
      </VAlert>

      <div
        v-for="section in groupedItems"
        :key="section.group"
        class="mb-6"
      >
        <h6 class="text-h6 mb-3">
          {{ section.group }}
        </h6>

        <VRow>
          <VCol
            v-for="item in section.rows"
            :key="item.route_name"
            cols="12"
            md="6"
            lg="4"
          >
            <VCard
              variant="outlined"
              class="h-100"
            >
              <VCardText>
                <div class="d-flex align-center justify-space-between mb-2">
                  <span class="font-weight-medium">{{ item.title }}</span>
                  <VChip
                    :color="item.is_active && item.video_url ? 'success' : 'secondary'"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ item.is_active && item.video_url ? 'فعال' : 'خالی' }}
                  </VChip>
                </div>
                <p class="text-caption text-medium-emphasis mb-3">
                  {{ item.route_name }}
                </p>
                <p
                  v-if="item.description"
                  class="text-body-2 mb-3"
                >
                  {{ item.description }}
                </p>
                <VBtn
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-edit"
                  @click="openEdit(item)"
                >
                  ویرایش ویدئو
                </VBtn>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </VCardText>
  </VCard>

  <VDialog
    v-model="editDialog"
    max-width="640"
    persistent
  >
    <VCard v-if="editItem">
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>ویرایش آموزش — {{ editItem.title }}</span>
        <IconBtn @click="editDialog = false">
          <VIcon icon="tabler-x" />
        </IconBtn>
      </VCardTitle>

      <VCardText>
        <VRow>
          <VCol cols="12">
            <VTextField
              v-model="editForm.title"
              label="عنوان"
            />
          </VCol>
          <VCol cols="12">
            <VTextarea
              v-model="editForm.description"
              label="توضیح کوتاه"
              rows="2"
              auto-grow
            />
          </VCol>
          <VCol cols="12">
            <VTextField
              v-model="editForm.video_url"
              label="لینک ویدئو آپارات"
              placeholder="https://www.aparat.com/v/xxxxx"
              hint="ویدئو را در آپارات بارگذاری کنید و لینک را اینجا بگذارید"
              persistent-hint
            />
          </VCol>
          <VCol cols="12">
            <VTextField
              v-model="editForm.poster_url"
              label="تصویر پیش‌نمایش (اختیاری)"
              placeholder="/marketing/crm-dashboard.png"
            />
          </VCol>
          <VCol cols="12">
            <VSwitch
              v-model="editForm.is_active"
              label="نمایش برای کاربران"
              color="primary"
              hide-details
            />
          </VCol>
          <VCol
            v-if="previewEmbed"
            cols="12"
          >
            <p class="text-body-2 mb-2">
              پیش‌نمایش:
            </p>
            <div class="tutorial-preview-frame">
              <iframe
                :src="previewEmbed"
                title="پیش‌نمایش آپارات"
                allowfullscreen
              />
            </div>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="editDialog = false"
        >
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          @click="save"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.tutorial-preview-frame {
  position: relative;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.05);
}

.tutorial-preview-frame iframe {
  display: block;
  border: 0;
  block-size: 100%;
  inline-size: 100%;
}
</style>
