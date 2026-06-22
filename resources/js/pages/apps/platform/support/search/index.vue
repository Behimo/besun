<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'PlatformSupport',
  },
})

const loading = ref(false)
const query = ref('')
const results = ref(null)

const search = async () => {
  if (query.value.trim().length < 2)
    return

  loading.value = true
  try {
    results.value = await $api('/platform/support/search', { query: { q: query.value.trim() } })
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

let timer
watch(query, () => {
  clearTimeout(timer)
  timer = setTimeout(search, 400)
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <div class="mb-4">
        <h4 class="text-h4 mb-1">
          جستجوی سریع
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          مجموعه یا کاربر را با نام، slug یا موبایل پیدا کنید
        </p>
      </div>

      <VCard>
        <VCardText>
          <AppTextField
            v-model="query"
            placeholder="حداقل ۲ کاراکتر..."
            prepend-inner-icon="tabler-search"
            :loading="loading"
            clearable
          />
        </VCardText>
      </VCard>

      <VRow
        v-if="results"
        class="mt-4"
      >
        <VCol
          cols="12"
          md="6"
        >
          <VCard title="مجموعه‌ها">
            <VList v-if="results.tenants?.length">
              <VListItem
                v-for="t in results.tenants"
                :key="t.id"
                :title="t.name"
                :subtitle="`${t.slug} · ${t.owner_phone ?? ''}`"
              >
                <template #append>
                  <VChip
                    size="x-small"
                    :color="t.has_core ? 'success' : 'warning'"
                  >
                    {{ t.has_core ? 'فعال' : 'بدون پایه' }}
                  </VChip>
                </template>
              </VListItem>
            </VList>
            <VCardText
              v-else
              class="text-medium-emphasis"
            >
              نتیجه‌ای نیست
            </VCardText>
          </VCard>
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <VCard title="کاربران">
            <VList v-if="results.users?.length">
              <VListItem
                v-for="u in results.users"
                :key="u.id"
                :title="u.name"
                :subtitle="`${u.phone} · ${u.email ?? ''}`"
              />
            </VList>
            <VCardText
              v-else
              class="text-medium-emphasis"
            >
              نتیجه‌ای نیست
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCol>
  </VRow>
</template>
