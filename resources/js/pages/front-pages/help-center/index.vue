<script setup>
import Footer from '@/views/front-pages/front-page-footer.vue'
import Navbar from '@/views/front-pages/front-page-navbar.vue'
import { helpArticlesFa, helpCenterFa } from '@/data/help-center-fa'
import { BRAND } from '@/config/brand'
import { useConfigStore } from '@core/stores/config'

const store = useConfigStore()
store.skin = 'default'

definePage({
  meta: { layout: 'blank', public: true },
})

usePageSeo({
  title: `مرکز راهنما | ${BRAND.nameFa}`,
  description: `راهنمای استفاده از ${BRAND.nameFa} — شروع کار، قیف فروش، کمپین و گزارش.`,
  canonical: '/front-pages/help-center',
})

const search = ref('')

const filteredCategories = computed(() => {
  const q = search.value.trim()
  if (!q)
    return helpCenterFa.categories

  return helpCenterFa.categories
    .map(cat => ({
      ...cat,
      articles: cat.articles.filter(a =>
        a.title.includes(q) || helpArticlesFa[a.slug]?.content?.some(c => c.includes(q)),
      ),
    }))
    .filter(cat => cat.articles.length)
})
</script>

<template>
  <div class="help-center-page landing-page-wrapper">
    <Navbar />

    <VContainer class="py-16">
      <div class="text-center mb-10">
        <h1 class="text-h3 mb-2">
          مرکز راهنمای {{ BRAND.nameFa }}
        </h1>
        <p class="text-body-1 text-medium-emphasis mb-6">
          آموزش CRM، قیف فروش، کمپین و ماژول‌ها
        </p>
        <VTextField
          v-model="search"
          prepend-inner-icon="tabler-search"
          label="جستجو در راهنما"
          max-width="480"
          class="mx-auto"
        />
      </div>

      <h2 class="text-h5 mb-4">
        مقالات پرطرفدار
      </h2>
      <VRow class="mb-10">
        <VCol
          v-for="article in helpCenterFa.popularArticles"
          :key="article.slug"
          cols="12"
          md="4"
        >
          <VCard
            :to="{ name: 'front-pages-help-center-article-title', params: { title: article.slug } }"
            hover
          >
            <VCardText>
              <VChip
                size="x-small"
                class="mb-2"
              >
                {{ article.category }}
              </VChip>
              <h3 class="text-h6 mb-2">
                {{ article.title }}
              </h3>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ article.excerpt }}
              </p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <h2 class="text-h5 mb-4">
        پایگاه دانش
      </h2>
      <VRow>
        <VCol
          v-for="cat in filteredCategories"
          :key="cat.title"
          cols="12"
          md="6"
        >
          <VCard>
            <VCardTitle class="d-flex align-center gap-2">
              <VIcon :icon="cat.icon" />
              {{ cat.title }}
            </VCardTitle>
            <VList>
              <VListItem
                v-for="article in cat.articles"
                :key="article.slug"
                :to="{ name: 'front-pages-help-center-article-title', params: { title: article.slug } }"
              >
                {{ article.title }}
              </VListItem>
            </VList>
          </VCard>
        </VCol>
      </VRow>
    </VContainer>

    <Footer />
  </div>
</template>
