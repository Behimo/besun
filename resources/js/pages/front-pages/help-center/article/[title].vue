<script setup>
import Footer from '@/views/front-pages/front-page-footer.vue'
import Navbar from '@/views/front-pages/front-page-navbar.vue'
import { helpArticlesFa } from '@/data/help-center-fa'
import { BRAND } from '@/config/brand'
import { useConfigStore } from '@core/stores/config'

const store = useConfigStore()
store.skin = 'default'
const route = useRoute()

definePage({
  meta: { layout: 'blank', public: true },
})

const slug = computed(() => route.params.title)
const article = computed(() => helpArticlesFa[slug.value])

usePageSeo(computed(() => ({
  title: article.value
    ? `${article.value.title} | راهنما ${BRAND.nameFa}`
    : `راهنما | ${BRAND.nameFa}`,
  description: article.value?.content?.[0] ?? BRAND.description,
  canonical: `/front-pages/help-center/article/${slug.value}`,
})))
</script>

<template>
  <div class="landing-page-wrapper">
    <Navbar />

    <VContainer class="py-16">
      <VRow justify="center">
        <VCol
          cols="12"
          md="8"
        >
          <VBtn
            variant="text"
            prepend-icon="tabler-arrow-right"
            class="mb-4 flip-in-rtl"
            :to="{ name: 'front-pages-help-center' }"
          >
            بازگشت به راهنما
          </VBtn>

          <template v-if="article">
            <VChip
              size="small"
              class="mb-3"
            >
              {{ article.category }}
            </VChip>
            <h1 class="text-h4 mb-6">
              {{ article.title }}
            </h1>
            <VCard>
              <VCardText>
                <p
                  v-for="(para, i) in article.content"
                  :key="i"
                  class="text-body-1"
                >
                  {{ para }}
                </p>
              </VCardText>
            </VCard>
          </template>

          <VAlert
            v-else
            type="warning"
            variant="tonal"
          >
            مقاله یافت نشد.
          </VAlert>
        </VCol>
      </VRow>
    </VContainer>

    <Footer />
  </div>
</template>
