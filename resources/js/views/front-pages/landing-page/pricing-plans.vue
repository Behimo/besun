<script setup>
import paperPlane from '@images/front-pages/icons/paper-airplane.png'
import { BRAND } from '@/config/brand'
import plane from '@images/front-pages/icons/plane.png'
import pricingPlanArrow from '@images/front-pages/icons/pricing-plans-arrow.png'
import shuttleRocket from '@images/front-pages/icons/shuttle-rocket.png'

const planImages = [paperPlane, plane, shuttleRocket]
const plans = ref([])
const modules = ref([])

onMounted(async () => {
  try {
    const res = await $api('/plans')
    plans.value = (res.plans ?? []).map((plan, index) => ({
      ...plan,
      image: planImages[index % planImages.length],
      current: index === 1,
    }))
    modules.value = res.modules ?? []
  } catch (e) {
    console.error(e)
  }
})
</script>

<template>
  <div id="pricing-plan">
    <VContainer>
      <div class="pricing-plans">
        <div class="headers d-flex justify-center flex-column align-center flex-wrap">
          <VChip
            label
            color="primary"
            class="mb-4"
            size="small"
          >
            قیمت‌گذاری
          </VChip>
          <h2 class="text-h4 pricing-title mb-2">
            پلن‌های {{ BRAND.nameFa }}
          </h2>
          <p class="text-body-1 mb-0 text-medium-emphasis">
            ماهانه، ۶ماهه و سالانه — قیمت بر اساس صندلی (کاربر)
          </p>
        </div>

        <VRow class="match-height">
          <VCol
            v-for="plan in plans"
            :key="plan.id"
            cols="12"
            md="4"
          >
            <VCard
              flat
              border
              :class="plan.current ? 'border-primary border-opacity-100' : ''"
            >
              <VCardText class="pa-8">
                <VImg
                  :src="plan.image"
                  width="120"
                  class="mb-4"
                />
                <h5 class="text-h5 mb-1">
                  {{ plan.name }}
                </h5>
                <div class="d-flex align-center mb-4">
                  <h1 class="text-h1 text-primary">
                    {{ Number(plan.price).toLocaleString('fa-IR') }}
                  </h1>
                  <span class="text-body-1 ms-2">ریال / {{ plan.duration_months }} ماه</span>
                </div>
                <VList class="card-list">
                  <VListItem
                    v-for="(feature, i) in (plan.features || [])"
                    :key="i"
                  >
                    <template #prepend>
                      <VIcon
                        icon="tabler-circle-filled"
                        size="8"
                        color="primary"
                        class="me-3"
                      />
                    </template>
                    <VListItemTitle class="text-body-1">
                      {{ feature }}
                    </VListItemTitle>
                  </VListItem>
                </VList>
                <VBtn
                  block
                  :variant="plan.current ? 'elevated' : 'tonal'"
                  :to="{ name: 'login' }"
                  class="mt-6"
                >
                  شروع کنید
                </VBtn>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <div
          v-if="modules.length"
          class="mt-12"
        >
          <h5 class="text-h5 text-center mb-6">
            ماژول‌های اضافی
          </h5>
          <VRow>
            <VCol
              v-for="mod in modules"
              :key="mod.id"
              cols="12"
              md="4"
            >
              <VCard variant="tonal">
                <VCardText>
                  <h6 class="text-h6 mb-2">
                    {{ mod.name }}
                  </h6>
                  <p class="text-body-2 mb-2">
                    {{ mod.description }}
                  </p>
                  <div class="text-primary font-weight-medium">
                    {{ Number(mod.price).toLocaleString('fa-IR') }} ریال
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <div class="text-center mt-8">
          <img
            :src="pricingPlanArrow"
            alt=""
            height="60"
          >
        </div>
      </div>
    </VContainer>
  </div>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 0.5rem;
}

.pricing-title {
  font-weight: 800;
}
</style>
